<?php
/** Konbit: the landing page when signed out, the app when signed in. */
declare(strict_types=1);

define('FA_APP_PAGE', 1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$me = current_user();
if (!$me) {
    require __DIR__ . '/landing.php';
    exit;
}

$bryntum = is_file(__DIR__ . '/assets/vendor/bryntum/scheduler.umd.js');
$css = ['vendor/ag-grid/ag-grid-theme.css'];
if ($bryntum) {
    $css = array_merge($css, ['vendor/bryntum/scheduler.css', 'vendor/bryntum/svalbard-light.css', 'vendor/bryntum/fontawesome/css/fontawesome.css', 'vendor/bryntum/fontawesome/css/solid.css']);
}
page_head('Konbit · ' . $me['name'], 'app', ['bryntum' => $bryntum ? '1' : '0'], array_filter($css, fn ($c) => is_file(__DIR__ . '/assets/' . $c)));
$icon = fn (string $d) => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
$nav = [
    'overview' => ['Overview', 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm8 0h8v-9h-8v9Zm0-16v5h8V4h-8Z'],
    'inbox'    => ['Work inbox', 'M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm0 12h-4a3 3 0 0 1-6 0H5V5h14v10Z'],
    'payouts'  => ['Payouts', 'M12 1 3 5v6c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V5l-9-4Zm-1.2 15.5L7 12.7l1.4-1.4 2.4 2.4 5.8-5.8L18 9.3l-7.2 7.2Z'],
    'schedule' => ['Schedule', 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 16H5V10h14v10ZM7 12h5v5H7v-5Z'],
    'ledger'   => ['Ledger', 'M3 3h18v4H3V3Zm0 6h18v4H3V9Zm0 6h18v6H3v-6Z'],
    'crew'     => ['Crew & projects', 'M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 2c-2.7 0-8 1.3-8 4v3h16v-3c0-2.7-5.3-4-8-4Zm8 0c-.3 0-.7 0-1.1.1A4.2 4.2 0 0 1 17 17v3h7v-3c0-2.7-5.3-4-8-4Z'],
    'mandate'  => ['Mandate', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm-1 7V3.5L18.5 9H13Zm-4.5 9L6 15.5l1.4-1.4 1.1 1.1 3.6-3.6 1.4 1.4-5 5Z'],
];
?>
<div class="shell">
  <aside class="side">
    <?= brand_html() ?>
    <nav class="nav" id="nav" aria-label="Main">
      <?php foreach ($nav as $key => [$label, $path]): ?>
        <button type="button" data-view="<?= e($key) ?>" class="<?= $key === 'overview' ? 'on' : '' ?>"><?= $icon($path) ?><span><?= e($label) ?></span><b class="nav-badge" data-badge="<?= e($key) ?>"></b></button>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div class="who"><span class="avatar"><?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></span><span><b><?= e($me['name']) ?></b><small><?= e($me['business_name'] ?: 'Freelancer') ?></small></span></div>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
    </div>
  </aside>

  <main class="main" id="main">
    <header class="top">
      <div class="top-brand"><?= brand_html() ?></div>
      <h1 id="viewTitle">Overview</h1>
      <div class="top-right">
        <span class="pill pill-sandbox" id="modePill">Sandbox</span>
        <span class="pill" id="mandatePill">Mandate</span>
      </div>
    </header>
    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <section class="view on" data-view="overview" id="v-overview"></section>
    <section class="view" data-view="inbox" id="v-inbox"></section>
    <section class="view" data-view="payouts" id="v-payouts"></section>
    <section class="view" data-view="schedule" id="v-schedule">
      <div class="view-head"><div><h2>Payout schedule</h2><p class="muted">Every payout and due milestone per crew member. Drag a waiting or scheduled payout to move its pay day; the server re-checks the mandate.</p></div>
        <div class="legend"><span class="lg ev-paid">Paid</span><span class="lg ev-sent">Sending</span><span class="lg ev-scheduled">Scheduled</span><span class="lg ev-needs_approval">Needs tap</span><span class="lg ev-blocked">Blocked</span><span class="lg ev-due">Due</span></div></div>
      <div id="scheduler" class="scheduler-box"></div>
      <p class="muted small" id="schedNote"></p>
    </section>
    <section class="view" data-view="ledger" id="v-ledger">
      <div class="view-head"><div><h2>Ledger &amp; audit</h2><p class="muted">Every cent in and out, every PayPal status and every agent decision.</p></div>
        <div class="seg" id="gridTabs"><button type="button" data-tab="items" class="on">Payout items</button><button type="button" data-tab="ledger">Money ledger</button><button type="button" data-tab="log">Agent audit</button></div></div>
      <div class="grid-tools"><input id="gridSearch" type="search" placeholder="Search every column…" aria-label="Search the table"><button class="btn btn-ghost" id="gridCsv" type="button">Export CSV</button></div>
      <div id="grid" class="grid-box"></div>
    </section>
    <section class="view" data-view="crew" id="v-crew"></section>
    <section class="view" data-view="mandate" id="v-mandate"></section>
  </main>
</div>

<nav class="bottom-nav" id="bottomNav" aria-label="Main">
  <?php foreach (['overview', 'inbox', 'payouts', 'schedule', 'crew'] as $key): ?>
    <button type="button" data-view="<?= e($key) ?>" class="<?= $key === 'overview' ? 'on' : '' ?>"><?= $icon($nav[$key][1]) ?><span><?= e(explode(' ', $nav[$key][0])[0]) ?></span><b class="nav-badge" data-badge="<?= e($key) ?>"></b></button>
  <?php endforeach; ?>
</nav>

<button class="agent-fab" id="agentFab" type="button" aria-controls="agentPanel" aria-expanded="false">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a2 2 0 0 1 2 2c0 .7-.4 1.4-1 1.7V7h5a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-8a3 3 0 0 1 3-3h5V5.7A2 2 0 0 1 12 2ZM8.5 12a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm7 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>
  <span>Ask Konbit</span>
</button>
<aside class="agent-panel" id="agentPanel" aria-label="Konbit agent" hidden>
  <header><div><b>Konbit agent</b><small>Claude · works inside your mandate</small></div><button type="button" class="icon-btn" id="agentReset" title="New conversation">↺</button><button type="button" class="icon-btn" id="agentClose" aria-label="Close">✕</button></header>
  <div class="agent-log" id="agentLog">
    <div class="msg bot">Hi! I can tell you who is owed what, create PayPal payment links for clients, and prepare payouts. Try: <button type="button" class="chip" data-ask="Who is still owed money, and how much can I pay out right now?">Who is owed money?</button> <button type="button" class="chip" data-ask="Create a $250 payment link for the Lakay Bakery website, memo: Milestone 2 landing page">Create a $250 pay link</button> <button type="button" class="chip" data-ask="Give Lucie a $20 bonus on the podcast for the fast edit">Bonus for Lucie</button></div>
  </div>
  <form class="agent-form" id="agentForm"><input id="agentInput" maxlength="800" placeholder="Ask about payouts, limits, clients…" autocomplete="off"><button class="btn btn-primary" type="submit">Send</button></form>
</aside>

<template id="tpl-empty"><div class="empty card"><h2>Start with the demo workspace</h2><p class="muted">Loads a 3-person crew, two client projects (milestones and a revenue split), a signed mandate and two PayPal payment links. Then pay a link with a PayPal sandbox buyer and feed the agent some work evidence.</p><button class="btn btn-primary" type="button" data-act="demo">Load demo workspace</button><p class="muted small">Or build your own in Crew &amp; projects.</p></div></template>
<?php
$scripts = ['vendor/ag-grid/ag-grid-community.min.js'];
if ($bryntum) {
    $scripts[] = 'vendor/bryntum/scheduler.umd.js';
}
foreach ($scripts as $s) {
    echo '<script src="' . e(url('assets/' . $s)) . '?v=' . FA_ASSET_VER . '" defer></script>' . "\n";
}
page_foot(['app.js', 'grid.js', 'schedule.js']);
