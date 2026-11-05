<?php
// Local dev router for `php -S 127.0.0.1:8099 router.php` (not used on Apache).
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(inc|vendor)/#', $path) || preg_match('/\.(sql|md|lock|log)$/', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
