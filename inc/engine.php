<?php
/**
 * The money engine: ledger, mandate, policy checks, payout plans,
 * PayPal Payouts execution and status sync.
 *
 * Nothing here trusts the AI. The agent can only *propose* a plan with crew
 * ids and amounts; every rule below runs on the server, again at execution
 * time, and funds are held with an atomic UPDATE before PayPal is called.
 */
declare(strict_types=1);

defined('FA') || exit;

const MANDATE_DAYS = [7, 30, 90];
const EMAIL_CHANGE_HOLD_H = 24;     // a changed payout email waits this long before auto-pay
const PLAN_SYNC_EVERY_SEC = 15;

/* ------------------------------------------------------------- ledger */

function ledger(PDO $pdo, int $owner, int $project, string $account, string $type, int $cents, string $idem, array $extra = []): void
{
    $pdo->prepare(
        'INSERT IGNORE INTO fa_ledger (owner_id, project_id, account, type, amount_cents, crew_id, plan_id, note, idem_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$owner, $project, $account, $type, $cents, $extra['crew_id'] ?? null, $extra['plan_id'] ?? null, mb_substr((string) ($extra['note'] ?? ''), 0, 200), $idem]);
}

/* ---------------------------------------------------------- workspace */

function project_available(array $p): int
{
    return (int) $p['received_cents'] - (int) $p['reserve_cents'] - (int) $p['paid_out_cents'] - (int) $p['held_cents'];
}

function project_owned(int $uid, int $pid): array
{
    $p = q("SELECT * FROM fa_projects WHERE id = ? AND owner_id = ? AND status = 'active'", [$pid, $uid])->fetch();
    if (!$p) {
        throw new AppError('Project not found.');
    }
    return $p;
}

function crew_owned(int $uid, int $cid): array
{
    $c = q("SELECT * FROM fa_crew WHERE id = ? AND owner_id = ? AND status = 'active'", [$cid, $uid])->fetch();
    if (!$c) {
        throw new AppError('Crew member not found.');
    }
    return $c;
}

function mask_email(string $email): string
{
    if (!str_contains($email, '@')) {
        return '';
    }
    [$name, $domain] = explode('@', $email, 2);
    return mb_substr($name, 0, 2) . str_repeat('•', max(1, min(5, mb_strlen($name) - 2))) . '@' . $domain;
}

/** Everything the UI and the agent need, scoped to one owner. */
function workspace_snapshot(int $uid, bool $forAgent = false): array
{
    $crew = q("SELECT * FROM fa_crew WHERE owner_id = ? AND status = 'active' ORDER BY id", [$uid])->fetchAll();
    $crewOut = array_map(fn ($c) => [
        'id'           => (int) $c['id'],
        'name'         => $c['name'],
        'role'         => $c['role'],
        'color'        => $c['color'],
        // The agent never sees payout emails: it can only refer to crew by id.
        'paypal_email' => $forAgent ? null : $c['paypal_email'],
        'email_hold'   => strtotime($c['email_changed_at'] . ' UTC') > time() - EMAIL_CHANGE_HOLD_H * 3600,
    ], $crew);
    if ($forAgent) {
        $crewOut = array_map(fn ($c) => array_diff_key($c, ['paypal_email' => 1, 'color' => 1]), $crewOut);
    }
    $projects = [];
    foreach (q("SELECT * FROM fa_projects WHERE owner_id = ? AND status = 'active' ORDER BY id", [$uid])->fetchAll() as $p) {
        $pid = (int) $p['id'];
        $projects[] = [
            'id'              => $pid,
            'name'            => $p['name'],
            'client'          => $p['client_name'],
            'reserve_pct'     => (int) $p['reserve_pct'],
            'received_cents'  => (int) $p['received_cents'],
            'reserve_cents'   => (int) $p['reserve_cents'],
            'paid_out_cents'  => (int) $p['paid_out_cents'],
            'held_cents'      => (int) $p['held_cents'],
            'available_cents' => project_available($p),
            'splits'          => array_map(fn ($s) => ['crew_id' => (int) $s['crew_id'], 'share_pct' => $s['share_bp'] / 100], q('SELECT crew_id, share_bp FROM fa_splits WHERE project_id = ?', [$pid])->fetchAll()),
            'milestones'      => array_map(fn ($m) => [
                'id' => (int) $m['id'], 'crew_id' => (int) $m['crew_id'], 'title' => $m['title'],
                'amount_cents' => (int) $m['amount_cents'], 'status' => $m['status'], 'due_on' => $m['due_on'],
            ], q('SELECT * FROM fa_milestones WHERE project_id = ? ORDER BY id', [$pid])->fetchAll()),
        ];
    }
    return ['crew' => $crewOut, 'projects' => $projects, 'mandate' => mandate_status($uid)];
}

/* ------------------------------------------------------------ mandate */

function mandate_active(int $uid): ?array
{
    q("UPDATE fa_mandates SET status = 'expired', ended_at = NOW() WHERE owner_id = ? AND status IN ('active','paused') AND expires_at < NOW()", [$uid]);
    $m = q("SELECT * FROM fa_mandates WHERE owner_id = ? AND status IN ('active','paused') ORDER BY id DESC LIMIT 1", [$uid])->fetch();
    return $m ?: null;
}

function mandate_terms_text(int $auto, int $maxPayout, int $perRecipient, int $monthCap, int $days): string
{
    return sprintf(
        'I allow the Konbit agent to send PayPal payouts to my approved crew only, from client money already received, '
        . 'without asking me when a payout is under %s. Payouts from %s up to %s need my tap. It may never pay more than %s '
        . 'to one person in 30 days or %s in total in 30 days, never pay a bonus on its own, and never pay anyone outside my crew list. '
        . 'This mandate lasts %d days and I can pause or revoke it at any time.',
        money($auto), money($auto), money($maxPayout), money($perRecipient), money($monthCap), $days
    );
}

function mandate_validate(int $auto, int $maxPayout, int $perRecipient, int $monthCap, int $days): void
{
    if ($auto < 0 || $auto > 100000 || $maxPayout < 100 || $maxPayout > 1000000 || $perRecipient < 100 || $perRecipient > 2000000
        || $monthCap < 100 || $monthCap > 5000000 || !in_array($days, MANDATE_DAYS, true)) {
        throw new AppError('Mandate limits are out of range (auto-pay up to $1,000, single payout up to $10,000, 7/30/90 days).');
    }
    if ($auto > $maxPayout || $maxPayout > $perRecipient || $perRecipient > $monthCap) {
        throw new AppError('Limits must grow: auto-pay ≤ max payout ≤ per-person monthly ≤ monthly cap.');
    }
}

function mandate_create(int $uid, int $auto, int $maxPayout, int $perRecipient, int $monthCap, int $days): array
{
    mandate_validate($auto, $maxPayout, $perRecipient, $monthCap, $days);
    $text = mandate_terms_text($auto, $maxPayout, $perRecipient, $monthCap, $days);
    tx(function (PDO $pdo) use ($uid, $auto, $maxPayout, $perRecipient, $monthCap, $days, $text) {
        $pdo->prepare("UPDATE fa_mandates SET status = 'revoked', ended_at = NOW() WHERE owner_id = ? AND status IN ('active','paused')")->execute([$uid]);
        $pdo->prepare(
            'INSERT INTO fa_mandates (owner_id, auto_below_cents, max_payout_cents, max_recipient_month_cents, month_cap_cents, expires_at, consent_text)
             VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ' . $days . ' DAY, ?)'
        )->execute([$uid, $auto, $maxPayout, $perRecipient, $monthCap, $text]);
    });
    agent_log($uid, 'owner', 'mandate_signed', ['terms' => $text]);
    return mandate_status($uid);
}

