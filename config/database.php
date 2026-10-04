<?php
/**
 * config/database.php
 * PDO connection to MySQL (XAMPP / WAMP / LAMP friendly) + global helpers.
 *
 * XAMPP DEFAULTS: host = 127.0.0.1, user = root, password = (empty)
 * Override with environment variables if your setup differs.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ *
 |  DATABASE CREDENTIALS (edit these 4 lines for your XAMPP install)  |
 * ------------------------------------------------------------------ */
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'cbt_system_db');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------ *
 |  PATHS + AUTO BASE URL (works at http://localhost/cbt_system/public/) |
 * ------------------------------------------------------------------ */
define('APP_ROOT',    dirname(__DIR__));                       // .../cbt_system
define('ASSETS_PATH', APP_ROOT . '/public/assets/');
define('UPLOAD_PATH', APP_ROOT . '/public/uploads/diagrams/');

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_URL',  rtrim($scriptDir, '/'));                   // '' or '/cbt_system/public'
define('UPLOAD_URL', BASE_URL . '/uploads/diagrams/');
define('ALLOWED_IMG_EXT', ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg']);
define('MAX_IMG_BYTES', 3 * 1024 * 1024);                      // 3 MB

/* ------------------------------------------------------------------ *
 |  PDO SINGLETON                                                      |
 * ------------------------------------------------------------------ */
class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // ✅ FORCE MYSQL TO USE AFRICA/LAGOS TIMEZONE (UTC+1)
            self::$pdo->exec("SET time_zone = '+01:00'");
            
        } catch (PDOException $e) {
            http_response_code(500);
            die(
                '<h2 style="font-family:sans-serif">Database connection failed</h2>'
                . '<p style="font-family:monospace">' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
                . '<p style="font-family:sans-serif">XAMPP checklist: (1) Apache + MySQL started in the XAMPP Control Panel, '
                . '(2) <code>schema.sql</code> imported via phpMyAdmin, (3) credentials in <code>config/database.php</code>.</p>'
            );
        }
        return self::$pdo;
    }
}

/* ------------------------------------------------------------------ *
 |  GLOBAL HELPERS                                                     |
 * ------------------------------------------------------------------ */
function db(): PDO { return Database::getConnection(); }

/** Escape output for HTML */
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/** Build a URL relative to the detected base */
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function pull_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(string $role = null): array
{
    if (!isset($_SESSION['user'])) {
        redirect('index.php?page=login');
    }
    if ($role !== null && $_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        die('403 - You are not authorised to view this section.');
    }
    return $_SESSION['user'];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_out(['status' => 'error', 'message' => 'Invalid CSRF token.'], 419);
    }
}
