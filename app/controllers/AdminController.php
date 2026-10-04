<?php
/**
 * app/controllers/AdminController.php  —  Admin section (dashboard, groups,
 * examinees, exams, question builder, results & CSV export)
 */
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/Group.php';
require_once dirname(__DIR__) . '/models/Exam.php';
require_once dirname(__DIR__) . '/models/Question.php';
require_once dirname(__DIR__) . '/models/Result.php';

class AdminController
{
    private User $users;
    private Group $groups;
    private Exam $exams;
    private Question $questions;
    private Result $results;

    public function __construct()
    {
        $this->users     = new User();
        $this->groups    = new Group();
        $this->exams     = new Exam();
        $this->questions = new Question();
        $this->results   = new Result();
        require_login('admin');
    }

    /* ---------------------------- DASHBOARD ---------------------------- */
    public function dashboard(): void
    {
        $stats = [
            'active_groups'       => count($this->groups->all()),
            'registered_examinees'=> $this->users->countExaminees(),
            'scheduled_tests'     => count($this->exams->all()),
            'completed_attempts'  => count($this->results->allResults()),
            'pass_rate'           => $this->results->passRate(),
        ];
        $recent = array_slice($this->results->allResults(), 0, 8);
        $groups = $this->groups->all();
        require APP_ROOT . '/app/views/admin/dashboard.php';
    }

    /* ------------------------------ GROUPS ----------------------------- */
    /* ------------------------------ GROUPS ----------------------------- */
    public function groups(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['action'] ?? '';
            
            if ($action === 'create' && trim((string)$_POST['name']) !== '') {
                $this->groups->create(trim((string)$_POST['name']), $_POST['description'] ?? null);
                flash('Exam group created.');
            } elseif ($action === 'update') {
                // ✅ ADDED: Handle group updates
                $name = trim((string)$_POST['name']);
                if ($name !== '') {
                    $this->groups->update((int)$_POST['id'], $name, $_POST['description'] ?? null);
                    flash('Exam group updated.');
                } else {
                    flash('Group name cannot be empty.', 'danger');
                }
            } elseif ($action === 'delete') {
                $this->groups->delete((int)$_POST['id']);
                flash('Exam group deleted.', 'warning');
            } elseif ($action === 'assign') {
                $this->groups->syncUserGroups((int)$_POST['user_id'], $_POST['group_ids'] ?? []);
                flash('Group assignments updated.');
            }
            
            redirect('index.php?page=admin_groups');
        }

