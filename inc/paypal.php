<?php
/**
 * PayPal, fully server-side:
 * - Orders v2: a client pays a payment request; the server sets the amount,
 *   captures, checks amount + currency + reference, and credits the project once.
 * - Payouts: one batch per approved plan, idempotent by sender_batch_id.
 * - Webhooks: verified with verify-webhook-signature before anything changes.
 */
declare(strict_types=1);

defined('FA') || exit;

function paypal_ready(): bool
{
    return cfg('paypal.client_id') !== '' && cfg('paypal.secret') !== '';
}

function paypal_mode(): string
{
    return cfg('paypal.mode') === 'live' ? 'live' : 'sandbox';
}

function paypal_api_base(): string
{
    return paypal_mode() === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

/** Low-level HTTPS call. Returns [httpCode, decodedJson]. Never logs secrets. */
function paypal_http(string $method, string $path, $body = null, array $headers = [], ?string $basicAuth = null): array
{
    $ch = curl_init(paypal_api_base() . $path);
    $h = array_merge(['Accept: application/json'], $headers);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($basicAuth !== null) {
        $opts[CURLOPT_USERPWD] = $basicAuth;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
        if (!is_string($body)) {
            $h[] = 'Content-Type: application/json';
        }
    }
    $opts[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        error_log('Konbit PayPal curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return [$code, is_string($raw) ? (json_decode($raw, true) ?: []) : []];
}

function paypal_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    [$code, $res] = paypal_http(
        'POST',
        '/v1/oauth2/token',
        'grant_type=client_credentials',
        ['Content-Type: application/x-www-form-urlencoded'],
        cfg('paypal.client_id') . ':' . cfg('paypal.secret')
    );
    if ($code !== 200 || empty($res['access_token'])) {
        error_log('Konbit PayPal auth failed: HTTP ' . $code);
        throw new AppError('PayPal is unavailable right now. Please try later.');
    }
    return $token = (string) $res['access_token'];
}

function paypal_api(string $method, string $path, $body = null, array $headers = []): array
{
    return paypal_http($method, $path, $body, array_merge(['Authorization: Bearer ' . paypal_token()], $headers));
}

/* ---------------------------------------------------- client payments */

/** Creates the PayPal order for a payment request; returns the approval URL. */
function client_payment_checkout(array $req): string
{
    if (!paypal_ready()) {
        throw new AppError('PayPal is not connected yet.');
    }
    if (!in_array($req['status'], ['requested', 'created', 'failed', 'cancelled'], true)) {
        throw new AppError('This payment request is already paid.');
    }
    $project = q('SELECT p.name, u.business_name, u.name AS owner_name FROM fa_projects p JOIN fa_users u ON u.id = p.owner_id WHERE p.id = ?', [$req['project_id']])->fetch();
    $brand = mb_substr($project['business_name'] !== '' ? $project['business_name'] : $project['owner_name'], 0, 120);
    $id = (int) $req['id'];
    [$code, $res] = paypal_api('POST', '/v2/checkout/orders', [
        'intent'         => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'fa-cp-' . $id,
            'custom_id'    => (string) $id,
            'description'  => mb_substr($project['name'] . ($req['memo'] !== '' ? ' · ' . $req['memo'] : ''), 0, 120),
            'amount'       => ['currency_code' => $req['currency'], 'value' => number_format($req['amount_cents'] / 100, 2, '.', '')],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'brand_name'          => $brand,
            'locale'              => 'en-US',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
            'return_url'          => abs_url('paypal-return.php?r=' . $req['pay_token']),
            'cancel_url'          => abs_url('pay.php?r=' . $req['pay_token'] . '&cancel=1'),
        ]]],
    ], ['PayPal-Request-Id: fa-cp-create-' . $id . '-' . bin2hex(random_bytes(4))]);

    $approve = '';
    foreach ($res['links'] ?? [] as $link) {
        if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
            $approve = (string) $link['href'];
        }
    }
    if (!in_array($code, [200, 201], true) || empty($res['id']) || $approve === '') {
        error_log('Konbit PayPal create failed: HTTP ' . $code . ' ' . ($res['name'] ?? ''));
        throw new AppError('Could not start PayPal checkout. Please try again.');
    }
    q("UPDATE fa_client_payments SET paypal_order_id = ?, status = 'created', mode = ? WHERE id = ? AND status <> 'completed'", [$res['id'], paypal_mode(), $id]);
    return $approve;
}

