<?php
/**
 * PayPal webhook. The signature is verified with PayPal before anything
 * changes; every handler is idempotent.
 * - CHECKOUT.ORDER.APPROVED / PAYMENT.CAPTURE.COMPLETED: client paid (covers a lost redirect).
 * - PAYMENT.PAYOUTS-ITEM.*: a crew payout changed state.
 */
declare(strict_types=1);

define('FA_NO_SESSION', 1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/paypal.php';
require __DIR__ . '/inc/engine.php';
require __DIR__ . '/inc/agent.php';

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rate_limit('ppwebhook:' . client_ip(), 120, 60)) {
    http_response_code(405);
    exit('{}');
}
$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 200000 || !paypal_verify_webhook($raw)) {
    http_response_code(400);
    exit('{}');
}
$event = json_decode($raw, true);
$type = (string) ($event['event_type'] ?? '');
$res = $event['resource'] ?? [];

try {
    if ($type === 'CHECKOUT.ORDER.APPROVED') {
        $req = q('SELECT * FROM fa_client_payments WHERE paypal_order_id = ?', [(string) ($res['id'] ?? '')])->fetch();
        if ($req) {
            client_payment_capture($req);
        }
    } elseif ($type === 'PAYMENT.CAPTURE.COMPLETED') {
        $ppOrder = (string) ($res['supplementary_data']['related_ids']['order_id'] ?? '');
        $req = q('SELECT * FROM fa_client_payments WHERE paypal_order_id = ?', [$ppOrder])->fetch();
        if ($req && (string) ($res['custom_id'] ?? '') === (string) $req['id']) {
            client_payment_credit((int) $req['id'], $res);
        }
    } elseif (str_starts_with($type, 'PAYMENT.PAYOUTS-ITEM.')) {
        $sid = (string) ($res['payout_item']['sender_item_id'] ?? '');
        if (preg_match('/^fa-item-(\d+)$/', $sid, $m)) {
            $item = q('SELECT i.id, i.plan_id, p.paypal_batch_id FROM fa_plan_items i JOIN fa_plans p ON p.id = i.plan_id WHERE i.id = ?', [(int) $m[1]])->fetch();
            // The batch id must match, so a valid event for another app can't touch our rows.
            if ($item && $item['paypal_batch_id'] === (string) ($res['payout_batch_id'] ?? '')) {
                plan_item_apply((int) $item['id'], (string) ($res['transaction_status'] ?? 'PENDING'), $res['payout_item_id'] ?? null);
                plan_refresh_status((int) $item['plan_id']);
            }
        }
    }
} catch (Throwable $e) {
    error_log('Konbit PayPal webhook ' . $type . ': ' . $e->getMessage());
}
http_response_code(200);
echo '{}';