function mandate_set_status(int $uid, string $status): void
{
    $m = mandate_active($uid);
    if (!$m) {
        throw new AppError('There is no mandate to change.');
    }
    if ($status === 'revoked') {
        q("UPDATE fa_mandates SET status = 'revoked', ended_at = NOW() WHERE id = ?", [$m['id']]);
    } else {
        q('UPDATE fa_mandates SET status = ? WHERE id = ?', [$status === 'paused' ? 'paused' : 'active', $m['id']]);
    }
    agent_log($uid, 'owner', 'mandate_' . $status, ['mandate_id' => (int) $m['id']]);
}

/** Money committed in the last 30 days (sent, paying, or paid), in total and per crew member. */
function mandate_usage(int $uid): array
{
    $rows = q(
        "SELECT i.crew_id, SUM(i.amount_cents) AS c FROM fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id
         WHERE p.owner_id = ? AND p.status IN ('sending','sent','paid','partial') AND i.status IN ('planned','sent','success','unclaimed','pending')
           AND p.approved_at > NOW() - INTERVAL 30 DAY GROUP BY i.crew_id",
        [$uid]
    )->fetchAll();
    $per = [];
    $total = 0;
    foreach ($rows as $r) {
        $per[(int) $r['crew_id']] = (int) $r['c'];
        $total += (int) $r['c'];
    }
    $autoToday = (int) q("SELECT COUNT(*) FROM fa_plans WHERE owner_id = ? AND approved_by = 'mandate' AND approved_at > NOW() - INTERVAL 1 DAY", [$uid])->fetchColumn();
    return ['total' => $total, 'per_crew' => $per, 'auto_today' => $autoToday];
}

function mandate_status(int $uid): ?array
{
    $m = mandate_active($uid);
    if (!$m) {
        return null;
    }
    $u = mandate_usage($uid);
    return [
        'id'                        => (int) $m['id'],
        'status'                    => $m['status'],
        'auto_below_cents'          => (int) $m['auto_below_cents'],
        'max_payout_cents'          => (int) $m['max_payout_cents'],
        'max_recipient_month_cents' => (int) $m['max_recipient_month_cents'],
        'month_cap_cents'           => (int) $m['month_cap_cents'],
        'max_auto_per_day'          => (int) $m['max_auto_per_day'],
        'used_30d_cents'            => $u['total'],
        'left_30d_cents'            => max(0, (int) $m['month_cap_cents'] - $u['total']),
        'used_per_crew_cents'       => (object) $u['per_crew'],
        'auto_today'                => $u['auto_today'],
        'expires_at'                => $m['expires_at'] . 'Z',
        'terms'                     => $m['consent_text'],
    ];
}

/* ------------------------------------------------------------- risk */