/** Captures an approved order (idempotent) and credits it. Returns 'completed', 'already' or 'pending'. */
function client_payment_capture(array $req): string
{
    if ($req['status'] === 'completed') {
        return 'already';
    }
    $ppId = (string) $req['paypal_order_id'];
    if ($ppId === '') {
        throw new AppError('This payment was not started.');
    }
    [$code, $res] = paypal_api('POST', '/v2/checkout/orders/' . rawurlencode($ppId) . '/capture', new stdClass(), [
        'PayPal-Request-Id: fa-cp-capture-' . $req['id'] . '-' . $ppId,
    ]);
    if ($code === 422 && (($res['details'][0]['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED')) {
        [$code, $res] = paypal_api('GET', '/v2/checkout/orders/' . rawurlencode($ppId));
    }
    if (!in_array($code, [200, 201], true)) {
        error_log('Konbit PayPal capture failed: HTTP ' . $code . ' ' . ($res['name'] ?? ''));
        throw new AppError('PayPal did not confirm the payment. Nothing was charged twice; please try again.');
    }
    $unit = $res['purchase_units'][0] ?? [];
    if ((string) ($unit['reference_id'] ?? '') !== 'fa-cp-' . $req['id']) {
        error_log('Konbit PayPal reference mismatch on request ' . $req['id']);
        throw new AppError('Payment reference mismatch.');
    }
    $capture = $unit['payments']['captures'][0] ?? null;
    if (!$capture) {
        throw new AppError('The payment was not completed.');
    }
    $payer = (string) ($res['payer']['email_address'] ?? '');
    return client_payment_credit((int) $req['id'], $capture, $payer);
}

/**
 * Credits a captured client payment once: the project pot grows, the
 * reserve share is set aside, then the revenue-split rule runs.
 */
function client_payment_credit(int $id, array $capture, string $payerEmail = ''): string
{
    $result = tx(function (PDO $pdo) use ($id, $capture, $payerEmail) {
        $st = $pdo->prepare('SELECT * FROM fa_client_payments WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            throw new AppError('Payment request not found.');
        }
        if ($r['status'] === 'completed') {
            return 'already';
        }
        $status = (string) ($capture['status'] ?? '');
        if ($status === 'PENDING') {
            $pdo->prepare("UPDATE fa_client_payments SET status = 'pending', capture_id = ? WHERE id = ?")->execute([$capture['id'] ?? null, $id]);
            return 'pending';
        }
        if ($status !== 'COMPLETED') {
            $pdo->prepare("UPDATE fa_client_payments SET status = 'failed' WHERE id = ?")->execute([$id]);
            throw new AppError('The payment was not completed.');
        }
        $value = (string) ($capture['amount']['value'] ?? '');
        $currency = (string) ($capture['amount']['currency_code'] ?? '');
        $expected = number_format(((int) $r['amount_cents']) / 100, 2, '.', '');
        if ($value !== $expected || $currency !== $r['currency']) {
            error_log("Konbit PayPal amount mismatch on request $id: got $value $currency, expected $expected {$r['currency']}");
            $pdo->prepare("UPDATE fa_client_payments SET status = 'failed' WHERE id = ?")->execute([$id]);
            throw new AppError('Payment amount did not match.');
        }
        $st = $pdo->prepare('SELECT * FROM fa_projects WHERE id = ? FOR UPDATE');
        $st->execute([$r['project_id']]);
        $p = $st->fetch();
        $amount = (int) $r['amount_cents'];
        $reserve = intdiv($amount * (int) $p['reserve_pct'], 100);
        $pdo->prepare('UPDATE fa_projects SET received_cents = received_cents + ?, reserve_cents = reserve_cents + ? WHERE id = ?')
            ->execute([$amount, $reserve, $p['id']]);
        $seeded = str_starts_with((string) ($capture['id'] ?? ''), 'DEMO-SEED-');
        ledger($pdo, (int) $r['owner_id'], (int) $p['id'], 'pot', $seeded ? 'demo_funds' : 'client_payment', $amount, "cp:$id:in", ['note' => ($seeded ? 'Demo starting funds (seeded, not a PayPal capture) ' : 'PayPal ') . $expected . ' ' . $currency . ($r['memo'] !== '' ? ' · ' . $r['memo'] : '')]);
        if ($reserve > 0) {
            ledger($pdo, (int) $r['owner_id'], (int) $p['id'], 'reserve', 'reserve_hold', $reserve, "cp:$id:reserve", ['note' => $p['reserve_pct'] . '% tax & fee reserve']);
        }
        $pdo->prepare("UPDATE fa_client_payments SET status = 'completed', capture_id = ?, payer_email = ?, completed_at = NOW() WHERE id = ?")
            ->execute([(string) ($capture['id'] ?? ''), $payerEmail !== '' ? mb_substr($payerEmail, 0, 190) : null, $id]);
        return 'completed';
    });
    if ($result === 'completed') {
        $r = q('SELECT * FROM fa_client_payments WHERE id = ?', [$id])->fetch();
        agent_log((int) $r['owner_id'], 'paypal', 'client_payment_captured', ['request' => $id, 'amount' => money((int) $r['amount_cents'])]);
        try {
            plan_revenue_split($r);
        } catch (Throwable $e) {
            error_log('Konbit split after payment ' . $id . ': ' . $e->getMessage());
        }
    }
    return $result;
}

/* ------------------------------------------------------------ payouts */

/** Sends one Payouts batch. Returns [httpCode, response]. Idempotent by sender_batch_id. */
function paypal_payout_send(string $senderBatchId, string $subject, array $items): array
{
    return paypal_api('POST', '/v1/payments/payouts', [
        'sender_batch_header' => [
            'sender_batch_id' => $senderBatchId,
            'email_subject'   => mb_substr($subject, 0, 255),
            'email_message'   => 'Sent by Konbit, the AI paymaster for freelance crews, inside a mandate your lead approved.',
        ],
        'items' => $items,
    ]);
}

function paypal_payout_get(string $batchId): array
{
    return paypal_api('GET', '/v1/payments/payouts/' . rawurlencode($batchId) . '?page_size=50');
}

/* ----------------------------------------------------------- webhooks */

/** Verifies a webhook with PayPal's verify-webhook-signature API. */
function paypal_verify_webhook(string $rawBody): bool
{
    $webhookId = (string) cfg('paypal.webhook_id', '');
    if ($webhookId === '' || !paypal_ready()) {
        return false;
    }
    $h = fn (string $name) => (string) ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '');
    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        return false;
    }
    [$code, $res] = paypal_api('POST', '/v1/notifications/verify-webhook-signature', [
        'auth_algo'         => $h('PAYPAL-AUTH-ALGO'),
        'cert_url'          => $h('PAYPAL-CERT-URL'),
        'transmission_id'   => $h('PAYPAL-TRANSMISSION-ID'),
        'transmission_sig'  => $h('PAYPAL-TRANSMISSION-SIG'),
        'transmission_time' => $h('PAYPAL-TRANSMISSION-TIME'),
        'webhook_id'        => $webhookId,
        'webhook_event'     => $event,
    ]);
    return $code === 200 && ($res['verification_status'] ?? '') === 'SUCCESS';
}
