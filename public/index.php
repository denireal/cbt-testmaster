<?php
/**
 * public/index.php  —  Front Controller / Central Router
 *
 * XAMPP URL: http://localhost/cbt_system/public/index.php?page=admin_dashboard
 * With the bundled .htaccess you can also use: http://localhost/cbt_system/public/admin_dashboard
 */
declare(strict_types=1);
date_default_timezone_set('Africa/Lagos');

session_start();

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';
require_once dirname(__DIR__) . '/app/controllers/ExamController.php';

$page = $_GET['page'] ?? 'home';

try {
    switch ($page) {
        /* ---------------------------- PUBLIC --------------------------- */
        case 'home':
            if (!isset($_SESSION['user'])) {
                redirect('index.php?page=login');
            }
            redirect($_SESSION['user']['role'] === 'admin'
                ? 'index.php?page=admin_dashboard'
                : 'index.php?page=student_dashboard');
            break;

        /* ----------------------------- AUTH ---------------------------- */
        case 'login':
            if (isset($_SESSION['user'])) {
                redirect('index.php?page=home');
            }
            (new AuthController())->showLogin();
            break;

        case 'logout':
            (new AuthController())->logout();
            break;

        /* ----------------------------- ADMIN --------------------------- */
        case 'admin_dashboard':
            (new AdminController())->dashboard();
            break;

        case 'admin_groups':
            (new AdminController())->groups();
            break;

        case 'admin_examinees':
            (new AdminController())->examinees();
            break;

        case 'admin_exams':
            (new AdminController())->exams();
            break;

        case 'admin_questions':
            (new AdminController())->questions();
            break;

        case 'admin_results':
            (new AdminController())->results();
            break;

        case 'admin_export_csv':
            (new AdminController())->exportCsv();
            break;

        /* --------------------------- EXAMINEE -------------------------- */
        case 'student_dashboard':
            (new ExamController())->dashboard();
            break;

        case 'exam':
            (new ExamController())->start();
            break;

        case 'result':
            (new ExamController())->result();
            break;

        // ✅ ADDED: AJAX Auto-save endpoint
        case 'save_answer':
            (new ExamController())->saveAnswer();
            break;

        // ✅ ADDED: Final exam submission endpoint
        case 'submit_exam':
            (new ExamController())->submitExam();
            break;

        case 'admin_analytics':
            (new AdminController())->analytics();
            break;

        /* ---------------------------- FALLBACK ------------------------- */
        default:
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body style="font-family:sans-serif;padding:40px">'
                . '<h1>404 — Route not found</h1>'
                . '<p>Unknown page: <code>' . e($page) . '</code></p>'
                . '<p><a href="' . url('index.php') . '">Return to the CBT portal</a></p>'
                . '</body></html>';
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Server error</title></head><body style="font-family:sans-serif;padding:40px">'
        . '<h1>500 — Application error</h1><pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>'
        . '</body></html>';
}