/** Deterministic red flags in untrusted evidence text (the agent adds its own judgment on top). */
function risk_scan(string $body, int $uid): array
{
    $signals = [];
    $known = array_map('strtolower', q("SELECT paypal_email FROM fa_crew WHERE owner_id = ? AND status = 'active'", [$uid])->fetchAll(PDO::FETCH_COLUMN));
    // An unknown address only counts when its line talks about paying (a sender's From: line is normal).
    foreach (preg_split('/\R/', $body) as $line) {
        if (!preg_match('/pay|send|account|transfer|wire|deposit/i', $line) || !preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line, $m)) {
            continue;
        }
        if (array_diff(array_map('strtolower', $m[0]), $known)) {
            $signals[] = 'mentions a payment address that is not on your crew list';
            break;
        }
    }
    $patterns = [
        '/\b(new|changed?|updated?|different)\s+(paypal|payment|bank|account)/i' => 'asks to change payment details',
        '/ignore (all |any )?(previous|prior|above) (rules|instructions)/i'      => 'tries to override the agent\'s instructions',
        '/\b(urgent|immediately|asap|right now|today only)\b/i'                  => 'pushes urgency',
        '/\b(gift ?cards?|crypto|bitcoin|wire transfer|western union)\b/i'       => 'asks for an unusual payment method',
    ];
    foreach ($patterns as $re => $label) {
        if (preg_match($re, $body)) {
            $signals[] = $label;
        }
    }
    return ['level' => $signals ? 'high' : 'low', 'signals' => array_values(array_unique($signals))];
}

function normalize_text(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s)));
}

/* ------------------------------------------------------------- policy */

/**
 * Runs every rule for a proposed plan. $items: [['crew_id','amount_cents','milestone_id'?]].
 * $ctx: kind, created_by, quote, event (row|null), risk (array|null), exclude_plan (int, at execution time).
 * Returns ['decision' => auto|ask|block, 'checks' => [[rule, result, detail]]].
 */
