<?php
/** Shared shell for the sign-in and sign-up pages. */
declare(strict_types=1);

defined('FA') || exit;

function auth_shell_open(string $title, string $heading, string $sub): void
{
    page_head($title . ' · Konbit', 'auth-page');
    ?>
<main class="auth-wrap">
  <aside class="auth-side" aria-hidden="true">
    <?= brand_html() ?>
    <h2>Your crew gets paid the moment the work is done.</h2>
    <ul class="auth-points">
      <li><b>1</b>Client pays your PayPal link.</li>
      <li><b>2</b>The agent reads the proof: a merged PR, an approval email.</li>
      <li><b>3</b>PayPal Payouts pays your crew, inside the mandate you signed.</li>
    </ul>
  </aside>
  <section class="auth-card card">
    <div class="auth-mobile-brand"><?= brand_html() ?></div>
    <h1><?= e($heading) ?></h1>
    <p class="muted"><?= e($sub) ?></p>
    <?= flash_html() ?>
<?php
}

function auth_shell_close(): void
{
    echo "  </section>\n</main>\n";
    page_foot([]);
}
