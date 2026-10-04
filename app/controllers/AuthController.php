<?php
/**
 * app/controllers/AuthController.php  —  Login / logout for admins & examinees
 */
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/models/User.php';

class AuthController
{
    private User $users;

    public function __construct() { $this->users = new User(); }

    /** GET index.php?page=login */
    public function showLogin(): void
    {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $reg  = trim((string)($_POST['reg_number'] ?? ''));
            $pass = (string)($_POST['password'] ?? '');

            if ($reg === '' || $pass === '') {
                $error = 'Please enter both your Reg/Matric Number and Password.';
            } else {
                $user = $this->users->attemptLogin($reg, $pass);
                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = $user;
                    redirect($user['role'] === 'admin' ? 'index.php?page=admin_dashboard' : 'index.php?page=student_dashboard');
                }
                $error = 'Invalid Reg/Matric Number or Password.';
            }
        }

        require APP_ROOT . '/app/views/auth/login.php';
    }

    /** GET index.php?page=logout */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('index.php?page=login'));
        exit;
    }
}