function policy_evaluate(int $uid, array $project, array $items, array $ctx): array
{
    $checks = [];
    $add = function (string $rule, string $result, string $detail) use (&$checks) {
        $checks[] = ['rule' => $rule, 'result' => $result, 'detail' => $detail];
    };
    $kind = $ctx['kind'];
    $by = $ctx['created_by'];
    $total = array_sum(array_map(fn ($i) => (int) $i['amount_cents'], $items));
    $pid = (int) $project['id'];

    if (!$items || $total <= 0) {
        $add('Amounts', 'block', 'The plan has nothing to pay.');
        return ['decision' => 'block', 'checks' => $checks, 'total_cents' => $total];
    }

    // 1. Allowlist: only active crew members of this owner who work on this project.
    $inProject = array_map('intval', q(
        'SELECT crew_id FROM fa_splits WHERE project_id = ? UNION SELECT crew_id FROM fa_milestones WHERE project_id = ?',
        [$pid, $pid]
    )->fetchAll(PDO::FETCH_COLUMN));
    $bad = [];
    $held = [];
    foreach ($items as $i) {
        $c = q("SELECT * FROM fa_crew WHERE id = ? AND owner_id = ? AND status = 'active'", [$i['crew_id'], $uid])->fetch();
        if (!$c || !in_array((int) $i['crew_id'], $inProject, true)) {
            $bad[] = '#' . (int) $i['crew_id'];
        } elseif (strtotime($c['email_changed_at'] . ' UTC') > time() - EMAIL_CHANGE_HOLD_H * 3600) {
            $held[] = $c['name'];
        }
        if ((int) $i['amount_cents'] < 1) {
            $bad[] = 'zero amount';
        }
    }
    $bad ? $add('Crew allowlist', 'block', 'Not on this project\'s crew list: ' . implode(', ', $bad) . '.')
         : $add('Crew allowlist', 'pass', 'Every recipient is on your crew list for this project.');
    if ($held) {
        $add('Payout email age', 'ask', implode(', ', $held) . ' changed their PayPal email in the last ' . EMAIL_CHANGE_HOLD_H . ' h, so a human must approve.');
    }

    // 2. Funds: only client money already received, minus the reserve, may go out.
    $available = project_available($project) + (int) ($ctx['already_held'] ?? 0);
    $total <= $available
        ? $add('Funds received', 'pass', money($total) . ' of ' . money($available) . ' available from captured client payments.')
        : $add('Funds received', 'block', 'Needs ' . money($total) . ' but only ' . money($available) . ' of received client money is free (after the reserve).');

    // 3. Milestones: must exist, belong here, be unpaid, and match the agreed fee exactly.
    foreach ($items as $i) {
        if (empty($i['milestone_id'])) {
            continue;
        }
        $m = q('SELECT * FROM fa_milestones WHERE id = ? AND project_id = ?', [$i['milestone_id'], $pid])->fetch();
        $okStatus = $m && ($m['status'] === 'open' || ($m['status'] === 'in_payout' && !empty($ctx['exclude_plan'])));
        if (!$m || (int) $m['crew_id'] !== (int) $i['crew_id'] || !$okStatus) {
            $add('Milestone', 'block', 'Milestone #' . (int) $i['milestone_id'] . ' is not an open milestone for this crew member.');
        } elseif ((int) $m['amount_cents'] !== (int) $i['amount_cents']) {
            $add('Milestone', 'block', 'Amount differs from the agreed fee of ' . money((int) $m['amount_cents']) . '.');
        } else {
            $add('Milestone', 'pass', '"' . $m['title'] . '" is open and the amount matches the agreed fee.');
        }
    }

    // 4. Evidence: an agent's quote must appear word for word in the evidence it read.
    if ($by === 'agent') {
        $quote = normalize_text((string) ($ctx['quote'] ?? ''));
        $body = normalize_text((string) ($ctx['event']['body'] ?? ''));
        if ($ctx['event'] === null) {
            $add('Evidence', 'ask', 'Proposed in chat without work evidence, so a human must approve.');
        } elseif ($quote === '' || mb_strlen($quote) < 8 || !str_contains($body, $quote)) {
            $add('Evidence', 'block', 'The agent\'s quote was not found in the evidence, so the claim is unproven.');
        } else {
            $add('Evidence', 'pass', 'Quote verified in the ' . $ctx['event']['source'] . ' evidence.');
        }
        if ($ctx['event'] !== null) {
            $dup = (int) q(
                "SELECT COUNT(*) FROM fa_plans p JOIN fa_events e ON e.id = p.event_id
                 WHERE p.owner_id = ? AND e.body_hash = ? AND p.status NOT IN ('rejected','blocked','failed') AND p.id <> ?",
                [$uid, $ctx['event']['body_hash'], (int) ($ctx['exclude_plan'] ?? 0)]
            )->fetchColumn();
            if ($dup > 0) {
                $add('Replay', 'block', 'This exact evidence already produced a payout.');
            }
        }
    }
    if ($kind === 'bonus') {
        $add('Bonus rule', 'ask', 'Bonuses are never paid without your tap.');
    }

    // 5. Risk signals from the evidence text and from the agent.
    $risk = $ctx['risk'] ?? null;
    if ($risk && $risk['level'] === 'high') {
        $add('Risk scan', 'ask', 'Red flags: ' . implode('; ', $risk['signals']) . '.');
    }

    // 6. Mandate: the hard caps block, the auto-pay threshold decides auto vs ask.
    $m = mandate_active($uid);
    if (!$m) {
        $add('Mandate', 'ask', 'No active mandate: you approve every payout.');
    } elseif ($m['status'] !== 'active') {
        $add('Mandate', 'ask', 'The mandate is paused: you approve every payout.');
    } else {
        $usage = mandate_usage($uid);
        if (!empty($ctx['exclude_plan'])) {
            // At execution time this plan's own items may already count as committed.
            foreach (q("SELECT crew_id, amount_cents FROM fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id WHERE p.id = ? AND p.status IN ('sending','sent','paid','partial')", [$ctx['exclude_plan']])->fetchAll() as $r) {
                $usage['per_crew'][(int) $r['crew_id']] = ($usage['per_crew'][(int) $r['crew_id']] ?? 0) - (int) $r['amount_cents'];
                $usage['total'] -= (int) $r['amount_cents'];
            }
        }
        $over = array_filter($items, fn ($i) => (int) $i['amount_cents'] > (int) $m['max_payout_cents']);
        $over ? $add('Max per payout', 'block', 'A payout is above the mandate\'s ' . money((int) $m['max_payout_cents']) . ' limit.')
              : $add('Max per payout', 'pass', 'Each payout is within ' . money((int) $m['max_payout_cents']) . '.');
        $perBad = [];
        foreach ($items as $i) {
            $used = $usage['per_crew'][(int) $i['crew_id']] ?? 0;
            if ($used + (int) $i['amount_cents'] > (int) $m['max_recipient_month_cents']) {
                $perBad[] = '#' . (int) $i['crew_id'] . ' (' . money($used) . ' already)';
            }
        }
        $perBad ? $add('Per person / 30 days', 'block', 'Over ' . money((int) $m['max_recipient_month_cents']) . ' for ' . implode(', ', $perBad) . '.')
                : $add('Per person / 30 days', 'pass', 'Everyone stays under ' . money((int) $m['max_recipient_month_cents']) . ' in 30 days.');
        $usage['total'] + $total > (int) $m['month_cap_cents']
            ? $add('Monthly cap', 'block', money($usage['total']) . ' already sent; this would pass the ' . money((int) $m['month_cap_cents']) . ' cap.')
            : $add('Monthly cap', 'pass', money($usage['total'] + $total) . ' of ' . money((int) $m['month_cap_cents']) . ' used after this.');
        $total < (int) $m['auto_below_cents']
            ? $add('Auto-pay threshold', 'pass', 'Under ' . money((int) $m['auto_below_cents']) . ', the agent may pay on its own.')
            : $add('Auto-pay threshold', 'ask', money($total) . ' is at or above ' . money((int) $m['auto_below_cents']) . ', so you approve it.');
        if ($usage['auto_today'] >= (int) $m['max_auto_per_day']) {
            $add('Daily velocity', 'ask', 'The agent already auto-paid ' . $usage['auto_today'] . ' plans in 24 h.');
        }
    }

    $results = array_column($checks, 'result');
    $decision = in_array('block', $results, true) ? 'block' : (in_array('ask', $results, true) ? 'ask' : 'auto');
    return ['decision' => $decision, 'checks' => $checks, 'total_cents' => $total];
}

/* -------------------------------------------------------------- plans */

/**
 * Creates a plan, runs the policy, and pays at once when the mandate allows.
 * $opts: kind, created_by, title, reason, quote, event, client_payment_id, pay_on, risk.
 */
