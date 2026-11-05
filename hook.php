<?php
/**
 * Work-event webhook (documented in openapi.yaml for APIMatic SDKs).
 * Zapier, GitHub Actions, Notion, Upwork or an email forwarder POST evidence
 * here with the workspace key; the agent reviews it right away.
 *
 *   POST hook.php   X-Konbit-Key: kb_...   {"source":"github","title":"...","text":"..."}
 *   GET  hook.php?event_id=12   X-Konbit-Key: kb_...
 */
declare(strict_types=1);

define('FA_NO_SESSION', 1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/paypal.php';
require __DIR__ . '/inc/engine.php';
require __DIR__ . '/inc/agent.php';

header('Content-Type: application/json; charset=utf-8');

function hook_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit('hook-ip:' . client_ip(), 60, 60)) {
    hook_out(['error' => 'rate_limited'], 429);
}
$key = (string) ($_SERVER['HTTP_X_KONBIT_KEY'] ?? '');
$user = $key !== '' && strlen($key) < 100 ? q('SELECT * FROM fa_users WHERE hook_key_hash = ? AND is_banned = 0', [hash('sha256', $key)])->fetch() : null;
if (!$user) {
    hook_out(['error' => 'unauthorized', 'message' => 'Send your workspace key in the X-Konbit-Key header.'], 401);
}
$uid = (int) $user['id'];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $ev = q('SELECT * FROM fa_events WHERE id = ? AND owner_id = ?', [(int) ($_GET['event_id'] ?? 0), $uid])->fetch();
        if (!$ev) {
            hook_out(['error' => 'not_found'], 404);
        }
        $plans = q('SELECT id, title, total_cents, decision, status FROM fa_plans WHERE event_id = ? AND owner_id = ?', [$ev['id'], $uid])->fetchAll();
        hook_out([
            'event_id' => (int) $ev['id'], 'status' => $ev['status'], 'risk' => $ev['risk'], 'summary' => $ev['agent_summary'],
            'plans' => array_map(fn ($p) => ['plan_id' => (int) $p['id'], 'title' => $p['title'], 'total_cents' => (int) $p['total_cents'], 'decision' => $p['decision'], 'status' => $p['status']], $plans),
        ]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        hook_out(['error' => 'method_not_allowed'], 405);
    }
    if (!rate_limit("event:$uid", 20, 3600)) {
        hook_out(['error' => 'rate_limited', 'message' => 'Up to 20 events per hour.'], 429);
    }
    $raw = (string) file_get_contents('php://input');
    if (strlen($raw) > 50000) {
        hook_out(['error' => 'too_large'], 413);
    }
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        hook_out(['error' => 'invalid_json'], 400);
    }
    $ev = event_create($uid, (string) ($in['source'] ?? 'api'), (string) ($in['title'] ?? ''), (string) ($in['text'] ?? ''));
    $review = event_analyze($uid, (int) $ev['id']);
    $plans = $review['plans'] ? q('SELECT id, title, total_cents, decision, status FROM fa_plans WHERE owner_id = ? AND id IN (' . implode(',', array_map('intval', $review['plans'])) . ')', [$uid])->fetchAll() : [];
    hook_out([
        'event_id' => $review['event_id'], 'status' => $review['status'], 'risk' => $review['risk'], 'summary' => $review['summary'],
        'plans' => array_map(fn ($p) => ['plan_id' => (int) $p['id'], 'title' => $p['title'], 'total_cents' => (int) $p['total_cents'], 'decision' => $p['decision'], 'status' => $p['status']], $plans),
    ], 201);
} catch (AppError $e) {
    hook_out(['error' => 'rejected', 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Konbit hook: ' . $e->getMessage());
    hook_out(['error' => 'server_error'], 500);
}
