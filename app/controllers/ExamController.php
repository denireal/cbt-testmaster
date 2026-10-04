<?php
/**
 * app/controllers/ExamController.php  —  Examinee dashboard, CBT screen, scorecard
 */
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/Group.php';
require_once dirname(__DIR__) . '/models/Exam.php';
require_once dirname(__DIR__) . '/models/Question.php';
require_once dirname(__DIR__) . '/models/Result.php';

class ExamController
{
    private User $users;
    private Exam $exams;
    private Question $questions;
    private Result $results;
    private array $me;

    public function __construct()
    {
        $this->users     = new User();
        $this->exams     = new Exam();
        $this->questions = new Question();
        $this->results   = new Result();
        $this->me        = require_login('examinee');
    }

    /** GET index.php?page=student_dashboard */
    public function dashboard(): void
    {
        $me    = $this->me;
        $exams = $this->exams->forExaminee((int)$this->me['id']);
        require APP_ROOT . '/app/views/examinee/dashboard.php';
    }

    /**
     * GET index.php?page=exam&exam_id=1
     * Creates (or resumes) an attempt, then renders the CBT interface.
     */
    public function start(): void
    {
        $examId = (int)($_GET['exam_id'] ?? 0);
        $exam   = $examId ? $this->exams->find($examId) : null;
        $userId = (int)$this->me['id'];

        // 1. Validate Exam Exists and is Active
        if (!$exam || !$exam['is_active']) {
            flash('Exam not found or is currently inactive.', 'danger');
            redirect('index.php?page=student_dashboard');
        }

        // 2. Check for existing attempts for this user and exam
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM exam_attempts WHERE user_id = :uid AND exam_id = :eid ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'eid' => $examId]);
        $attempt = $stmt->fetch();

        // 3. Handle Existing Attempts
        if ($attempt) {
            if (in_array($attempt['status'], ['completed', 'timed_out'])) {
                flash('You have already completed this examination.', 'info');
                redirect('index.php?page=result&attempt_id=' . $attempt['id']);
            }
        } else {
            // 4. No existing attempt, create a brand new one
            try {
                $attempt = $this->results->startAttempt($userId, $examId);
            } catch (Throwable $e) {
                flash('Could not start exam: ' . $e->getMessage(), 'danger');
                redirect('index.php?page=student_dashboard');
            }
        }

        // 5. Rebuild the question list in the exact stored order for this attempt
        $order        = json_decode((string)$attempt['question_order'], true) ?: [];
        $optionOrders = json_decode((string)$attempt['option_orders'], true) ?: [];
        
        // Fetch questions (we don't shuffle here, we use the stored shuffled order)
        $pool = $this->questions->forExam($examId, false, false);
        $byId = [];
        foreach ($pool as $q) {
            $byId[(int)$q['id']] = $q;
        }

        $letters  = range('A', 'Z');
        $questions = [];
        foreach ($order as $qid) {
            if (!isset($byId[(int)$qid])) continue;
            
            $q    = $byId[(int)$qid];
            $opts = $q['options'];
            $map  = [];
            foreach ($opts as $o) {
                $map[(int)$o['id']] = $o;
            }
            
            $ordered = [];
            foreach (($optionOrders[$qid] ?? []) as $i => $oid) {
                if (isset($map[(int)$oid])) {
                    $opt = $map[(int)$oid];
                    $opt['option_letter'] = $letters[$i] ?? (string)($i + 1);
                    $ordered[] = $opt;
                }
            }
            $q['options'] = $ordered ?: $opts;
            $questions[]  = $q;
        }

        // NEW: Apply "Questions to Answer" limit if set
        $limit = (int)($exam['questions_to_answer'] ?? 0);
        if ($limit > 0 && count($questions) > $limit) {
            // Shuffle first to ensure random selection, then slice
            shuffle($questions);
            $questions = array_slice($questions, 0, $limit);
        }

        // 6. FALLBACK: If rebuilding failed (e.g., corrupted attempt data), just use the pool directly
        if (empty($questions)) {
            $questions = $pool;
        }

        // 7. Prevent loading an empty exam
        if (empty($questions)) {
            flash('This exam has no questions configured. Please contact the administrator.', 'danger');
            redirect('index.php?page=student_dashboard');
        }

        // 8. Calculate remaining time (PURE DURATION, NO TIMEZONE MATH)
        $remaining = (int)$exam['duration_minutes'] * 60;

        // 9. Fetch saved answers for this attempt
        $saved = $this->results->savedAnswers((int)$attempt['id']);

        // 10. Make $me available to the view
        $me = $this->me;

        // 11. Render the exam screen
        require APP_ROOT . '/app/views/examinee/exam_screen.php';
    }

    /** GET index.php?page=result&attempt_id=1 */
    public function result(): void
    {
        $attemptId = (int)($_GET['attempt_id'] ?? 0);
        $attempt   = $this->results->findAttempt($attemptId);

        if (!$attempt || (int)$attempt['user_id'] !== (int)$this->me['id']) {
            flash('Scorecard not found.', 'danger');
            redirect('index.php?page=student_dashboard');
        }

        if ($attempt['status'] !== 'completed') {
            $this->results->submitAndCalculateScore($attemptId, 'timed_out');
            $attempt = $this->results->findAttempt($attemptId);
        }

        $log = (int)$attempt['show_immediate_score'] === 1 ? $this->results->questionLog($attemptId) : [];

        require APP_ROOT . '/app/views/examinee/result.php';
    }

    /** POST AJAX: Save a single answer during the exam */
    public function saveAnswer(): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['attempt_id'], $input['question_id'])) {
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }

        try {
            $this->results->saveAnswer(
                (int)$input['attempt_id'],
                (int)$input['question_id'],
                isset($input['option_id']) ? (int)$input['option_id'] : null,
                isset($input['flagged']) ? (bool)$input['flagged'] : null
            );
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /** POST index.php?page=submit_exam */
    public function submitExam(): void
    {
        verify_csrf();
        $attemptId = (int)($_POST['attempt_id'] ?? 0);
        
        try {
            $this->results->submitAndCalculateScore($attemptId, 'completed');
            flash('Examination submitted successfully!', 'success');
        } catch (Throwable $e) {
            flash('Submission failed: ' . $e->getMessage(), 'danger');
        }
        
        redirect('index.php?page=result&attempt_id=' . $attemptId);
    }

    /** POST index.php?page=admin_questions (with action=bulk_upload) */
    public function handleBulkUpload(): void
    {
        $examId = (int)($_POST['exam_id'] ?? 0);
        if (!$examId) {
            flash('Invalid exam.', 'danger');
            redirect('index.php?page=admin_exams');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    flash('Only CSV files are allowed.', 'danger');
                } else {
                    try {
                        $count = $this->questions->bulkCreateFromCsv($examId, $file['tmp_name']);
                        flash("Successfully imported {$count} questions!", 'success');
                    } catch (\Throwable $e) {
                        flash($e->getMessage(), 'danger');
                    }
                }
            } else {
                flash('File upload error. Please try again.', 'danger');
            }
        }
        
        redirect('index.php?page=admin_questions&exam_id=' . $examId);
    }
}