function plan_create(int $uid, array $project, array $items, array $opts): array
{
    $ctx = [
        'kind'       => $opts['kind'],
        'created_by' => $opts['created_by'],
        'quote'      => $opts['quote'] ?? null,
        'event'      => $opts['event'] ?? null,
        'risk'       => $opts['risk'] ?? null,
    ];
    $policy = policy_evaluate($uid, $project, $items, $ctx);
    $decision = $policy['decision'];
    $payOn = $opts['pay_on'] ?? null;
    if ($payOn !== null && $payOn <= gmdate('Y-m-d')) {
        $payOn = null;
    }
    $status = $decision === 'block' ? 'blocked' : ($decision === 'ask' ? 'needs_approval' : ($payOn ? 'scheduled' : 'needs_approval'));

    $planId = tx(function (PDO $pdo) use ($uid, $project, $items, $opts, $policy, $status, $payOn, $decision) {
        // Claim milestones so two plans can never pay the same deliverable.
        if ($decision !== 'block') {
            foreach ($items as $i) {
                if (!empty($i['milestone_id'])) {
                    $st = $pdo->prepare("UPDATE fa_milestones SET status = 'in_payout' WHERE id = ? AND status = 'open'");
                    $st->execute([$i['milestone_id']]);
                    if ($st->rowCount() !== 1) {
                        throw new AppError('That milestone is already being paid.');
                    }
                }
            }
        }
        $pdo->prepare(
            'INSERT INTO fa_plans (owner_id, project_id, event_id, client_payment_id, kind, created_by, title, reason, evidence_quote, total_cents, decision, policy_json, status, pay_on, approved_by, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uid, $project['id'], $opts['event']['id'] ?? null, $opts['client_payment_id'] ?? null, $opts['kind'], $opts['created_by'],
            mb_substr($opts['title'], 0, 200), mb_substr((string) ($opts['reason'] ?? ''), 0, 600),
            isset($opts['quote']) ? mb_substr((string) $opts['quote'], 0, 600) : null,
            $policy['total_cents'], $decision, json_encode($policy['checks'], JSON_UNESCAPED_UNICODE), $status, $payOn,
            $status === 'scheduled' ? 'mandate' : null, null,
        ]);
        $planId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO fa_plan_items (plan_id, crew_id, milestone_id, receiver, amount_cents, note) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($items as $i) {
            $c = $pdo->prepare('SELECT paypal_email FROM fa_crew WHERE id = ? AND owner_id = ?');
            $c->execute([$i['crew_id'], $uid]);
            $ins->execute([$planId, $i['crew_id'], $i['milestone_id'] ?? null, (string) ($c->fetchColumn() ?: ''), $i['amount_cents'], mb_substr((string) ($i['note'] ?? $opts['title']), 0, 200)]);
        }
        return $planId;
    });
    agent_log($uid, 'policy', 'plan_' . $decision, ['plan' => $planId, 'title' => $opts['title'], 'total' => money($policy['total_cents']), 'by' => $opts['created_by']]);

    $out = ['plan_id' => $planId, 'decision' => $decision, 'status' => $status, 'total_cents' => $policy['total_cents'], 'checks' => $policy['checks']];
    if ($decision === 'auto' && !$payOn) {
        $out = array_merge($out, plan_execute($uid, $planId, 'mandate'));
    } elseif ($opts['created_by'] === 'owner' && $decision === 'ask' && !$payOn) {
        // The owner pressed the button themself: that is the approval.
        $out = array_merge($out, plan_execute($uid, $planId, 'owner'));
    }
    return $out;
}

function plan_owned(int $uid, int $planId): array
{
    $p = q('SELECT * FROM fa_plans WHERE id = ? AND owner_id = ?', [$planId, $uid])->fetch();
    if (!$p) {
        throw new AppError('Payout plan not found.');
    }
    return $p;
}

function plan_items(int $planId): array
{
    return q('SELECT * FROM fa_plan_items WHERE plan_id = ? ORDER BY id', [$planId])->fetchAll();
}

/** Frees milestones claimed by a plan that will not pay. */
function plan_release_milestones(PDO $pdo, int $planId): void
{
    $pdo->prepare(
        "UPDATE fa_milestones m JOIN fa_plan_items i ON i.milestone_id = m.id SET m.status = 'open'
         WHERE i.plan_id = ? AND m.status = 'in_payout'"
    )->execute([$planId]);
}

/**
 * Approves and pays a plan. $by = 'mandate' (the agent, only when policy says auto)
 * or 'owner' (a human tap; 'ask' is fine, 'block' never is).
 */
