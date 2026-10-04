<?php
/**
 * public/process_ajax.php  —  Asynchronous AJAX handler (jQuery/AJAX endpoints)
 *
 * Endpoints (POST unless noted):
 *   ?action=save_answer      {attempt_id, question_id, option_id|null, flagged?}
 *   ?action=toggle_flag      {attempt_id, question_id, flagged}
 *   ?action=time_left        {attempt_id}          -> seconds remaining (server clock)
 *   ?action=submit_exam      {attempt_id}          -> graded summary, locks attempt
 *
 * All responses are JSON: {"status":"success|error", ...}
 */
declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/models/User.php';
require_once dirname(__DIR__) . '/app/models/Group.php';
require_once dirname(__DIR__) . '/app/models/Exam.php';
require_once dirname(__DIR__) . '/app/models/Question.php';
require_once dirname(__DIR__) . '/app/models/Result.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* ------------------------------ AUTH GATE ------------------------------ */
if (!isset($_SESSION['user'])) {
    json_out(['status' => 'error', 'message' => 'Session expired. Please log in again.'], 401);
}
verify_csrf();

$user    = $_SESSION['user'];
$results = new Result();

try {
    $attemptId  = (int)($input['attempt_id'] ?? 0);
    $attempt    = $attemptId ? $results->findAttempt($attemptId) : null;

    // Examinees may only touch their own attempt
    if ($attempt && $user['role'] === 'examinee' && (int)$attempt['user_id'] !== (int)$user['id']) {
        json_out(['status' => 'error', 'message' => 'Forbidden attempt.'], 403);
    }

    switch ($action) {

        /* ---------------- SAVE / CHANGE AN ANSWER ---------------- */
        case 'save_answer':
            $questionId = (int)($input['question_id'] ?? 0);
            $optionId   = isset($input['option_id']) && $input['option_id'] !== '' && $input['option_id'] !== null
                ? (int)$input['option_id'] : null;

            $results->saveAnswer($attemptId, $questionId, $optionId);
            json_out(['status' => 'success', 'message' => 'Answer saved', 'saved_at' => date('H:i:s')]);
            break;

        /* ------------------- FLAG FOR REVIEW --------------------- */
        case 'toggle_flag':
            $questionId = (int)($input['question_id'] ?? 0);
            $flagged    = !empty($input['flagged']);

            // Flag without changing the stored option
            $saved = $results->savedAnswers($attemptId);
            $results->saveAnswer($attemptId, $questionId, $saved[$questionId]['option_id'] ?? null, $flagged);
            json_out(['status' => 'success', 'flagged' => $flagged]);
            break;

        /* ------------- SERVER-SIDE REMAINING TIME ---------------- */
        case 'time_left':
            if (!$attempt) {
                json_out(['status' => 'error', 'message' => 'Attempt not found.'], 404);
            }
            $seconds = $results->remainingSeconds($attempt, (int)$attempt['duration_minutes']);

            // Auto-lock on the server the moment time expires
            if ($seconds <= 0 && $attempt['status'] === 'in_progress') {
                $summary = $results->submitAndCalculateScore($attemptId, 'timed_out');
                json_out(['status' => 'success', 'expired' => true, 'remaining' => 0, 'summary' => $summary]);
            }
            json_out(['status' => 'success', 'remaining' => $seconds, 'expired' => false]);
            break;

        /* ---------------- FINAL SUBMIT + GRADING ----------------- */
        case 'submit_exam':
            if (!$attempt) {
                json_out(['status' => 'error', 'message' => 'Attempt not found.'], 404);
            }
            if ($attempt['status'] === 'completed') {
                json_out([
                    'status'      => 'success',
                    'already'     => true,
                    'attempt_id'  => $attemptId,
                    'redirect'    => url('index.php?page=result&attempt_id=' . $attemptId),
                ]);
            }
            $summary = $results->submitAndCalculateScore($attemptId, 'completed');
            json_out([
                'status'     => 'success',
                'attempt_id' => $attemptId,
                'summary'    => $summary,
                'redirect'   => url('index.php?page=result&attempt_id=' . $attemptId),
            ]);
            break;

        default:
            json_out(['status' => 'error', 'message' => 'Unknown AJAX action: ' . $action], 400);
    }
} catch (Throwable $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
