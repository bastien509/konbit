<?php
/**
 * JSON API for the app. Every action needs a session; every POST needs the
 * CSRF token. Ownership is enforced in each query (owner_id = the user).
 */
declare(strict_types=1);

define('FA_NO_SESSION', 1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/paypal.php';
require __DIR__ . '/inc/engine.php';
require __DIR__ . '/inc/agent.php';
require __DIR__ . '/inc/demo.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $code < 400] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

start_session();
$me = current_user();
if (!$me) {
    out(['error' => 'Please sign in again.', 'login' => true], 401);
}
$uid = (int) $me['id'];
$action = (string) ($_GET['a'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isPost && !csrf_valid()) {
    out(['error' => 'Your session expired. Reload the page.'], 419);
}
// Release the session lock so slow agent calls don't block polling.
$csrf = csrf_token();
session_write_close();

if (!rate_limit("api:$uid", 240, 60)) {
    out(['error' => 'Too many requests. Slow down a little.'], 429);
}

$in = $isPost ? (json_decode((string) file_get_contents('php://input'), true) ?: $_POST) : $_GET;
$int = fn (string $k) => (int) ($in[$k] ?? 0);
$str = fn (string $k, int $max = 200) => trim(mb_substr((string) ($in[$k] ?? ''), 0, $max));

function plans_for(int $uid, int $limit = 40): array
{
    $plans = q("SELECT p.*, pr.name AS project FROM fa_plans p JOIN fa_projects pr ON pr.id = p.project_id WHERE p.owner_id = ? ORDER BY p.id DESC LIMIT $limit", [$uid])->fetchAll();
    if (!$plans) {
        return [];
    }
    $ids = implode(',', array_map(fn ($p) => (int) $p['id'], $plans));
    $items = [];
    foreach (q("SELECT i.*, c.name FROM fa_plan_items i JOIN fa_crew c ON c.id = i.crew_id WHERE i.plan_id IN ($ids) ORDER BY i.id")->fetchAll() as $i) {
        $items[(int) $i['plan_id']][] = [
            'crew_id' => (int) $i['crew_id'], 'name' => $i['name'], 'receiver' => mask_email($i['receiver']),
            'amount_cents' => (int) $i['amount_cents'], 'status' => $i['status'], 'paypal_status' => $i['paypal_status'], 'note' => $i['note'],
        ];
    }
    return array_map(fn ($p) => [
        'id' => (int) $p['id'], 'project_id' => (int) $p['project_id'], 'project' => $p['project'], 'kind' => $p['kind'],
        'created_by' => $p['created_by'], 'title' => $p['title'], 'reason' => $p['reason'], 'quote' => $p['evidence_quote'],
        'total_cents' => (int) $p['total_cents'], 'decision' => $p['decision'], 'status' => $p['status'], 'pay_on' => $p['pay_on'],
        'event_id' => $p['event_id'] ? (int) $p['event_id'] : null, 'batch_id' => $p['paypal_batch_id'], 'approved_by' => $p['approved_by'],
        'checks' => json_decode($p['policy_json'], true) ?: [], 'items' => $items[(int) $p['id']] ?? [],
        'created_at' => $p['created_at'] . 'Z', 'approved_at' => $p['approved_at'] ? $p['approved_at'] . 'Z' : null,
    ], $plans);
}

try {
    switch ($action) {
        case 'state':
            engine_sweep($uid);
            $ws = workspace_snapshot($uid);
            $events = q('SELECT id, source, title, body, status, agent_summary, risk, received_at FROM fa_events WHERE owner_id = ? ORDER BY id DESC LIMIT 25', [$uid])->fetchAll();
            $requests = q('SELECT r.id, r.pay_token, r.memo, r.amount_cents, r.status, r.payer_email, r.created_at, r.completed_at, p.name AS project FROM fa_client_payments r JOIN fa_projects p ON p.id = r.project_id WHERE r.owner_id = ? ORDER BY r.id DESC LIMIT 25', [$uid])->fetchAll();
            out([
                'me'        => ['name' => $me['name'], 'business' => $me['business_name'], 'hook_hint' => $me['hook_key_hint']],
                'csrf'      => $csrf,
                'workspace' => $ws,
                'plans'     => plans_for($uid),
                'events'    => array_map(fn ($e) => ['id' => (int) $e['id'], 'source' => $e['source'], 'title' => $e['title'], 'body' => $e['body'], 'status' => $e['status'], 'summary' => $e['agent_summary'], 'risk' => $e['risk'], 'at' => $e['received_at'] . 'Z'], $events),
                'requests'  => array_map(fn ($r) => ['id' => (int) $r['id'], 'url' => abs_url('pay.php?r=' . $r['pay_token']), 'memo' => $r['memo'], 'amount_cents' => (int) $r['amount_cents'], 'status' => $r['status'], 'payer' => $r['payer_email'] ? mask_email($r['payer_email']) : null, 'project' => $r['project'], 'at' => $r['created_at'] . 'Z', 'paid_at' => $r['completed_at'] ? $r['completed_at'] . 'Z' : null], $requests),
                'samples'   => array_map(fn ($k, $s) => ['key' => $k, 'title' => $s['title']], array_keys(SAMPLE_EVIDENCE), SAMPLE_EVIDENCE),
                'paypal'    => ['ready' => paypal_ready(), 'mode' => paypal_mode()],
                'agent'     => agent_ready(),
            ]);

        case 'demo_seed':
            demo_seed($uid);
            out(['message' => 'Demo workspace loaded.']);

        case 'crew_add':
            $name = $str('name', 80);
            $email = strtolower($str('paypal_email', 190));
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new AppError('Add a name and a valid PayPal email.');
            }
            $n = (int) q('SELECT COUNT(*) FROM fa_crew WHERE owner_id = ?', [$uid])->fetchColumn();
            if ($n >= 30) {
                throw new AppError('A crew can have up to 30 people.');
            }
            q('INSERT INTO fa_crew (owner_id, name, role, paypal_email, color) VALUES (?, ?, ?, ?, ?)', [$uid, $name, $str('role', 60), $email, FA_CREW_COLORS[$n % count(FA_CREW_COLORS)]]);
            agent_log($uid, 'owner', 'crew_added', ['name' => $name, 'email' => mask_email($email)]);
            out(['message' => $name . ' joined your crew. New payout emails wait 24 h before auto-pay.']);

        case 'crew_update':
            $c = crew_owned($uid, $int('id'));
            $email = strtolower($str('paypal_email', 190));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new AppError('Enter a valid PayPal email.');
            }
            $changed = $email !== strtolower($c['paypal_email']);
            q('UPDATE fa_crew SET name = ?, role = ?, paypal_email = ?, email_changed_at = IF(?, NOW(), email_changed_at) WHERE id = ? AND owner_id = ?', [$str('name', 80) ?: $c['name'], $str('role', 60), $email, $changed ? 1 : 0, $c['id'], $uid]);
            if ($changed) {
                // Unpaid plans follow the new address, and a scheduled one now waits for a human tap.
                q("UPDATE fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id SET i.receiver = ? WHERE i.crew_id = ? AND p.status IN ('needs_approval','scheduled')", [$email, $c['id']]);
                q("UPDATE fa_plans p JOIN fa_plan_items i ON i.plan_id = p.id SET p.status = 'needs_approval', p.decision = 'ask', p.approved_by = NULL WHERE i.crew_id = ? AND p.status = 'scheduled'", [$c['id']]);
                agent_log($uid, 'owner', 'crew_email_changed', ['crew' => (int) $c['id'], 'email' => mask_email($email)]);
            }
            out(['message' => $changed ? 'Saved. The new PayPal email needs your tap for 24 hours.' : 'Saved.']);

        case 'crew_remove':
            $c = crew_owned($uid, $int('id'));
            q("UPDATE fa_crew SET status = 'removed' WHERE id = ? AND owner_id = ?", [$c['id'], $uid]);
            agent_log($uid, 'owner', 'crew_removed', ['crew' => (int) $c['id']]);
            out(['message' => $c['name'] . ' was removed. They can no longer be paid.']);

        case 'project_add':
            $name = $str('name', 120);
            $reserve = $int('reserve_pct');
            if ($name === '' || $reserve < 0 || $reserve > 50) {
                throw new AppError('Name the project and pick a reserve between 0 and 50%.');
            }
            q('INSERT INTO fa_projects (owner_id, name, client_name, reserve_pct) VALUES (?, ?, ?, ?)', [$uid, $name, $str('client', 120), $reserve]);
            out(['message' => 'Project created.']);

        case 'milestone_add':
            $p = project_owned($uid, $int('project_id'));
            $c = crew_owned($uid, $int('crew_id'));
            $cents = parse_cents($in['amount'] ?? '');
            $title = $str('title', 140);
            if ($title === '' || $cents === null || $cents < 100 || $cents > 1000000) {
                throw new AppError('Give the milestone a title and a fee between $1 and $10,000.');
            }
            q('INSERT INTO fa_milestones (project_id, crew_id, title, amount_cents) VALUES (?, ?, ?, ?)', [$p['id'], $c['id'], $title, $cents]);
            out(['message' => 'Milestone added.']);

        case 'split_set':
            $p = project_owned($uid, $int('project_id'));
            $c = crew_owned($uid, $int('crew_id'));
            $pct = (float) ($in['share_pct'] ?? 0);
            $bp = (int) round($pct * 100);
            $other = (int) q('SELECT COALESCE(SUM(share_bp), 0) FROM fa_splits WHERE project_id = ? AND crew_id <> ?', [$p['id'], $c['id']])->fetchColumn();
            if ($bp < 0 || $other + $bp > 10000) {
                throw new AppError('Shares on a project cannot add up to more than 100%.');
            }
            if ($bp === 0) {
                q('DELETE FROM fa_splits WHERE project_id = ? AND crew_id = ?', [$p['id'], $c['id']]);
            } else {
                q('INSERT INTO fa_splits (project_id, crew_id, share_bp) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE share_bp = VALUES(share_bp)', [$p['id'], $c['id'], $bp]);
            }
            out(['message' => 'Split saved.']);

        case 'milestone_pay':
            // The owner pays a milestone by hand: same policy, the tap is the approval.
            $m = q('SELECT m.* FROM fa_milestones m JOIN fa_projects p ON p.id = m.project_id WHERE m.id = ? AND p.owner_id = ?', [$int('id'), $uid])->fetch();
            if (!$m) {
                throw new AppError('Milestone not found.');
            }
            $crewName = (string) q('SELECT name FROM fa_crew WHERE id = ?', [$m['crew_id']])->fetchColumn();
            $r = plan_create($uid, project_owned($uid, (int) $m['project_id']), [['crew_id' => (int) $m['crew_id'], 'amount_cents' => (int) $m['amount_cents'], 'milestone_id' => (int) $m['id'], 'note' => 'Milestone: ' . $m['title']]], [
                'kind' => 'milestone', 'created_by' => 'owner', 'title' => $crewName . ' · ' . $m['title'], 'reason' => 'Paid by hand from the project screen.',
            ]);
            out(['result' => $r, 'message' => $r['message'] ?? ($r['decision'] === 'block' ? 'Blocked by policy.' : 'Plan created.')]);

        case 'mandate_sign':
            $r = mandate_create(
                $uid,
                parse_cents($in['auto_below'] ?? '') ?? -1,
                parse_cents($in['max_payout'] ?? '') ?? -1,
                parse_cents($in['max_recipient_month'] ?? '') ?? -1,
                parse_cents($in['month_cap'] ?? '') ?? -1,
                $int('days')
            );
            out(['mandate' => $r, 'message' => 'Mandate signed. The agent now works inside these limits.']);

        case 'mandate_state':
            $s = $str('status', 10);
            if (!in_array($s, ['paused', 'active', 'revoked'], true)) {
                throw new AppError('Unknown mandate state.');
            }
            mandate_set_status($uid, $s);
            out(['message' => ['paused' => 'Mandate paused. Every payout now needs your tap.', 'active' => 'Mandate resumed.', 'revoked' => 'Mandate revoked.'][$s]]);

        case 'request_create':
            $p = project_owned($uid, $int('project_id'));
            $cents = parse_cents($in['amount'] ?? '');
            $r = payment_request_create($uid, $p, $cents ?? 0, $str('memo', 200));
            out(['url' => $r['url'], 'message' => 'Payment link ready. Send it to your client.']);

        case 'event_create':
            if (!rate_limit("event:$uid", 20, 3600)) {
                throw new AppError('That is a lot of evidence for one hour. Try again later.');
            }
            $sample = $str('sample', 20);
            if ($sample !== '' && isset(SAMPLE_EVIDENCE[$sample])) {
                $src = $sample === 'phishing' ? 'email' : $sample;
                $ev = event_create($uid, $src, SAMPLE_EVIDENCE[$sample]['title'], SAMPLE_EVIDENCE[$sample]['body']);
            } else {
                $ev = event_create($uid, $str('source', 20), $str('title', 160), (string) ($in['body'] ?? ''));
            }
            $r = event_analyze($uid, (int) $ev['id']);
            out(['review' => $r]);

        case 'event_analyze':
            if (!rate_limit("event:$uid", 20, 3600)) {
                throw new AppError('Too many reviews this hour.');
            }
            out(['review' => event_analyze($uid, $int('id'))]);

        case 'plan_approve':
            $r = plan_execute($uid, $int('id'), 'owner');
            out(['result' => $r, 'message' => $r['message'] ?? 'Done.']);

        case 'plan_reject':
            plan_reject($uid, $int('id'));
            out(['message' => 'Plan rejected. Nothing was paid.']);

        case 'plan_reschedule':
            out(['result' => plan_reschedule($uid, $int('id'), $str('date', 10)), 'message' => 'Moved.']);

        case 'plan_sync':
            $p = plan_owned($uid, $int('id'));
            plan_sync($p);
            out(['message' => 'Status refreshed from PayPal.']);

        case 'chat':
            $text = $str('text', AGENT_MAX_INPUT);
            if ($text === '') {
                throw new AppError('Type a message.');
            }
            if (!agent_ready()) {
                throw new AppError('The AI agent is not configured yet.');
            }
            if (!rate_limit("chat:$uid", 30, 3600)) {
                throw new AppError('You reached the hourly chat limit.');
            }
            start_session();
            out(agent_chat($uid, $text));

        case 'chat_reset':
            session_store(['agent_history' => null]);
            out(['message' => 'New conversation.']);

        case 'hook_key':
            $key = 'kb_' . bin2hex(random_bytes(20));
            q('UPDATE fa_users SET hook_key_hash = ?, hook_key_hint = ? WHERE id = ?', [hash('sha256', $key), substr($key, 0, 7) . '…', $uid]);
            agent_log($uid, 'owner', 'hook_key_rotated', []);
            out(['key' => $key, 'message' => 'New key created. Copy it now: it is shown only once.']);

        case 'grid':
            $ledger = q('SELECT l.id, l.created_at, l.account, l.type, l.amount_cents, l.note, p.name AS project, c.name AS crew, l.plan_id FROM fa_ledger l JOIN fa_projects p ON p.id = l.project_id LEFT JOIN fa_crew c ON c.id = l.crew_id WHERE l.owner_id = ? ORDER BY l.id DESC LIMIT 500', [$uid])->fetchAll();
            $items = q(
                'SELECT i.id, i.plan_id, i.amount_cents, i.status, i.paypal_status, i.paypal_item_id, i.receiver, i.updated_at, c.name AS crew, pl.title, pl.kind, pl.created_by, pl.approved_by, pl.status AS plan_status, pl.paypal_batch_id, pr.name AS project
                 FROM fa_plan_items i JOIN fa_plans pl ON pl.id = i.plan_id JOIN fa_crew c ON c.id = i.crew_id JOIN fa_projects pr ON pr.id = pl.project_id
                 WHERE pl.owner_id = ? ORDER BY i.id DESC LIMIT 500',
                [$uid]
            )->fetchAll();
            $log = q('SELECT id, created_at, kind, tool, detail FROM fa_agent_log WHERE owner_id = ? ORDER BY id DESC LIMIT 500', [$uid])->fetchAll();
            out([
                'ledger' => array_map(fn ($r) => ['id' => (int) $r['id'], 'at' => $r['created_at'] . 'Z', 'account' => $r['account'], 'type' => $r['type'], 'amount' => (int) $r['amount_cents'] / 100, 'project' => $r['project'], 'crew' => $r['crew'], 'plan' => $r['plan_id'] ? (int) $r['plan_id'] : null, 'note' => $r['note']], $ledger),
                'items'  => array_map(fn ($r) => ['id' => (int) $r['id'], 'plan' => (int) $r['plan_id'], 'at' => $r['updated_at'] . 'Z', 'crew' => $r['crew'], 'project' => $r['project'], 'title' => $r['title'], 'kind' => $r['kind'], 'by' => $r['created_by'], 'approved_by' => $r['approved_by'], 'amount' => (int) $r['amount_cents'] / 100, 'status' => $r['status'], 'paypal_status' => $r['paypal_status'], 'receiver' => mask_email($r['receiver']), 'batch' => $r['paypal_batch_id'], 'item_id' => $r['paypal_item_id']], $items),
                'log'    => array_map(fn ($r) => ['id' => (int) $r['id'], 'at' => $r['created_at'] . 'Z', 'kind' => $r['kind'], 'tool' => $r['tool'], 'detail' => $r['detail']], $log),
            ]);

        case 'schedule':
            // Bryntum Scheduler: crew as resources, payouts and due milestones as events.
            $crew = q("SELECT id, name, role, color FROM fa_crew WHERE owner_id = ? AND status = 'active' ORDER BY id", [$uid])->fetchAll();
            $events = [];
            foreach (q(
                "SELECT i.id, i.crew_id, i.amount_cents, i.status AS istatus, p.id AS plan_id, p.title, p.status, p.pay_on, p.created_at, p.approved_at, p.kind, pr.name AS project
                 FROM fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id JOIN fa_projects pr ON pr.id = p.project_id
                 WHERE p.owner_id = ? AND p.status <> 'rejected' AND p.created_at > NOW() - INTERVAL 60 DAY",
                [$uid]
            )->fetchAll() as $r) {
                $start = $r['pay_on'] ? $r['pay_on'] . 'T09:00:00Z' : ($r['approved_at'] ?: $r['created_at']) . 'Z';
                $movable = in_array($r['status'], ['needs_approval', 'scheduled'], true);
                $events[] = [
                    'id' => 'i' . $r['id'], 'resourceId' => (int) $r['crew_id'], 'name' => money((int) $r['amount_cents']) . ' · ' . $r['title'],
                    'startDate' => str_replace(' ', 'T', $start), 'duration' => 6, 'durationUnit' => 'h',
                    'planId' => (int) $r['plan_id'], 'status' => $r['status'], 'itemStatus' => $r['istatus'], 'project' => $r['project'], 'kind' => $r['kind'],
                    'draggable' => $movable, 'resizable' => false, 'cls' => 'ev-' . $r['status'],
                ];
            }
            foreach (q(
                "SELECT m.id, m.crew_id, m.title, m.amount_cents, m.due_on, m.status, pr.name AS project FROM fa_milestones m JOIN fa_projects pr ON pr.id = m.project_id
                 WHERE pr.owner_id = ? AND m.due_on IS NOT NULL AND m.status = 'open'",
                [$uid]
            )->fetchAll() as $m) {
                $events[] = [
                    'id' => 'm' . $m['id'], 'resourceId' => (int) $m['crew_id'], 'name' => 'Due: ' . $m['title'] . ' (' . money((int) $m['amount_cents']) . ')',
                    'startDate' => $m['due_on'] . 'T00:00:00Z', 'duration' => 1, 'durationUnit' => 'd', 'status' => 'due', 'project' => $m['project'],
                    'draggable' => false, 'resizable' => false, 'cls' => 'ev-due',
                ];
            }
            out(['resources' => array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'role' => $c['role'], 'eventColor' => $c['color']], $crew), 'events' => $events]);
    }
    out(['error' => 'Unknown action.'], 404);
} catch (AppError $e) {
    out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Konbit API ' . $action . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    out(['error' => 'Something went wrong. Please try again.'], 500);
}