function plan_execute(int $uid, int $planId, string $by): array
{
    $plan = plan_owned($uid, $planId);
    if (!in_array($plan['status'], ['needs_approval', 'scheduled'], true)) {
        throw new AppError('This plan is ' . str_replace('_', ' ', $plan['status']) . ' and cannot be paid now.');
    }
    if (!paypal_ready()) {
        throw new AppError('PayPal is not connected yet.');
    }
    $project = project_owned($uid, (int) $plan['project_id']);
    $items = array_map(fn ($i) => ['crew_id' => (int) $i['crew_id'], 'amount_cents' => (int) $i['amount_cents'], 'milestone_id' => $i['milestone_id'] ? (int) $i['milestone_id'] : null], plan_items($planId));
    $event = $plan['event_id'] ? q('SELECT * FROM fa_events WHERE id = ?', [$plan['event_id']])->fetch() : null;
    // Re-check everything at execution time: limits, funds and crew may have changed.
    $policy = policy_evaluate($uid, $project, $items, [
        'kind' => $plan['kind'], 'created_by' => $plan['created_by'], 'quote' => $plan['evidence_quote'],
        'event' => $event ?: null, 'risk' => $event ? risk_scan((string) $event['body'], $uid) : null, 'exclude_plan' => $planId,
    ]);
    $allowed = $by === 'owner' ? $policy['decision'] !== 'block' : $policy['decision'] === 'auto';
    if (!$allowed) {
        q('UPDATE fa_plans SET policy_json = ?, decision = ? WHERE id = ?', [json_encode($policy['checks'], JSON_UNESCAPED_UNICODE), $policy['decision'], $planId]);
        if ($policy['decision'] === 'block') {
            tx(function (PDO $pdo) use ($planId) {
                $pdo->prepare("UPDATE fa_plans SET status = 'blocked' WHERE id = ?")->execute([$planId]);
                plan_release_milestones($pdo, $planId);
            });
        } elseif ($plan['status'] === 'scheduled') {
            q("UPDATE fa_plans SET status = 'needs_approval' WHERE id = ?", [$planId]);
        }
        agent_log($uid, 'policy', 'execute_refused', ['plan' => $planId, 'by' => $by, 'decision' => $policy['decision']]);
        return ['paid' => false, 'status' => $policy['decision'] === 'block' ? 'blocked' : 'needs_approval', 'checks' => $policy['checks'],
            'message' => $policy['decision'] === 'block' ? 'Blocked by policy at payment time.' : 'This plan needs your approval.'];
    }
    $total = (int) $policy['total_cents'];

    // Hold the money atomically: the UPDATE only succeeds while enough received money is free.
    tx(function (PDO $pdo) use ($uid, $plan, $planId, $total, $by) {
        $st = $pdo->prepare("UPDATE fa_plans SET status = 'sending', approved_by = ?, approved_at = NOW(), sender_batch_id = ? WHERE id = ? AND status IN ('needs_approval','scheduled')");
        $st->execute([$by, 'fa-plan-' . $planId, $planId]);
        if ($st->rowCount() !== 1) {
            throw new AppError('This plan is already being paid.');
        }
        $st = $pdo->prepare(
            'UPDATE fa_projects SET held_cents = held_cents + ?
             WHERE id = ? AND owner_id = ? AND received_cents - reserve_cents - paid_out_cents - held_cents >= ?'
        );
        $st->execute([$total, $plan['project_id'], $uid, $total]);
        if ($st->rowCount() !== 1) {
            throw new AppError('Not enough received client money is free for this payout.');
        }
        ledger($pdo, $uid, (int) $plan['project_id'], 'hold', 'payout_hold', $total, "plan:$planId:hold", ['plan_id' => $planId, 'note' => 'Held for Payouts batch']);
    });

    $ppItems = [];
    $currency = $project['currency'];
    foreach (plan_items($planId) as $i) {
        $ppItems[] = [
            'recipient_type' => 'EMAIL',
            'amount'         => ['value' => number_format($i['amount_cents'] / 100, 2, '.', ''), 'currency' => $currency],
            'receiver'       => $i['receiver'],
            'note'           => mb_substr($i['note'] . ' · ' . $project['name'], 0, 4000),
            'sender_item_id' => 'fa-item-' . $i['id'],
        ];
    }
    [$code, $res] = paypal_payout_send('fa-plan-' . $planId, $project['name'] . ': ' . $plan['title'], $ppItems);
    $batchId = (string) ($res['batch_header']['payout_batch_id'] ?? '');
    if (in_array($code, [200, 201], true) && $batchId !== '') {
        q("UPDATE fa_plans SET status = 'sent', paypal_batch_id = ?, last_sync_at = NOW() WHERE id = ?", [$batchId, $planId]);
        q("UPDATE fa_plan_items SET status = 'sent' WHERE plan_id = ?", [$planId]);
        agent_log($uid, 'paypal', 'payouts_batch_sent', ['plan' => $planId, 'batch' => $batchId, 'total' => money($total), 'by' => $by]);
        return ['paid' => true, 'status' => 'sent', 'batch_id' => $batchId, 'message' => money($total) . ' sent with PayPal Payouts.'];
    }
    if ($code === 0) {
        // Unknown outcome (timeout): keep the hold; the same sender_batch_id can never pay twice.
        agent_log($uid, 'paypal', 'payouts_batch_unknown', ['plan' => $planId]);
        return ['paid' => false, 'status' => 'sending', 'message' => 'PayPal did not answer in time. Konbit will check the batch again.'];
    }
    error_log('Konbit payout failed: HTTP ' . $code . ' ' . ($res['name'] ?? '') . ' ' . ($res['message'] ?? ''));
    tx(function (PDO $pdo) use ($uid, $plan, $planId, $total) {
        $pdo->prepare("UPDATE fa_plans SET status = 'failed' WHERE id = ?")->execute([$planId]);
        $pdo->prepare("UPDATE fa_plan_items SET status = 'failed' WHERE plan_id = ?")->execute([$planId]);
        $pdo->prepare('UPDATE fa_projects SET held_cents = held_cents - ? WHERE id = ?')->execute([$total, $plan['project_id']]);
        ledger($pdo, $uid, (int) $plan['project_id'], 'hold', 'payout_release', -$total, "plan:$planId:release", ['plan_id' => $planId, 'note' => 'PayPal refused the batch']);
        plan_release_milestones($pdo, $planId);
    });
    agent_log($uid, 'paypal', 'payouts_batch_failed', ['plan' => $planId, 'http' => $code, 'name' => $res['name'] ?? '']);
    $why = ($res['name'] ?? '') === 'INSUFFICIENT_FUNDS' ? ' The PayPal business balance is too low.' : '';
    return ['paid' => false, 'status' => 'failed', 'message' => 'PayPal refused the payout batch.' . $why];
}

