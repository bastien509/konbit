<?php
/** PayPal sends the client back here after approval; we capture server-side and credit once. */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/paypal.php';
require __DIR__ . '/inc/engine.php';
require __DIR__ . '/inc/agent.php';

$token = (string) ($_GET['r'] ?? '');
$orderId = (string) ($_GET['token'] ?? '');
$req = preg_match('/^[a-f0-9]{32}$/', $token) ? q('SELECT * FROM fa_client_payments WHERE pay_token = ?', [$token])->fetch() : null;
if (!$req) {
    http_response_code(404);
    exit('Payment not found.');
}
// The order PayPal returned must be the one we created for this request.
if ($orderId === '' || !hash_equals((string) $req['paypal_order_id'], $orderId)) {
    flash('This checkout does not match the payment request. Please start again.', 'error');
    redirect('pay.php?r=' . $token);
}
try {
    $r = client_payment_capture($req);
    flash($r === 'pending' ? 'PayPal is processing your payment.' : 'Payment received. Thank you!', 'ok');
} catch (AppError $e) {
    flash($e->getMessage(), 'error');
}
redirect('pay.php?r=' . $token);
