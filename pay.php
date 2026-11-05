<?php
/** Public client pay page: a client opens the freelancer's link and pays with PayPal. */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/paypal.php';

$token = (string) ($_GET['r'] ?? $_POST['r'] ?? '');
$req = preg_match('/^[a-f0-9]{32}$/', $token) ? q(
    'SELECT r.*, p.name AS project, p.client_name, u.name AS owner_name, u.business_name
     FROM fa_client_payments r JOIN fa_projects p ON p.id = r.project_id JOIN fa_users u ON u.id = r.owner_id WHERE r.pay_token = ?',
    [$token]
)->fetch() : null;

if (!$req) {
    http_response_code(404);
}

if ($req && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid() || !rate_limit('pay:' . client_ip(), 20, 600)) {
        flash('Please try again.', 'error');
        header('Location: ' . url('pay.php?r=' . $token), true, 303);
        exit;
    }
    try {
        $approve = client_payment_checkout($req);
        header('Location: ' . $approve, true, 303);
        exit;
    } catch (AppError $e) {
        flash($e->getMessage(), 'error');
        header('Location: ' . url('pay.php?r=' . $token), true, 303);
        exit;
    }
}

$from = $req ? ($req['business_name'] !== '' ? $req['business_name'] : $req['owner_name']) : '';
page_head($req ? 'Pay ' . $from . ' · Konbit' : 'Payment link not found', 'pay-page');
?>
<main class="pay-wrap">
  <div class="pay-brand"><?= logo_svg() ?><span>Konbit</span></div>
  <?= flash_html() ?>
  <?php if (!$req): ?>
    <section class="card pay-card"><h1>Payment link not found</h1><p class="muted">Ask the sender for a new link.</p></section>
  <?php else: ?>
    <section class="card pay-card">
      <p class="eyebrow">Payment request</p>
      <h1><?= e($from) ?></h1>
      <p class="muted"><?= e($req['project']) ?><?= $req['client_name'] !== '' ? ' · for ' . e($req['client_name']) : '' ?></p>
      <div class="pay-amount"><?= e(money((int) $req['amount_cents'], $req['currency'])) ?></div>
      <?php if ($req['memo'] !== ''): ?><p class="pay-memo"><?= e($req['memo']) ?></p><?php endif; ?>
      <?php if ($req['status'] === 'completed'): ?>
        <div class="pay-done">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
          <div><strong>Paid. Thank you!</strong><span>Received <?= e(gmdate('M j, Y', strtotime($req['completed_at'] . ' UTC'))) ?> through PayPal.</span></div>
        </div>
      <?php elseif ($req['status'] === 'pending'): ?>
        <div class="pay-done pending"><div><strong>Payment pending</strong><span>PayPal is still processing this payment.</span></div></div>
      <?php else: ?>
        <form method="post" action="<?= e(url('pay.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="r" value="<?= e($token) ?>">
          <button class="btn btn-paypal" type="submit">Pay with <b>PayPal</b></button>
        </form>
        <?php if (isset($_GET['cancel'])): ?><p class="muted center small">Checkout cancelled. Nothing was charged.</p><?php endif; ?>
      <?php endif; ?>
      <p class="pay-fine">Secured by PayPal. <?= paypal_mode() === 'sandbox' ? 'Sandbox demo: use a PayPal sandbox personal account, no real money moves.' : '' ?></p>
    </section>
    <p class="pay-foot">Your payment goes to <?= e($from) ?>. Konbit then pays their crew from it, inside limits they approved.</p>
  <?php endif; ?>
</main>
<?php
page_foot([]);