function plan_reject(int $uid, int $planId): void
{
    tx(function (PDO $pdo) use ($uid, $planId) {
        $st = $pdo->prepare("UPDATE fa_plans SET status = 'rejected' WHERE id = ? AND owner_id = ? AND status IN ('needs_approval','scheduled','blocked')");
        $st->execute([$planId, $uid]);
        if ($st->rowCount() !== 1) {
            throw new AppError('This plan can no longer be rejected.');
        }
        $pdo->prepare("UPDATE fa_plan_items SET status = 'cancelled' WHERE plan_id = ?")->execute([$planId]);
        plan_release_milestones($pdo, $planId);
    });
    agent_log($uid, 'owner', 'plan_rejected', ['plan' => $planId]);
}

/** Moves a not-yet-paid plan to another day (Bryntum drag & drop). */
function plan_reschedule(int $uid, int $planId, string $date): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date <= gmdate('Y-m-d') || $date > gmdate('Y-m-d', time() + 90 * 86400)) {
        throw new AppError('Pick a day between tomorrow and 90 days from now.');
    }
    $plan = plan_owned($uid, $planId);
    if (!in_array($plan['status'], ['needs_approval', 'scheduled'], true)) {
        throw new AppError('Only unpaid plans can be moved.');
    }
    // A scheduled plan keeps its mandate approval only if it still qualifies; otherwise it waits for a tap.
    $status = $plan['decision'] === 'auto' ? 'scheduled' : 'needs_approval';
    q('UPDATE fa_plans SET pay_on = ?, status = ?, approved_by = ? WHERE id = ?', [$date, $status, $status === 'scheduled' ? 'mandate' : null, $planId]);
    agent_log($uid, 'owner', 'plan_rescheduled', ['plan' => $planId, 'pay_on' => $date]);
    return ['plan_id' => $planId, 'pay_on' => $date, 'status' => $status];
}

/* -------------------------------------------------------------- sync */

const PAYPAL_ITEM_STATUS = [
    'SUCCESS' => 'success', 'UNCLAIMED' => 'unclaimed', 'PENDING' => 'pending', 'ONHOLD' => 'pending', 'NEW' => 'pending',
    'FAILED' => 'failed', 'DENIED' => 'failed', 'BLOCKED' => 'failed', 'RETURNED' => 'returned', 'REFUNDED' => 'returned', 'REVERSED' => 'returned',
];

/** Applies one PayPal item status. Money moves exactly once per state change. */
function plan_item_apply(int $itemId, string $ppStatus, ?string $ppItemId): void
{
    $new = PAYPAL_ITEM_STATUS[strtoupper($ppStatus)] ?? 'pending';
    tx(function (PDO $pdo) use ($itemId, $ppStatus, $ppItemId, $new) {
        $st = $pdo->prepare('SELECT i.*, p.owner_id, p.project_id, p.id AS pid FROM fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id WHERE i.id = ? FOR UPDATE');
        $st->execute([$itemId]);
        $i = $st->fetch();
        if (!$i) {
            return;
        }
        $amount = (int) $i['amount_cents'];
        $owner = (int) $i['owner_id'];
        $project = (int) $i['project_id'];
        $pdo->prepare('SELECT id FROM fa_projects WHERE id = ? FOR UPDATE')->execute([$project]);
        $wasSettled = (int) $i['settled'] === 1;
        $wasOpen = in_array($i['status'], ['sent', 'pending', 'planned'], true);
        if (in_array($new, ['success', 'unclaimed'], true) && !$wasSettled) {
            // The money left the PayPal balance: hold -> paid out.
            $pdo->prepare('UPDATE fa_projects SET held_cents = held_cents - ?, paid_out_cents = paid_out_cents + ? WHERE id = ?')->execute([$amount, $amount, $project]);
            ledger($pdo, $owner, $project, 'hold', 'payout_settle', -$amount, "item:$itemId:unhold", ['plan_id' => (int) $i['pid'], 'crew_id' => (int) $i['crew_id']]);
            ledger($pdo, $owner, $project, 'crew', 'payout', $amount, "item:$itemId:paid", ['plan_id' => (int) $i['pid'], 'crew_id' => (int) $i['crew_id'], 'note' => 'PayPal ' . strtoupper($ppStatus)]);
            ledger($pdo, $owner, $project, 'pot', 'payout', -$amount, "item:$itemId:pot", ['plan_id' => (int) $i['pid'], 'crew_id' => (int) $i['crew_id']]);
            if ($i['milestone_id']) {
                $pdo->prepare("UPDATE fa_milestones SET status = 'paid' WHERE id = ?")->execute([$i['milestone_id']]);
            }
            $pdo->prepare('UPDATE fa_plan_items SET settled = 1 WHERE id = ?')->execute([$itemId]);
        } elseif (in_array($new, ['failed', 'returned'], true)) {
            if ($wasSettled) {
                // Returned after it was counted as paid: the money is back in the pot.
                $pdo->prepare('UPDATE fa_projects SET paid_out_cents = paid_out_cents - ? WHERE id = ?')->execute([$amount, $project]);
                ledger($pdo, $owner, $project, 'pot', 'payout_returned', $amount, "item:$itemId:returned", ['plan_id' => (int) $i['pid'], 'crew_id' => (int) $i['crew_id']]);
                $pdo->prepare('UPDATE fa_plan_items SET settled = 0 WHERE id = ?')->execute([$itemId]);
            } elseif ($wasOpen) {
                $pdo->prepare('UPDATE fa_projects SET held_cents = held_cents - ? WHERE id = ?')->execute([$amount, $project]);
                ledger($pdo, $owner, $project, 'hold', 'payout_release', -$amount, "item:$itemId:release", ['plan_id' => (int) $i['pid'], 'crew_id' => (int) $i['crew_id'], 'note' => 'PayPal ' . strtoupper($ppStatus)]);
            }
            if ($i['milestone_id'] && ($wasSettled || $wasOpen)) {
                $pdo->prepare("UPDATE fa_milestones SET status = 'open' WHERE id = ?")->execute([$i['milestone_id']]);
            }
        }
        $pdo->prepare('UPDATE fa_plan_items SET status = ?, paypal_status = ?, paypal_item_id = COALESCE(?, paypal_item_id) WHERE id = ?')
            ->execute([$new, strtoupper($ppStatus), $ppItemId, $itemId]);
    });
}

