<?php
/**
 * Konbit bootstrap: config, PDO, schema install, session, CSRF, auth,
 * rate limiting, security headers and small helpers.
 */
declare(strict_types=1);

defined('FA') || define('FA', 1);

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'bootstrap.php') {
    http_response_code(404);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Our own log inside inc/ (denied to the web), so errors on shared hosting are findable.
ini_set('error_log', __DIR__ . '/php-errors.log');
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Konbit fatal on ' . ($_SERVER['REQUEST_URI'] ?? 'cli') . ': ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (!headers_sent() && (str_contains($script, 'api.php') || str_contains($script, 'hook.php'))) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo '{"ok":false,"error":"Server error. Please try again."}';
        }
    }
});
date_default_timezone_set('UTC');

$GLOBALS['FA_CFG'] = (static function (): array {
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        http_response_code(503);
        exit('Konbit is not configured yet. Copy inc/config.sample.php to inc/config.php.');
    }
    return require $file;
})();

const FA_ASSET_VER = '3';
const FA_CREW_COLORS = ['#00209F', '#D21034', '#E0A800', '#0F9D58', '#00838F', '#E8590C', '#1565C0', '#AD1457'];

/** User-facing error: the message is safe to show. Anything else becomes a generic error. */
final class AppError extends RuntimeException
{
}

function cfg(string $path, $default = null)
{
    $node = $GLOBALS['FA_CFG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

/* ---------------------------------------------------------------- DB */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db.host'), (int) cfg('db.port', 3306), cfg('db.name'));
    try {
        $pdo = new PDO($dsn, (string) cfg('db.user'), (string) cfg('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Konbit DB connect failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable.');
    }
    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    install_schema($pdo);
    return $pdo;
}

/** Creates the tables once (idempotent); a lock file skips the work afterwards. */
function install_schema(PDO $pdo): void
{
    $lock = __DIR__ . '/schema-v1.lock';
    if (is_file($lock)) {
        return;
    }
    $sql = (string) file_get_contents(dirname(__DIR__) . '/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
    @file_put_contents($lock, gmdate('c'));
}

/** Runs $fn inside a transaction; retries on deadlock. */
function tx(callable $fn)
{
    $pdo = db();
    for ($attempt = 1; ; $attempt++) {
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $deadlock = $e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1213, 1205], true);
            if ($deadlock && $attempt < 3) {
                usleep(50000 * $attempt);
                continue;
            }
            throw $e;
        }
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ------------------------------------------------------------- money */

function money(int $cents, string $currency = 'USD'): string
{
    $sign = $cents < 0 ? '−' : '';
    return $sign . ($currency === 'USD' ? '$' : $currency . ' ') . number_format(abs($cents) / 100, 2);
}

/** Parses "12", "12.5" or "12.50" dollars into cents; null when invalid. */
function parse_cents($raw): ?int
{
    $s = trim((string) $raw);
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    return (int) round(((float) $s) * 100);
}

/* ------------------------------------------------------------ request */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/** URL path of the site folder, e.g. "/freelanceragent" (empty at the web root). */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    return rtrim((string) cfg('app.base_url'), '/') . '/' . ltrim($path, '/');
}

function asset(string $file): string
{
    return url('assets/' . $file) . '?v=' . FA_ASSET_VER;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/** Per-request nonce that lets AG Grid inject its theme <style> under the CSP. */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // The app page hosts AG Grid and Bryntum Scheduler, which set element styles at runtime.
    $appPage = defined('FA_APP_PAGE');
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "style-src 'self'" . ($appPage ? " 'unsafe-inline'" : '') . ' https://fonts.googleapis.com; '
        . "font-src 'self' data: https://fonts.gstatic.com; "
        . "script-src 'self'" . ($appPage ? " 'unsafe-eval'" : '') . "; connect-src 'self'; "
        . "form-action 'self' https://www.sandbox.paypal.com https://www.paypal.com; "
        . "frame-ancestors 'none'; base-uri 'self'; object-src 'none'"
    );
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
}

/* ------------------------------------------------------------ session */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('KONBITSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (base_path() === '' ? '' : base_path()) . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Re-opens a released session just long enough to store $values (null removes a key). */
function session_store(array $values): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    foreach ($values as $key => $value) {
        if ($value === null) {
            unset($_SESSION[$key]);
        } else {
            $_SESSION[$key] = $value;
        }
    }
    session_write_close();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = (string) ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function flash(string $msg, string $kind = 'info'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'kind' => $kind];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* --------------------------------------------------------------- auth */

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid > 0) {
        $u = q('SELECT * FROM fa_users WHERE id = ?', [$uid])->fetch();
        if ($u && !(int) $u['is_banned']) {
            $cache = $u;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $cache;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function require_login_page(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

/* --------------------------------------------------------- rate limit */

/** Fixed-window counter. Returns false once $max hits are used in the window. */
function rate_limit(string $bucket, int $max, int $windowSec): bool
{
    $window = intdiv(time(), $windowSec) * $windowSec;
    $key = sha1($bucket);
    q('INSERT INTO fa_rate_limits (bucket, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', [$key, $window]);
    $hits = (int) q('SELECT hits FROM fa_rate_limits WHERE bucket = ? AND window_start = ?', [$key, $window])->fetchColumn();
    if (random_int(1, 500) === 1) {
        q('DELETE FROM fa_rate_limits WHERE window_start < ?', [time() - 7200]);
    }
    return $hits <= $max;
}

function agent_log(int $uid, string $kind, string $tool, array $detail): void
{
    q(
        'INSERT INTO fa_agent_log (owner_id, kind, tool, detail) VALUES (?, ?, ?, ?)',
        [$uid, $kind, $tool, json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
    );
}

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    if (!defined('FA_NO_SESSION')) {
        start_session();
    }
}