        $groups    = $this->groups->all();
        $examinees = $this->users->allExaminees();
        require APP_ROOT . '/app/views/admin/groups.php';
    }

    /* ---------------------------- EXAMINEES ---------------------------- */
    public function examinees(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action   = $_POST['action'] ?? '';
            $groupIds = $_POST['group_ids'] ?? [];

            try {
                if ($action === 'create') {
                    $this->users->createExaminee(
                        (string)$_POST['reg_number'], (string)$_POST['full_name'],
                        $_POST['email'] ?? null, (string)$_POST['password'], $groupIds
                    );
                    flash('Examinee registered.');
                } elseif ($action === 'update') {
                    $this->users->updateExaminee(
                        (int)$_POST['id'], (string)$_POST['reg_number'], (string)$_POST['full_name'],
                        $_POST['email'] ?? null, $groupIds, $_POST['password'] ?? null
                    );
                    flash('Examinee updated.');
                } elseif ($action === 'delete') {
                    $this->users->delete((int)$_POST['id']);
                    flash('Examinee deleted.', 'warning');
                } elseif ($action === 'bulk_upload_examinees') {
                    // Handle Bulk CSV Upload
                    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                        $result = $this->users->bulkCreateFromCsv($_FILES['csv_file']['tmp_name']);
                        flash("Successfully imported {$result['created']} examinees. ({$result['skipped']} skipped/duplicates).", 'success');
                    } else {
                        flash('Please select a valid CSV file.', 'danger');
                    }
                }
            } catch (Throwable $e) {
                flash('Operation failed: ' . $e->getMessage(), 'danger');
            }
            redirect('index.php?page=admin_examinees');
        }

        $examinees = $this->users->allExaminees();
        $groups    = $this->groups->all();
        require APP_ROOT . '/app/views/admin/examinees.php';
    }

    /* ------------------------------ EXAMS ------------------------------ */
    public function exams(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create') {
                    $this->exams->create($_POST);
                    flash('Exam created. Now add questions to it.');
                } elseif ($action === 'update') {
                    $this->exams->update((int)$_POST['id'], $_POST);
                    flash('Exam updated.');
                } elseif ($action === 'delete') {
                    $this->exams->delete((int)$_POST['id']);
                    flash('Exam deleted.', 'warning');
                }
            } catch (Throwable $e) {
                flash('Operation failed: ' . $e->getMessage(), 'danger');
            }
            redirect('index.php?page=admin_exams');
        }

        $exams  = $this->exams->all();
        $groups = $this->groups->all();
        require APP_ROOT . '/app/views/admin/exams.php';
    }

    /* ------------------------- QUESTION BUILDER ------------------------ */
    public function questions(): void
    {
        // ✅ Check both GET and POST to ensure we always have the exam_id
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $exam   = $examId ? $this->exams->find($examId) : null;
        
        if (!$exam) {
            flash('Select a valid exam to manage questions.', 'danger');
            redirect('index.php?page=admin_exams');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create' || $action === 'update') {
                    $diagramPath = $_POST['existing_diagram'] ?? null;
                    if (!empty($_FILES['diagram']['name'])) {
                        $diagramPath = Question::uploadDiagram($_FILES['diagram']);
                    }
                    $options = $this->collectOptions($_POST);

                    if ($action === 'create') {
                        $this->questions->create($examId, (string)$_POST['question_text'], $diagramPath, $_POST['explanation'] ?? null, $options);
                        flash('Question added.');
                    } else {
                        $this->questions->update((int)$_POST['id'], (string)$_POST['question_text'], $diagramPath, $_POST['explanation'] ?? null, $options);
                        flash('Question updated.');
                    }
                } elseif ($action === 'delete') {
                    $this->questions->delete((int)$_POST['id']);
                    flash('Question deleted.', 'warning');
                } elseif ($action === 'bulk_upload') {
                    // ✅ Handle Bulk CSV Upload for Questions
                    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                        $count = $this->questions->bulkCreateFromCsv($examId, $_FILES['csv_file']['tmp_name']);
                        flash("Successfully imported {$count} questions!", 'success');
                    } else {
                        flash('Please select a valid CSV file.', 'danger');
                    }
                }
            } catch (Throwable $e) {
                flash('Question operation failed: ' . $e->getMessage(), 'danger');
            }
            
            // ✅ IMPORTANT: Redirect must always include the exam_id
            redirect('index.php?page=admin_questions&exam_id=' . $examId);
        }

        $questions = $this->questions->forExam($examId);
        require APP_ROOT . '/app/views/admin/questions.php';
    }

    private function collectOptions(array $post): array
    {
        $texts    = $post['option_text'] ?? [];
        $correct  = (int)($post['correct_index'] ?? 0);
        $options  = [];
        foreach ($texts as $i => $text) {
            if (trim((string)$text) === '') {
                continue;
            }
            $options[] = ['option_text' => $text, 'is_correct' => ((int)$i === $correct) ? 1 : 0];
        }
        if (count($options) < 2) {
            throw new RuntimeException('At least two options are required.');
        }
        if (!array_filter($options, fn($o) => $o['is_correct'])) {
            throw new RuntimeException('Mark exactly one option as the correct answer.');
        }
        return $options;
    }

    public function analytics(): void
    {
        require_login('admin');
        $examId = (int)($_GET['exam_id'] ?? 0);
        $exam = $examId ? (new Exam())->find($examId) : null;
        $analysis = $exam ? (new Result())->getItemAnalysis($examId) : [];
        $exams = (new Exam())->all(); // Assuming you have an all() method, or use your existing fetch logic
        require APP_ROOT . '/app/views/admin/analytics.php';
    }

    /* --------------------------- RESULTS / CSV ------------------------- */
    public function results(): void
    {
        $attempt = null;
        $log     = [];
        if (!empty($_GET['attempt_id'])) {
            $attempt = $this->results->findAttempt((int)$_GET['attempt_id']);
            $log     = $this->results->questionLog((int)$_GET['attempt_id']);
        }
        $results = $this->results->allResults();
        require APP_ROOT . '/app/views/admin/results.php';
    }

    public function exportCsv(): void
    {
        $this->results->exportCsv();
    }
}