function plan_refresh_status(int $planId): void
{
    $st = array_column(plan_items($planId), 'status');
    if (!$st) {
        return;
    }
    $open = array_intersect($st, ['planned', 'sent', 'pending']);
    $good = array_intersect($st, ['success', 'unclaimed']);
    $bad = array_intersect($st, ['failed', 'returned']);
    $status = $open ? 'sent' : ($good && !$bad ? 'paid' : ($good ? 'partial' : 'failed'));
    q("UPDATE fa_plans SET status = ?, last_sync_at = NOW() WHERE id = ? AND status IN ('sending','sent','paid','partial','failed')", [$status, $planId]);
}

/** Pulls the batch status from PayPal and applies it. */
function plan_sync(array $plan): void
{
    if (!$plan['paypal_batch_id']) {
        return;
    }
    [$code, $res] = paypal_payout_get((string) $plan['paypal_batch_id']);
    if ($code !== 200) {
        q('UPDATE fa_plans SET last_sync_at = NOW() WHERE id = ?', [$plan['id']]);
        return;
    }
    $mine = [];
    foreach (plan_items((int) $plan['id']) as $i) {
        $mine['fa-item-' . $i['id']] = (int) $i['id'];
    }
    foreach ($res['items'] ?? [] as $it) {
        $sid = (string) ($it['payout_item']['sender_item_id'] ?? '');
        if (isset($mine[$sid])) {
            plan_item_apply($mine[$sid], (string) ($it['transaction_status'] ?? 'PENDING'), $it['payout_item_id'] ?? null);
        }
    }
    plan_refresh_status((int) $plan['id']);
}

/** Lazy background work, run on page polls: due scheduled plans, then batch status. */
function engine_sweep(int $uid): void
{
    if (!paypal_ready() || !rate_limit("sweep:$uid", 6, 60)) {
        return;
    }
    foreach (q("SELECT id FROM fa_plans WHERE owner_id = ? AND status = 'scheduled' AND pay_on <= CURDATE() ORDER BY id LIMIT 3", [$uid])->fetchAll() as $p) {
        try {
            plan_execute($uid, (int) $p['id'], 'mandate');
        } catch (AppError $e) {
            agent_log($uid, 'policy', 'scheduled_failed', ['plan' => (int) $p['id'], 'error' => $e->getMessage()]);
        }
    }
    $due = q(
        "SELECT * FROM fa_plans WHERE owner_id = ? AND status IN ('sent','partial','paid') AND paypal_batch_id IS NOT NULL
           AND (last_sync_at IS NULL OR last_sync_at < NOW() - INTERVAL " . PLAN_SYNC_EVERY_SEC . " SECOND)
           AND created_at > NOW() - INTERVAL 35 DAY
           AND (status = 'sent' OR EXISTS (SELECT 1 FROM fa_plan_items i WHERE i.plan_id = fa_plans.id AND i.status IN ('unclaimed','pending','sent')))
         ORDER BY last_sync_at LIMIT 3",
        [$uid]
    )->fetchAll();
    foreach ($due as $plan) {
        plan_sync($plan);
    }
}

/* ------------------------------------------------------ revenue split */

/** When a client payment lands, propose the crew's revenue shares (the policy decides auto vs ask). */
function plan_revenue_split(array $payment): ?array
{
    $uid = (int) $payment['owner_id'];
    $project = project_owned($uid, (int) $payment['project_id']);
    $splits = q('SELECT crew_id, share_bp FROM fa_splits WHERE project_id = ? AND share_bp > 0', [$project['id']])->fetchAll();
    if (!$splits) {
        return null;
    }
    $amount = (int) $payment['amount_cents'];
    $items = [];
    foreach ($splits as $s) {
        $cents = intdiv($amount * (int) $s['share_bp'], 10000);
        if ($cents > 0) {
            $name = (string) q('SELECT name FROM fa_crew WHERE id = ?', [$s['crew_id']])->fetchColumn();
            $items[] = ['crew_id' => (int) $s['crew_id'], 'amount_cents' => $cents, 'note' => ($s['share_bp'] / 100) . '% revenue share for ' . $name];
        }
    }
    return plan_create($uid, $project, $items, [
        'kind' => 'split', 'created_by' => 'rule', 'client_payment_id' => (int) $payment['id'],
        'title' => 'Revenue split of ' . money($amount) . ($payment['memo'] !== '' ? ' · ' . $payment['memo'] : ''),
        'reason' => 'The client paid ' . money($amount) . ' through PayPal. Shares follow the project split.',
    ]);
}
