<?php
/**
 * app/models/Result.php  —  Attempt lifecycle, grading engine, reports, CSV export
 *
 * Grading formula:
 *   Final Score = (Total Correct x Positive Mark) - (Total Incorrect x Negative Mark)
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

class Result
{
    private PDO $db;

    public function __construct() { $this->db = Database::getConnection(); }

    /* -------------------------------------------------------------- *
     |  ATTEMPT CREATION (locks a row, stores shuffled order in MySQL)  |
     * -------------------------------------------------------------- */
    public function startAttempt(int $userId, int $examId): array
    {
        // Resume an in-progress attempt instead of creating a duplicate
        $stmt = $this->db->prepare(
            "SELECT * FROM exam_attempts
             WHERE user_id = :uid AND exam_id = :eid AND status = 'in_progress'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'eid' => $examId]);
        if ($attempt = $stmt->fetch()) {
            return $attempt;
        }

        $exam = (new Exam())->find($examId);
        if (!$exam) {
            throw new RuntimeException('Exam not found.');
        }

        $questions = (new Question())->forExam($examId, (bool)$exam['shuffle_questions'], (bool)$exam['shuffle_options']);
        if (count($questions) === 0) {
            throw new RuntimeException('This exam has no questions yet.');
        }

        // ✅ Limit questions to answer if specified
        $limit = (int)($exam['questions_to_answer'] ?? 0);
        if ($limit > 0 && count($questions) > $limit) {
            shuffle($questions);
            $questions = array_slice($questions, 0, $limit);
        }

        $questionOrder = array_map(fn($q) => (int)$q['id'], $questions);
        $optionOrders  = [];
        foreach ($questions as $q) {
            $optionOrders[$q['id']] = array_map(fn($o) => (int)$o['id'], $q['options']);
        }

        $ins = $this->db->prepare(
            "INSERT INTO exam_attempts (user_id, exam_id, start_time, status, question_order, option_orders)
             VALUES (:uid, :eid, NOW(), 'in_progress', :qorder, :oorders)"
        );
        $ins->execute([
            'uid'     => $userId,
            'eid'     => $examId,
            'qorder'  => json_encode($questionOrder),
            'oorders' => json_encode($optionOrders),
        ]);

        $attemptId = (int)$this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM exam_attempts WHERE id = :id");
        $stmt->execute(['id' => $attemptId]);
        return $stmt->fetch();
    }

    /** 
     * Pure countdown: returns total seconds based purely on duration.
     * No timezone math. (Note: refreshing the page will reset the timer to full duration).
     */
    public function remainingSeconds(array $attempt, int $durationMinutes): int
    {
        return $durationMinutes * 60;
    }

    public function findAttempt(int $attemptId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, e.title AS exam_title, e.positive_marks, e.negative_marks,
                    e.passing_percentage, e.show_immediate_score, e.duration_minutes,
                    u.full_name, u.reg_number
             FROM exam_attempts a
             INNER JOIN exams e ON e.id = a.exam_id
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $attemptId]);
        return $stmt->fetch() ?: null;
    }

    /* -------------------------------------------------------------- *
     |  AJAX ANSWER PERSISTENCE (upsert, correctness resolved server-side) |
     * -------------------------------------------------------------- */
    public function saveAnswer(int $attemptId, int $questionId, ?int $optionId, ?bool $flagged = null): void
    {
        $attempt = $this->findAttempt($attemptId);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            throw new RuntimeException('This attempt is locked.');
        }

        $isCorrect = null;
        if ($optionId !== null) {
            $stmt = $this->db->prepare("SELECT is_correct FROM options WHERE id = :oid AND question_id = :qid LIMIT 1");
            $stmt->execute(['oid' => $optionId, 'qid' => $questionId]);
            $opt = $stmt->fetch();
            if (!$opt) {
                throw new RuntimeException('Invalid option for this question.');
            }
            $isCorrect = (int)$opt['is_correct'];
        }

        $flagValue = $flagged === null ? null : ($flagged ? 1 : 0);

        $sql = "INSERT INTO student_answers (attempt_id, question_id, selected_option_id, is_correct, is_flagged, updated_at)
                VALUES (:aid, :qid, :oid, :correct, :flag, NOW())
                ON DUPLICATE KEY UPDATE
                    selected_option_id = VALUES(selected_option_id),
                    is_correct         = VALUES(is_correct),
                    is_flagged         = IF(:flag2 IS NULL, is_flagged, VALUES(is_flagged)),
                    updated_at         = NOW()";
        $this->db->prepare($sql)->execute([
            'aid'     => $attemptId,
            'qid'     => $questionId,
            'oid'     => $optionId,
            'correct' => $isCorrect,
            'flag'    => $flagValue,
            'flag2'   => $flagValue,
        ]);
    }

    public function savedAnswers(int $attemptId): array
    {
        $stmt = $this->db->prepare("SELECT question_id, selected_option_id, is_flagged FROM student_answers WHERE attempt_id = :aid");
        $stmt->execute(['aid' => $attemptId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['question_id']] = [
                'option_id'  => $row['selected_option_id'] === null ? null : (int)$row['selected_option_id'],
                'is_flagged' => (bool)$row['is_flagged'],
            ];
        }
        return $out;
    }

    /* -------------------------------------------------------------- *
     |  GRADING + LOCK (idempotent: duplicate submits are rejected)     |
     * -------------------------------------------------------------- */
    public function submitAndCalculateScore(int $attemptId, string $status = 'completed'): array
    {
        $attempt = $this->findAttempt($attemptId);
        if (!$attempt) {
            throw new RuntimeException('Attempt not found.');
        }
        if ($attempt['status'] === 'completed') {
            return $this->scoreSummary($attempt); // duplicate submission guard
        }

        // ✅ CRITICAL FIX: Get total questions from the attempt's stored order ONLY.
        // This ensures it uses the "Questions to Answer" limit, NOT the total question bank.
        $questionOrder = json_decode((string)$attempt['question_order'], true) ?: [];
        $totalQuestions = count($questionOrder);

        $stmt = $this->db->prepare(
            "SELECT SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct,
                    SUM(CASE WHEN is_correct = 0 AND selected_option_id IS NOT NULL THEN 1 ELSE 0 END) AS incorrect
             FROM student_answers WHERE attempt_id = :aid"
        );
        $stmt->execute(['aid' => $attemptId]);
        $counts     = $stmt->fetch();
        $correct    = (int)($counts['correct'] ?? 0);
        $incorrect  = (int)($counts['incorrect'] ?? 0);
        
        // Unanswered is based on the LIMITED total, not the whole bank
        $unanswered = max(0, $totalQuestions - ($correct + $incorrect));

        $positive   = (float)$attempt['positive_marks'];
        $negative   = (float)$attempt['negative_marks'];
        $finalScore = ($correct * $positive) - ($incorrect * $negative);
        if ($finalScore < 0) {
            $finalScore = 0.0;
        }

        // ✅ Calculate percentage based on the LIMITED total questions
        $maxScore   = $totalQuestions * $positive;
        $percentage = $maxScore > 0 ? ($finalScore / $maxScore) * 100 : 0.0;
        $passed     = $percentage >= (float)$attempt['passing_percentage'] ? 1 : 0;

        // Award per-question marks for the scorecard log
        $this->db->prepare(
            "UPDATE student_answers
                SET marks_awarded = CASE
                        WHEN is_correct = 1 THEN :pos
                        WHEN is_correct = 0 AND selected_option_id IS NOT NULL THEN -:neg
                        ELSE 0
                    END
              WHERE attempt_id = :aid"
        )->execute(['pos' => $positive, 'neg' => $negative, 'aid' => $attemptId]);

        // Lock the attempt so it cannot be resubmitted or re-answered
        $this->db->prepare(
            "UPDATE exam_attempts
                SET status = :status, total_correct = :correct, total_incorrect = :incorrect,
                    total_unanswered = :unanswered, final_score = :score, percentage = :pct,
                    passed = :passed, submitted_at = NOW(), end_time = NOW()
              WHERE id = :id"
        )->execute([
            'status'     => $status,
            'correct'    => $correct,
            'incorrect'  => $incorrect,
            'unanswered' => $unanswered,
            'score'      => round($finalScore, 2),
            'pct'        => round($percentage, 2),
            'passed'     => $passed,
            'id'         => $attemptId,
        ]);

        return [
            'attempt_id'   => $attemptId,
            'correct'      => $correct,
            'incorrect'    => $incorrect,
            'unanswered'   => $unanswered,
            'final_score'  => round($finalScore, 2),
            'percentage'   => round($percentage, 2),
            'passed'       => (bool)$passed,
            'status'       => $status,
        ];
    }

    private function scoreSummary(array $attempt): array
    {
        return [
            'attempt_id'  => (int)$attempt['id'],
            'correct'     => (int)$attempt['total_correct'],
            'incorrect'   => (int)$attempt['total_incorrect'],
            'unanswered'  => (int)$attempt['total_unanswered'],
            'final_score' => (float)$attempt['final_score'],
            'percentage'  => (float)$attempt['percentage'],
            'passed'      => (bool)$attempt['passed'],
            'status'      => $attempt['status'],
        ];
    }

    /* -------------------------------------------------------------- *
     |  REPORTS                                                        |
     * -------------------------------------------------------------- */
    public function allResults(): array
    {
        $sql = "SELECT a.id AS attempt_id, u.reg_number, u.full_name, g.name AS group_name,
                       e.title AS exam_title, a.total_correct, a.total_incorrect, a.total_unanswered,
                       a.final_score, a.percentage, a.passed, a.status, a.submitted_at
                FROM exam_attempts a
                INNER JOIN users u  ON u.id = a.user_id
                INNER JOIN exams e  ON e.id = a.exam_id
                INNER JOIN groups g ON g.id = e.group_id
                ORDER BY a.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Question-by-question log for a single scorecard */
    /** Question-by-question log for a single scorecard (shows ONLY questions student saw) */
    public function questionLog(int $attemptId): array
    {
        // 1. Get the attempt details including the question_order
        $stmt = $this->db->prepare(
            "SELECT exam_id, question_order FROM exam_attempts WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();
        
        if (!$attempt) {
            return [];
        }

        // 2. Decode the question_order to get ONLY the questions student saw
        $questionOrder = json_decode((string)$attempt['question_order'], true) ?: [];
        
        if (empty($questionOrder)) {
            return [];
        }

        // 3. Build placeholders for the IN clause (all positional)
        $inPlaceholders = implode(',', array_fill(0, count($questionOrder), '?'));
        $fieldPlaceholders = implode(',', array_fill(0, count($questionOrder), '?'));
        
        // 4. Fetch ONLY the questions that were in the student's attempt
        $sql = "SELECT q.id AS question_id, q.question_text, q.diagram_path, q.explanation,
                       sa.selected_option_id, sa.is_correct, sa.marks_awarded, sa.is_flagged
                FROM questions q
                LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.attempt_id = ?
                WHERE q.id IN ($inPlaceholders)
                ORDER BY FIELD(q.id, $fieldPlaceholders)";
        
        $stmt = $this->db->prepare($sql);
        
        // Build the params array: [attempt_id, ...question_ids_for_IN, ...question_ids_for_FIELD]
        $params = array_merge([$attemptId], $questionOrder, $questionOrder);
        $stmt->execute($params);
        
        $rows = $stmt->fetchAll();

        // 5. Fetch options for each question
        $optStmt = $this->db->prepare("SELECT id, option_text, option_letter, is_correct FROM options WHERE question_id = ? ORDER BY id ASC");
        
        foreach ($rows as &$row) {
            $optStmt->execute([$row['question_id']]);
            $opts = $optStmt->fetchAll();
            $row['options'] = $opts;
            
            // Default to Unanswered if no selection was made
            $row['selected_text'] = 'Unanswered';
            $row['correct_text']  = '-';
            
            foreach ($opts as $o) {
                if ((int)$o['id'] === (int)$row['selected_option_id']) {
                    $row['selected_text'] = $o['option_letter'] . '. ' . $o['option_text'];
                }
                if ((int)$o['is_correct'] === 1) {
                    $row['correct_text'] = $o['option_letter'] . '. ' . $o['option_text'];
                }
            }
        }
        unset($row);
        
        return $rows;
    }
    /** Stream every scorecard as a CSV download (opens directly in Excel) */
    public function exportCsv(): void
    {
        $rows = $this->allResults();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt_results_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Attempt ID', 'Reg Number', 'Examinee', 'Exam Group', 'Exam Title',
            'Correct', 'Incorrect', 'Unanswered', 'Final Score', 'Percentage', 'Outcome', 'Status', 'Submitted At',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['attempt_id'], $r['reg_number'], $r['full_name'], $r['group_name'], $r['exam_title'],
                $r['total_correct'], $r['total_incorrect'], $r['total_unanswered'],
                $r['final_score'], $r['percentage'] . '%',
                $r['passed'] ? 'PASSED' : 'FAILED', $r['status'], $r['submitted_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function passRate(): int
    {
        $total  = (int)$this->db->query("SELECT COUNT(*) FROM exam_attempts WHERE status IN ('completed', 'timed_out')")->fetchColumn();
        $passed = (int)$this->db->query("SELECT COUNT(*) FROM exam_attempts WHERE status IN ('completed', 'timed_out') AND passed = 1")->fetchColumn();
        return $total > 0 ? (int)round(($passed / $total) * 100) : 0;
    }

    /** Calculate Item Analysis (Difficulty & Discrimination Indices) for an exam */
    public function getItemAnalysis(int $examId): array
    {
        // 1. Get all completed attempts for this exam, ordered by score
        $stmt = $this->db->prepare("SELECT id, percentage FROM exam_attempts WHERE exam_id = ? AND status IN ('completed', 'timed_out') ORDER BY percentage DESC");
        $stmt->execute([$examId]);
        $attempts = $stmt->fetchAll();
        
        if (count($attempts) < 2) return ['questions' => [], 'total_attempts' => count($attempts), 'message' => 'Need at least 2 attempts for analysis.'];

        $totalAttempts = count($attempts);
        $top27Count = max(1, (int)ceil($totalAttempts * 0.27));
        $bottom27Count = max(1, (int)ceil($totalAttempts * 0.27));
        
        $topIds = array_column(array_slice($attempts, 0, $top27Count), 'id');
        $bottomIds = array_column(array_slice($attempts, -$bottom27Count), 'id');
        
        $topPlaceholders = implode(',', array_fill(0, count($topIds), '?'));
        $bottomPlaceholders = implode(',', array_fill(0, count($bottomIds), '?'));
        
        // 2. Get question stats
        $sql = "SELECT q.id AS question_id, q.question_text,
                       COUNT(DISTINCT sa.attempt_id) AS total_attempts,
                       SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
                       SUM(CASE WHEN sa.attempt_id IN ($topPlaceholders) AND sa.is_correct = 1 THEN 1 ELSE 0 END) AS top_correct,
                       SUM(CASE WHEN sa.attempt_id IN ($bottomPlaceholders) AND sa.is_correct = 1 THEN 1 ELSE 0 END) AS bottom_correct
                FROM questions q
                LEFT JOIN student_answers sa ON q.id = sa.question_id
                WHERE q.exam_id = ?
                GROUP BY q.id
                ORDER BY q.id ASC";
                
        $stmt = $this->db->prepare($sql);
        $params = array_merge($topIds, $bottomIds, [$examId]);
        $stmt->execute($params);
        $questions = $stmt->fetchAll();
        
        // 3. Calculate indices
        foreach ($questions as &$q) {
            $q['difficulty'] = $q['total_attempts'] > 0 ? ($q['correct_count'] / $q['total_attempts']) : 0;
            $q['discrimination'] = ($q['top_correct'] - $q['bottom_correct']) / $top27Count;
        }
        unset($q);
        
        return ['questions' => $questions, 'total_attempts' => $totalAttempts, 'top_count' => $top27Count, 'bottom_count' => $bottom27Count];
    }
}