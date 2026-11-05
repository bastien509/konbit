// Konbit app: one page, views rendered from /api.php?a=state.
// Every server string goes through esc() before it touches innerHTML:
// work evidence is untrusted text from outside systems.
(function () {
  'use strict';

  var BASE = document.body.dataset.base || '';
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var S = null;               // last state
  var view = 'overview';
  var payFilter = 'tap';
  var busy = false;
  var pollTimer = 0;

  var TITLES = { overview: 'Overview', inbox: 'Work inbox', payouts: 'Payouts', schedule: 'Schedule', ledger: 'Ledger & audit', crew: 'Crew & projects', mandate: 'Mandate' };
  var SOURCES = { github: ['GitHub', '⌥'], email: ['Email', '✉'], calendar: ['Calendar', '◷'], upwork: ['Upwork', 'U'], notion: ['Notion', 'N'], slack: ['Slack', '#'], api: ['API', '{}'], manual: ['Manual', '✎'], phishing: ['Email', '✉'] };
  var PLAN_STATUS = {
    needs_approval: ['Needs your tap', 'gold'], blocked: ['Blocked', 'red'], scheduled: ['Scheduled', 'blue'], sending: ['Sending', 'blue'],
    sent: ['Sent · PayPal processing', 'blue'], paid: ['Paid', 'green'], partial: ['Partly paid', 'gold'], failed: ['Failed', 'red'], rejected: ['Rejected', 'muted']
  };
  var ITEM_STATUS = { planned: 'planned', sent: 'sent', pending: 'pending', success: 'paid', unclaimed: 'unclaimed (sandbox email)', failed: 'failed', returned: 'returned', cancelled: 'cancelled' };
  var EVENT_STATUS = { new: ['New', 'muted'], analyzing: ['Reviewing…', 'blue'], done: ['Payout proposed', 'green'], ignored: ['No action', 'muted'], flagged: ['Suspicious', 'red'], error: ['Error', 'red'] };
  var BY = { agent: 'AI agent', rule: 'Split rule', owner: 'You' };

  /* ------------------------------------------------------------ utils */

  function esc(v) {
    return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(c) {
    var n = Number(c || 0) / 100;
    return (n < 0 ? '−$' : '$') + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function ago(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '';
    var s = Math.round((Date.now() - d) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return Math.round(s / 60) + ' min ago';
    if (s < 86400) return Math.round(s / 3600) + ' h ago';
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }
  function chip(text, cls) { return '<span class="chip-s ' + esc(cls) + '">' + esc(text) + '</span>'; }
  function crewById(id) { return (S.workspace.crew || []).filter(function (c) { return c.id === id; })[0] || { name: '#' + id, color: '#888' }; }

  function toast(msg, kind) {
    var t = $('#toast');
    t.textContent = msg;
    t.className = 'toast on ' + (kind || '');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { t.className = 'toast'; }, 4200);
  }

  function api(action, body) {
    var opt = { method: body ? 'POST' : 'GET', headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
    if (body) {
      opt.headers['Content-Type'] = 'application/json';
      opt.headers['X-CSRF-Token'] = CSRF;
      opt.body = JSON.stringify(body);
    }
    return fetch(BASE + '/api.php?a=' + encodeURIComponent(action), opt).then(function (res) {
      return res.json().catch(function () { return { ok: false, error: 'Unexpected server reply.' }; }).then(function (j) {
        if (j.login) { location.href = BASE + '/login.php'; }
        if (!j.ok) { throw new Error(j.error || 'Request failed.'); }
        if (j.csrf) { CSRF = j.csrf; }
        return j;
      });
    });
  }

  function act(btn, action, body, after) {
    if (btn) { btn.disabled = true; btn.classList.add('loading'); }
    return api(action, body || {}).then(function (j) {
      if (j.message) toast(j.message, 'ok');
      return refresh().then(function () { if (after) after(j); return j; });
    }).catch(function (e) {
      toast(e.message, 'err');
    }).then(function (j) {
      if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
      return j;
    });
  }

  /* ------------------------------------------------------------ state */

  function refresh() {
    return api('state').then(function (j) {
      S = j;
      render();
      return j;
    }).catch(function (e) { toast(e.message, 'err'); });
  }

  function schedulePoll() {
    clearTimeout(pollTimer);
    var hasOpen = S && S.plans.some(function (p) { return p.status === 'sent' || p.status === 'sending'; });
    pollTimer = setTimeout(function () {
      if (document.hidden || busy || ['schedule', 'ledger'].indexOf(view) >= 0) { schedulePoll(); return; }
      refresh().then(schedulePoll);
    }, hasOpen ? 6000 : 15000);
  }

  function setView(v) {
    view = v;
    $$('[data-view]').forEach(function (el) {
      if (el.tagName === 'SECTION') el.classList.toggle('on', el.dataset.view === v);
      else el.classList.toggle('on', el.dataset.view === v);
    });
    $('#viewTitle').textContent = TITLES[v];
    window.scrollTo(0, 0);
    try { sessionStorage.setItem('konbit-view', v); } catch (e) { /* private mode */ }
    if (['schedule', 'ledger', 'mandate'].indexOf(v) >= 0) tourFlag(v, true);
    document.dispatchEvent(new CustomEvent('konbit:view', { detail: v }));
    if (S) render();
  }

  function render() {
    var ws = S.workspace;
    var m = ws.mandate;
    var pill = $('#mandatePill');
    pill.textContent = m ? (m.status === 'active' ? 'Mandate on' : 'Mandate paused') : 'No mandate';
    pill.className = 'pill ' + (m ? (m.status === 'active' ? 'pill-on' : 'pill-warn') : 'pill-off');
    $('#modePill').textContent = S.paypal.mode === 'live' ? 'Live' : 'PayPal sandbox';
    var tap = S.plans.filter(function (p) { return p.status === 'needs_approval'; }).length;
    $$('[data-badge="payouts"]').forEach(function (b) { b.textContent = tap ? tap : ''; });
    var fresh = S.events.filter(function (e) { return e.status === 'flagged'; }).length;
    $$('[data-badge="inbox"]').forEach(function (b) { b.textContent = fresh ? '!' : ''; });
    if (view === 'overview') renderOverview();
    if (view === 'inbox') renderInbox();
    if (view === 'payouts') renderPayouts();
    if (view === 'crew') renderCrew();
    if (view === 'mandate') renderMandate();
    renderTourBar();
  }

  /* ------------------------------------------------------------- tour */

  // A guided checklist for judges and first-time users. Progress comes from real data where possible.
  function tourFlag(k, set) {
    try {
      if (set) localStorage.setItem('konbit-tour-' + k, '1');
      return localStorage.getItem('konbit-tour-' + k) === '1';
    } catch (e) { return false; }
  }
  function tourSteps() {
    var ev = function (src, status) { return S.events.some(function (e) { return (!src || e.source === src) && (!status || e.status === status); }); };
    var received = S.workspace.projects.reduce(function (a, p) { return a + p.received_cents; }, 0);
    return [
      { id: 'money', title: 'Client money is in', done: received > 0, view: 'overview',
        text: 'Clients paid ' + money(received) + ' for two projects. A tax reserve is held back, and only the rest can ever reach the crew.' },
      { id: 'auto', title: 'Send the agent proof of work', done: ev('calendar', 'done') || ev('email', 'done'), view: 'inbox', target: '[data-key="calendar"]',
        text: 'In Work inbox, tap Calendar ("copy approved"). Claude matches it to Lucie\'s $80 milestone. That is under the $150 auto-pay limit, so PayPal Payouts pays her by itself.' },
      { id: 'ask', title: 'Approve a bigger payout', done: S.plans.some(function (p) { return p.approved_by === 'owner'; }), view: 'inbox', target: '[data-key="github"]',
        text: 'Tap GitHub ("PR #42 merged"). Jean\'s $250 is above the auto-pay limit, so it waits in Payouts. Open Payouts and press Approve & pay.' },
      { id: 'trick', title: 'Try to trick the agent', done: ev(null, 'flagged'), view: 'inbox', target: '[data-key="phishing"]',
        text: 'Tap "URGENT: new PayPal for Jean". It asks for a new PayPal address and a $900 bonus. Watch it get flagged with nothing paid.' },
      { id: 'chat', title: 'Ask Konbit in plain words', done: tourFlag('chat'), view: 'chat',
        text: 'Open Ask Konbit (bottom right) and try "Who is still owed money?" or "Create a $250 payment link for the bakery".' },
      { id: 'views', title: 'See every cent', done: tourFlag('schedule') && tourFlag('ledger'), view: tourFlag('schedule') ? 'ledger' : 'schedule',
        text: 'Schedule (Bryntum) shows each person\'s payouts on a timeline; drag a waiting one to another day. Ledger (AG Grid) shows every PayPal status and every agent decision.' },
      { id: 'rules', title: 'Read the mandate', done: tourFlag('mandate'), view: 'mandate',
        text: 'The one rule set the agent obeys: Auto, Ask and Never. Pause it and every payout needs your tap.' },
      { id: 'paypal', title: 'Optional: pay a client link yourself', optional: true, view: 'overview', target: '.req .btn-paypal',
        done: S.requests.some(function (r) { return r.status === 'completed' && !r.seeded; }),
        text: 'Open a client payment link and pay with any PayPal sandbox personal account. The project fills up and the podcast\'s split is planned.' }
    ];
  }
  function tourVisible() {
    if (!S.workspace.projects.length) return false;
    return S.me.is_demo || !tourFlag('hidden');
  }
  function tourGo(step) {
    if (!step) return;
    if (step.view === 'chat') { openAgent(true); return; }
    setView(step.view);
    if (step.target) {
      setTimeout(function () {
        var el = document.querySelector(step.target);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('pulse');
        setTimeout(function () { el.classList.remove('pulse'); }, 4000);
      }, 120);
    }
  }
  function renderTourBar() {
    var bar = $('#tourBar');
    if (!bar) return;
    if (!tourVisible() || view === 'overview') { bar.hidden = true; return; }
    var steps = tourSteps().filter(function (s) { return !s.optional; });
    var next = steps.filter(function (s) { return !s.done; })[0];
    var done = steps.filter(function (s) { return s.done; }).length;
    bar.hidden = false;
    bar.innerHTML = next
      ? '<span class="tour-count">Tour ' + done + '/' + steps.length + '</span><span class="tour-next"><b>Next:</b> ' + esc(next.title) + '</span><button class="btn btn-primary btn-sm" type="button" data-tour="' + next.id + '">Show me</button><button class="link-btn" type="button" data-go="overview">All steps</button>'
      : '<span class="tour-count">Tour done</span><span class="tour-next">You have seen the whole flow. Reset the demo from Overview to start again.</span>';
  }
  function tourCard() {
    var steps = tourSteps();
    var req = steps.filter(function (s) { return !s.optional; });
    var done = req.filter(function (s) { return s.done; }).length;
    var nextId = (req.filter(function (s) { return !s.done; })[0] || {}).id;
    var list = steps.map(function (s, i) {
      return '<li class="tstep' + (s.done ? ' done' : '') + (s.id === nextId ? ' next' : '') + '"><i>' + (s.done ? '✓' : (s.optional ? '+' : i + 1)) + '</i><div><b>' + esc(s.title) + '</b><span>' + esc(s.text) + '</span></div>' +
        (s.done || s.id === 'money' ? '' : '<button class="btn ' + (s.id === nextId ? 'btn-primary' : 'btn-ghost') + ' btn-sm" type="button" data-tour="' + s.id + '">Show me</button>') + '</li>';
    }).join('');
    return '<section class="card tour"><header><div><p class="eyebrow">' + (S.me.is_demo ? 'Judge tour · about 3 minutes' : 'Getting started') + '</p><h2>' +
      (done === req.length ? 'You have seen the whole flow' : 'See Konbit pay a crew, safely') + '</h2>' +
      '<p class="muted small">Konbit pays a freelancer\'s crew with PayPal Payouts when work is proven, but only inside the mandate the freelancer signed. PayPal sandbox: test money only.</p></div>' +
      '<div class="tour-side"><div class="tour-ring" style="--p:' + Math.round(done / req.length * 100) + '"><b>' + done + '/' + req.length + '</b></div>' +
      (S.me.is_demo ? '<button class="btn btn-ghost btn-sm" type="button" data-act="demo-reset">Reset demo</button>' : '<button class="link-btn" type="button" data-act="tour-hide">Hide</button>') + '</div></header>' +
      '<ol class="tour-steps">' + list + '</ol></section>';
  }

  /* --------------------------------------------------------- overview */

  function totals() {
    var t = { received: 0, reserve: 0, paid: 0, held: 0, available: 0 };
    S.workspace.projects.forEach(function (p) {
      t.received += p.received_cents; t.reserve += p.reserve_cents; t.paid += p.paid_out_cents; t.held += p.held_cents; t.available += p.available_cents;
    });
    return t;
  }

  function planCard(p, compact) {
    var st = PLAN_STATUS[p.status] || [p.status, 'muted'];
    var items = p.items.map(function (i) {
      var c = crewById(i.crew_id);
      return '<li><span class="dot" style="background:' + esc(c.color) + '"></span><b>' + esc(i.name) + '</b><span class="muted small">' + esc(i.receiver) + '</span><span class="amt">' + money(i.amount_cents) + '</span>' +
        (p.status === 'sent' || p.status === 'paid' || p.status === 'partial' || p.status === 'failed' ? '<span class="istat ist-' + esc(i.status) + '">' + esc(ITEM_STATUS[i.status] || i.status) + '</span>' : '') + '</li>';
    }).join('');
    var checks = p.checks.map(function (c) {
      var icon = c.result === 'pass' ? '✓' : c.result === 'ask' ? '!' : '✕';
      return '<li class="ck ck-' + esc(c.result) + '"><i>' + icon + '</i><b>' + esc(c.rule) + '</b><span>' + esc(c.detail) + '</span></li>';
    }).join('');
    var actions = '';
    if (p.status === 'needs_approval') {
      actions = '<button class="btn btn-primary" data-act="approve" data-id="' + p.id + '">Approve &amp; pay ' + money(p.total_cents) + '</button><button class="btn btn-ghost" data-act="reject" data-id="' + p.id + '">Reject</button>';
    } else if (p.status === 'scheduled') {
      actions = '<button class="btn btn-ghost" data-act="approve" data-id="' + p.id + '">Pay now</button><button class="btn btn-ghost" data-act="reject" data-id="' + p.id + '">Cancel</button>';
    } else if (p.status === 'blocked') {
      actions = '<button class="btn btn-ghost" data-act="reject" data-id="' + p.id + '">Dismiss</button>';
    } else if (p.batch_id) {
      actions = '<button class="btn btn-ghost" data-act="sync" data-id="' + p.id + '">Refresh from PayPal</button><span class="muted small mono">Batch ' + esc(p.batch_id) + '</span>';
    }
    return '<article class="plan card plan-' + esc(p.status) + '">' +
      '<header><div><p class="eyebrow">' + esc(p.project) + ' · ' + esc(BY[p.created_by] || p.created_by) + ' · ' + esc(p.kind) + ' · ' + esc(ago(p.created_at)) + '</p><h3>' + esc(p.title) + '</h3></div>' +
      '<div class="plan-right"><span class="plan-total">' + money(p.total_cents) + '</span>' + chip(st[0], st[1]) + (p.pay_on ? chip('Pay on ' + p.pay_on, 'blue') : '') + '</div></header>' +
      '<ul class="plan-items">' + items + '</ul>' +
      (p.quote ? '<blockquote class="quote"><span>Evidence</span>“' + esc(p.quote) + '”</blockquote>' : '') +
      (p.reason && !compact ? '<p class="muted small">' + esc(p.reason) + '</p>' : '') +
      (compact ? '' : '<details class="checks"' + (p.status === 'blocked' || p.status === 'needs_approval' ? ' open' : '') + '><summary>Policy checks (' + p.checks.length + ')</summary><ul>' + checks + '</ul></details>') +
      (actions ? '<footer>' + actions + '</footer>' : '') +
      '</article>';
  }

  function renderOverview() {
    var el = $('#v-overview');
    var ws = S.workspace;
    if (!ws.projects.length) {
      el.innerHTML = '';
      el.appendChild($('#tpl-empty').content.cloneNode(true));
      return;
    }
    var t = totals();
    var tourTop = tourVisible() ? tourCard() : '';
    var tap = S.plans.filter(function (p) { return p.status === 'needs_approval'; });
    var recent = S.plans.filter(function (p) { return p.status !== 'needs_approval'; }).slice(0, 4);
    var m = ws.mandate;
    var kpi = function (label, val, sub, cls) { return '<div class="kpi card ' + (cls || '') + '"><span>' + esc(label) + '</span><b>' + val + '</b><small>' + esc(sub) + '</small></div>'; };
    var html = tourTop + '<div class="kpis">' +
      kpi('Received from clients', money(t.received), S.requests.some(function (r) { return r.seeded; }) ? 'Includes seeded demo funds' : 'Captured with PayPal') +
      kpi('Paid to crew', money(t.paid), 'PayPal Payouts', 'kpi-green') +
      kpi('Free to pay out', money(t.available), 'After reserve and holds', 'kpi-blue') +
      kpi('Tax & fee reserve', money(t.reserve), 'Never paid out') +
      '</div>';
    html += '<div class="cols"><div class="col-main">';
    html += '<div class="sec-head"><h2>Needs your tap</h2>' + (tap.length ? chip(tap.length + ' waiting', 'gold') : '') + '</div>';
    html += tap.length ? tap.slice(0, 3).map(function (p) { return planCard(p, true); }).join('') + (tap.length > 3 ? '<button class="btn btn-ghost" data-go="payouts">See all ' + tap.length + '</button>' : '')
      : '<div class="card calm"><b>All clear.</b><span class="muted">The agent pays small, proven payouts on its own and puts the rest here.</span></div>';
    html += '<div class="sec-head"><h2>Latest payouts</h2><button class="link-btn" data-go="payouts">All payouts</button></div>';
    html += recent.length ? recent.map(function (p) { return planCard(p, true); }).join('') : '<p class="muted">No payouts yet. Pay a client link, then send the agent some work evidence from the Work inbox.</p>';
    html += '</div><div class="col-side">';
    html += '<div class="card mandate-mini"><p class="eyebrow">Mandate</p>' + (m ?
      '<h3>' + (m.status === 'active' ? 'Agent may auto-pay under ' + money(m.auto_below_cents) : 'Paused: you approve everything') + '</h3>' +
      meter(m.used_30d_cents, m.month_cap_cents, 'used in 30 days') +
      '<p class="muted small">Max ' + money(m.max_payout_cents) + ' per payout · ' + money(m.max_recipient_month_cents) + ' per person / 30 days</p><button class="link-btn" data-go="mandate">Change limits</button>'
      : '<h3>No mandate yet</h3><p class="muted small">Without one, every payout waits for you.</p><button class="btn btn-primary" data-go="mandate">Sign a mandate</button>') + '</div>';
    html += '<div class="sec-head"><h2>Client payment links</h2></div>';
    html += S.requests.slice(0, 5).map(function (r) {
      var paid = r.status === 'completed';
      return '<div class="card req"><div><b>' + money(r.amount_cents) + '</b> ' + chip(r.seeded ? 'Demo funds' : paid ? 'Paid' : r.status === 'pending' ? 'Pending' : 'Open', paid ? 'green' : 'muted') +
        '<p class="small">' + esc(r.project) + (r.memo ? ' · ' + esc(r.memo) : '') + '</p></div>' +
        (paid ? '<span class="muted small">' + esc(ago(r.paid_at)) + '</span>' : '<div class="req-acts"><a class="btn btn-paypal btn-sm" href="' + esc(r.url) + '" target="_blank" rel="noopener">Open pay page</a><button class="icon-btn" data-copy="' + esc(r.url) + '" title="Copy link">⧉</button></div>') + '</div>';
    }).join('') || '<p class="muted small">Create one in Crew &amp; projects or ask the agent.</p>';
    html += '<div class="sec-head"><h2>Projects</h2></div>';
    html += ws.projects.map(function (p) {
      return '<div class="card proj-mini"><b>' + esc(p.name) + '</b><span class="muted small">' + esc(p.client) + '</span>' +
        meter(p.paid_out_cents + p.held_cents, Math.max(1, p.received_cents - p.reserve_cents), 'paid of ' + money(p.received_cents - p.reserve_cents) + ' payable') +
        '<span class="small">Free now: <b>' + money(p.available_cents) + '</b></span></div>';
    }).join('');
    html += '</div></div>';
    el.innerHTML = html;
  }

  function meter(used, cap, label) {
    var pct = cap > 0 ? Math.min(100, Math.round(used / cap * 100)) : 0;
    return '<div class="meter"><i style="width:' + pct + '%"></i></div><p class="small muted">' + money(used) + ' ' + esc(label) + (label.indexOf('of') < 0 ? ' of ' + money(cap) : '') + '</p>';
  }

  /* ------------------------------------------------------------ inbox */

  function renderInbox() {
    var el = $('#v-inbox');
    if (!S.workspace.projects.length) { el.innerHTML = ''; el.appendChild($('#tpl-empty').content.cloneNode(true)); return; }
    var samples = S.samples.map(function (s) {
      var src = SOURCES[s.key] || SOURCES.api;
      return '<button class="sample card" data-act="sample" data-key="' + esc(s.key) + '"><i>' + esc(src[1]) + '</i><span><b>' + esc(src[0]) + '</b>' + esc(s.title) + '</span></button>';
    }).join('');
    var events = S.events.map(function (e) {
      var st = EVENT_STATUS[e.status] || [e.status, 'muted'];
      var src = SOURCES[e.source] || SOURCES.api;
      var plans = S.plans.filter(function (p) { return p.event_id === e.id; });
      return '<article class="event card ev-' + esc(e.status) + '"><header><i class="src">' + esc(src[1]) + '</i><div><p class="eyebrow">' + esc(src[0]) + ' · ' + esc(ago(e.at)) + '</p><h3>' + esc(e.title || 'Work evidence') + '</h3></div>' +
        '<div class="ev-chips">' + chip(st[0], st[1]) + (e.risk ? chip('Risk ' + e.risk, e.risk === 'high' ? 'red' : e.risk === 'medium' ? 'gold' : 'green') : '') + '</div></header>' +
        (e.summary ? '<p class="agent-says"><b>Agent:</b> ' + esc(e.summary) + '</p>' : '') +
        (plans.length ? '<div class="ev-plans">' + plans.map(function (p) { var s = PLAN_STATUS[p.status] || [p.status]; return '<button class="link-btn" data-go="payouts">' + esc(p.title) + ' · ' + money(p.total_cents) + ' · ' + esc(s[0]) + '</button>'; }).join('') + '</div>' : '') +
        '<details><summary>Raw evidence (untrusted)</summary><pre>' + esc(e.body) + '</pre></details>' +
        (e.status === 'error' || e.status === 'new' ? '<footer><button class="btn btn-ghost" data-act="analyze" data-id="' + e.id + '">Review again</button></footer>' : '') +
        '</article>';
    }).join('');
    el.innerHTML = '<div class="view-head"><div><h2>Work inbox</h2><p class="muted">Proof of work arrives here from email, GitHub, calendar, Upwork or the <button class="link-btn" data-go="mandate">webhook API</button>. Claude reads it, matches it to a milestone and proposes a payout. The server verifies the quote and the mandate.</p></div></div>' +
      '<p class="eyebrow">Try a sample event</p><div class="samples">' + samples + '</div>' +
      '<details class="card paste"><summary>Paste your own evidence</summary><form id="pasteForm" class="form"><div class="row2"><label>Source<select name="source"><option value="email">Email</option><option value="github">GitHub</option><option value="calendar">Calendar</option><option value="upwork">Upwork</option><option value="notion">Notion</option><option value="slack">Slack</option><option value="manual">Manual note</option></select></label><label>Title<input name="title" maxlength="160" placeholder="Re: logo files"></label></div><label>Text<textarea name="body" rows="5" maxlength="6000" required placeholder="Paste an email, a PR description, meeting notes…"></textarea></label><button class="btn btn-primary" type="submit">Send to agent</button></form></details>' +
      '<div class="sec-head"><h2>Reviewed evidence</h2></div>' + (events || '<p class="muted">Nothing yet. Tap a sample above.</p>');
  }

  /* ---------------------------------------------------------- payouts */

  function renderPayouts() {
    var el = $('#v-payouts');
    var groups = {
      tap: function (p) { return p.status === 'needs_approval'; },
      sched: function (p) { return p.status === 'scheduled'; },
      done: function (p) { return ['sent', 'sending', 'paid', 'partial', 'failed'].indexOf(p.status) >= 0; },
      blocked: function (p) { return p.status === 'blocked' || p.status === 'rejected'; },
      all: function () { return true; }
    };
    var labels = { tap: 'Needs tap', sched: 'Scheduled', done: 'Sent & paid', blocked: 'Blocked', all: 'All' };
    var seg = Object.keys(groups).map(function (k) {
      var n = S.plans.filter(groups[k]).length;
      return '<button type="button" data-filter="' + k + '" class="' + (payFilter === k ? 'on' : '') + '">' + labels[k] + (n ? ' <b>' + n + '</b>' : '') + '</button>';
    }).join('');
    var list = S.plans.filter(groups[payFilter]);
    el.innerHTML = '<div class="view-head"><div><h2>Payouts</h2><p class="muted">Every plan shows who proposed it, the evidence, and each policy check. Auto means the mandate allowed it; you approve the rest.</p></div><div class="seg" id="payFilter">' + seg + '</div></div>' +
      (list.length ? list.map(function (p) { return planCard(p, false); }).join('') : '<p class="muted">Nothing here.</p>');
  }

  /* ------------------------------------------------------------- crew */

  function renderCrew() {
    var el = $('#v-crew');
    var ws = S.workspace;
    var crewOpts = ws.crew.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('');
    var html = '<div class="view-head"><div><h2>Crew</h2><p class="muted">Your payout allowlist. The agent can only pay these people, and only by id: it never sees or chooses an email. A changed PayPal email waits 24 h before auto-pay.</p></div></div>';
    html += '<div class="crew-grid">' + ws.crew.map(function (c) {
      return '<form class="card crew" data-crew="' + c.id + '"><div class="crew-top"><span class="avatar" style="background:' + esc(c.color) + '">' + esc(c.name.charAt(0)) + '</span><div><input name="name" value="' + esc(c.name) + '" maxlength="80" aria-label="Name"><input name="role" value="' + esc(c.role) + '" maxlength="60" aria-label="Role" class="muted"></div></div>' +
        '<label class="small">PayPal email<input name="paypal_email" type="email" value="' + esc(c.paypal_email) + '" maxlength="190"></label>' + (c.email_hold ? chip('New email · 24 h hold', 'gold') : '') +
        '<div class="crew-acts"><button class="btn btn-ghost btn-sm" type="submit">Save</button><button class="link-btn danger" type="button" data-act="crew-remove" data-id="' + c.id + '">Remove</button></div></form>';
    }).join('') +
      '<form class="card crew add" id="crewAdd"><b>Add crew member</b><input name="name" placeholder="Name" maxlength="80" required><input name="role" placeholder="Role" maxlength="60"><input name="paypal_email" type="email" placeholder="PayPal email (sandbox personal account)" maxlength="190" required><button class="btn btn-primary btn-sm" type="submit">Add</button></form></div>';

    html += '<div class="view-head"><div><h2>Projects</h2><p class="muted">Milestones are fixed fees released by work evidence. Splits share every client payment automatically.</p></div></div>';
    html += ws.projects.map(function (p) {
      var ms = p.milestones.map(function (m) {
        var c = crewById(m.crew_id);
        var st = { open: ['Open', 'muted'], in_payout: ['In payout', 'blue'], paid: ['Paid', 'green'] }[m.status] || [m.status, 'muted'];
        return '<li><span class="dot" style="background:' + esc(c.color) + '"></span><span><b>' + esc(m.title) + '</b><small class="muted">' + esc(c.name) + (m.due_on ? ' · due ' + esc(m.due_on) : '') + '</small></span><span class="amt">' + money(m.amount_cents) + '</span>' + chip(st[0], st[1]) +
          (m.status === 'open' ? '<button class="btn btn-ghost btn-sm" data-act="ms-pay" data-id="' + m.id + '">Pay now</button>' : '') + '</li>';
      }).join('');
      var splits = p.splits.map(function (s) { var c = crewById(s.crew_id); return '<li><span class="dot" style="background:' + esc(c.color) + '"></span><b>' + esc(c.name) + '</b><span class="amt">' + esc(s.share_pct) + '%</span></li>'; }).join('');
      var reqs = S.requests.filter(function (r) { return r.project === p.name; }).slice(0, 4).map(function (r) {
        return '<li><b>' + money(r.amount_cents) + '</b><span class="small">' + esc(r.memo) + '</span>' + chip(r.status === 'completed' ? 'Paid' : 'Open', r.status === 'completed' ? 'green' : 'muted') + (r.status !== 'completed' ? '<button class="icon-btn" data-copy="' + esc(r.url) + '" title="Copy link">⧉</button>' : '') + '</li>';
      }).join('');
      return '<section class="card project"><header><div><h3>' + esc(p.name) + '</h3><p class="muted small">' + esc(p.client) + ' · reserve ' + p.reserve_pct + '%</p></div>' +
        '<div class="proj-nums"><span>Received <b>' + money(p.received_cents) + '</b></span><span>Paid <b>' + money(p.paid_out_cents) + '</b></span><span>Free <b>' + money(p.available_cents) + '</b></span></div></header>' +
        '<div class="proj-cols"><div><p class="eyebrow">Milestones</p><ul class="rows">' + (ms || '<li class="muted small">None yet</li>') + '</ul>' +
        '<form class="inline-form" data-form="milestone" data-project="' + p.id + '"><select name="crew_id" required>' + crewOpts + '</select><input name="title" placeholder="Deliverable" maxlength="140" required><input name="amount" placeholder="Fee $" inputmode="decimal" required><button class="btn btn-ghost btn-sm">Add</button></form></div>' +
        '<div><p class="eyebrow">Revenue split</p><ul class="rows">' + (splits || '<li class="muted small">No split</li>') + '</ul>' +
        '<form class="inline-form" data-form="split" data-project="' + p.id + '"><select name="crew_id" required>' + crewOpts + '</select><input name="share_pct" placeholder="%" inputmode="decimal" required><button class="btn btn-ghost btn-sm">Set</button></form>' +
        '<p class="eyebrow">Client payment links</p><ul class="rows">' + (reqs || '<li class="muted small">None yet</li>') + '</ul>' +
        '<form class="inline-form" data-form="request" data-project="' + p.id + '"><input name="amount" placeholder="Amount $" inputmode="decimal" required><input name="memo" placeholder="Memo for the client" maxlength="200"><button class="btn btn-ghost btn-sm">Create link</button></form></div></div></section>';
    }).join('');
    html += '<form class="card inline-form wide" data-form="project"><b>New project</b><input name="name" placeholder="Project name" maxlength="120" required><input name="client" placeholder="Client" maxlength="120"><input name="reserve_pct" placeholder="Reserve %" value="15" inputmode="numeric"><button class="btn btn-primary btn-sm">Create</button></form>';
    if (!ws.projects.length) {
      html = '<div class="empty-wrap"></div>' + html;
    }
    el.innerHTML = html;
    if (!ws.projects.length) $('.empty-wrap', el).appendChild($('#tpl-empty').content.cloneNode(true));
  }

  /* ---------------------------------------------------------- mandate */

  function renderMandate() {
    var el = $('#v-mandate');
    var m = S.workspace.mandate;
    var d = function (c, f) { return m ? (m[c] / 100).toFixed(0) : f; };
    var hookUrl = location.origin + BASE + '/hook.php';
    var html = '<div class="view-head"><div><h2>Payout mandate</h2><p class="muted">The one rule set the agent obeys. Three zones: <b>auto</b> (agent pays alone), <b>ask</b> (you tap) and <b>never</b> (hard caps). The server enforces it on every payout, twice.</p></div></div>';
    if (m) {
      var used = m.used_per_crew_cents || {};
      html += '<div class="card mandate-now"><div class="mandate-top"><div><p class="eyebrow">Signed · expires ' + esc(new Date(m.expires_at).toLocaleDateString()) + '</p><h3>' + (m.status === 'active' ? 'Active' : 'Paused') + '</h3></div><div class="mandate-acts">' +
        (m.status === 'active' ? '<button class="btn btn-ghost" data-act="mandate" data-s="paused">Pause</button>' : '<button class="btn btn-primary" data-act="mandate" data-s="active">Resume</button>') +
        '<button class="btn btn-ghost danger" data-act="mandate" data-s="revoked">Revoke</button></div></div>' +
        '<div class="zones"><div class="zone z-auto"><b>Auto</b><span>under ' + money(m.auto_below_cents) + '</span></div><div class="zone z-ask"><b>Ask</b><span>' + money(m.auto_below_cents) + ' – ' + money(m.max_payout_cents) + ', bonuses, new emails, red flags</span></div><div class="zone z-never"><b>Never</b><span>over ' + money(m.max_payout_cents) + ' · over ' + money(m.max_recipient_month_cents) + '/person · over ' + money(m.month_cap_cents) + '/30 days · anyone off the crew list</span></div></div>' +
        meter(m.used_30d_cents, m.month_cap_cents, 'committed in the last 30 days') +
        '<ul class="rows">' + S.workspace.crew.map(function (c) { return '<li><span class="dot" style="background:' + esc(c.color) + '"></span><b>' + esc(c.name) + '</b><span class="amt">' + money(used[c.id] || 0) + ' / ' + money(m.max_recipient_month_cents) + '</span></li>'; }).join('') + '</ul>' +
        '<blockquote class="quote terms"><span>Signed terms</span>' + esc(m.terms) + '</blockquote></div>';
    }
    html += '<form class="card form" id="mandateForm"><h3>' + (m ? 'Sign new limits' : 'Sign your mandate') + '</h3><div class="row4">' +
      '<label>Auto-pay below ($)<input name="auto_below" inputmode="decimal" value="' + d('auto_below_cents', '150') + '" required></label>' +
      '<label>Max per payout ($)<input name="max_payout" inputmode="decimal" value="' + d('max_payout_cents', '400') + '" required></label>' +
      '<label>Per person / 30 days ($)<input name="max_recipient_month" inputmode="decimal" value="' + d('max_recipient_month_cents', '800') + '" required></label>' +
      '<label>Total / 30 days ($)<input name="month_cap" inputmode="decimal" value="' + d('month_cap_cents', '2000') + '" required></label></div>' +
      '<label>Lasts<select name="days"><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option></select></label>' +
      '<p class="muted small">Signing replaces the current mandate. Lowering limits is the safe direction; you can pause any time.</p><button class="btn btn-primary" type="submit">Sign mandate</button></form>';
    html += '<div class="card form"><h3>Work-event webhook</h3><p class="muted small">Let Zapier, GitHub Actions, Notion or an email forwarder send evidence to the agent. Spec: <a href="' + esc(BASE) + '/openapi.yaml" target="_blank" rel="noopener">openapi.yaml</a> (import it into APIMatic to generate SDKs).</p>' +
      '<p>Key: <span class="mono">' + esc(S.me.hook_hint || 'none yet') + '</span> <button class="btn btn-ghost btn-sm" data-act="hook-key">' + (S.me.hook_hint ? 'Rotate key' : 'Create key') + '</button></p><div id="hookKey"></div>' +
      '<pre class="code">curl -X POST ' + esc(hookUrl) + ' \\\n  -H "X-Konbit-Key: kb_…" -H "Content-Type: application/json" \\\n  -d \'{"source":"github","title":"PR #42 merged","text":"Landing page merged and live"}\'</pre></div>';
    el.innerHTML = html;
  }

  /* ------------------------------------------------------------ agent */

  var log = $('#agentLog');
  function addMsg(who, html) {
    var d = document.createElement('div');
    d.className = 'msg ' + who;
    d.innerHTML = html;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
    return d;
  }
  function cardHtml(c) {
    if (c.type === 'plan') {
      var p = S.plans.filter(function (x) { return x.id === c.plan_id; })[0];
      return p ? planCard(p, true) : '';
    }
    if (c.type === 'paylink') {
      return '<div class="card mini-card"><p class="eyebrow">PayPal payment link</p><b>' + esc(c.amount) + '</b> <span class="small">' + esc(c.project) + ' · ' + esc(c.memo) + '</span><div class="req-acts"><a class="btn btn-paypal btn-sm" href="' + esc(c.url) + '" target="_blank" rel="noopener">Open pay page</a><button class="icon-btn" data-copy="' + esc(c.url) + '" title="Copy link">⧉</button></div></div>';
    }
    if (c.type === 'mandate') {
      return '<div class="card mini-card"><b>Mandate ' + esc(S.workspace.mandate ? S.workspace.mandate.status : 'revoked') + '</b> <button class="link-btn" data-go="mandate">Open mandate</button></div>';
    }
    return '';
  }
  function ask(text) {
    if (busy || !text) return;
    busy = true;
    addMsg('me', esc(text));
    tourFlag('chat', true);
    var wait = addMsg('bot typing', '<span></span><span></span><span></span>');
    api('chat', { text: text }).then(function (j) {
      return refresh().then(function () {
        wait.remove();
        var m = addMsg('bot', esc(j.reply).replace(/\n/g, '<br>'));
        (j.cards || []).forEach(function (c) { m.insertAdjacentHTML('beforeend', cardHtml(c)); });
      });
    }).catch(function (e) {
      wait.remove();
      addMsg('bot err', esc(e.message));
    }).then(function () { busy = false; });
  }
  function openAgent(open) {
    var p = $('#agentPanel');
    p.hidden = !open;
    $('#agentFab').setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('agent-open', open);
    if (open) $('#agentInput').focus();
  }

  /* ----------------------------------------------------------- events */

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-tour],[data-view],[data-go],[data-act],[data-filter],[data-copy],[data-ask]');
    if (!t) return;
    if (t.dataset.tour) { tourGo(tourSteps().filter(function (s) { return s.id === t.dataset.tour; })[0]); return; }
    if (t.dataset.view && t.tagName === 'BUTTON') { setView(t.dataset.view); return; }
    if (t.dataset.go) { setView(t.dataset.go); if (t.dataset.go === 'payouts') payFilter = 'all'; if (S) render(); return; }
    if (t.dataset.filter) { payFilter = t.dataset.filter; renderPayouts(); return; }
    if (t.dataset.copy) {
      (navigator.clipboard ? navigator.clipboard.writeText(t.dataset.copy) : Promise.reject()).then(function () { toast('Link copied.', 'ok'); }, function () { window.prompt('Copy this link:', t.dataset.copy); });
      return;
    }
    if (t.dataset.ask) { ask(t.dataset.ask); return; }
    var id = Number(t.dataset.id || 0);
    switch (t.dataset.act) {
      case 'demo': act(t, 'demo_seed'); break;
      case 'demo-reset':
        if (confirm('Reset the demo workspace? Everything in it is replaced with a fresh, funded demo.')) {
          ['chat', 'schedule', 'ledger', 'mandate'].forEach(function (k) { try { localStorage.removeItem('konbit-tour-' + k); } catch (e) { /* private mode */ } });
          $$('.msg', log).slice(1).forEach(function (m) { m.remove(); });
          act(t, 'demo_reset');
        }
        break;
      case 'tour-hide': tourFlag('hidden', true); render(); break;
      case 'approve': act(t, 'plan_approve', { id: id }); break;
      case 'reject': act(t, 'plan_reject', { id: id }); break;
      case 'sync': act(t, 'plan_sync', { id: id }); break;
      case 'analyze': reviewing(t, act(t, 'event_analyze', { id: id })); break;
      case 'sample': reviewing(t, act(t, 'event_create', { sample: t.dataset.key }, reviewToast)); break;
      case 'ms-pay': act(t, 'milestone_pay', { id: id }); break;
      case 'crew-remove': if (confirm('Remove this person from your crew? They can no longer be paid.')) act(t, 'crew_remove', { id: id }); break;
      case 'mandate': if (t.dataset.s !== 'revoked' || confirm('Revoke the mandate? Every payout will need your tap.')) act(t, 'mandate_state', { status: t.dataset.s }); break;
      case 'hook-key':
        act(t, 'hook_key', {}, function (j) { var box = $('#hookKey'); if (box) box.innerHTML = '<pre class="code key">' + esc(j.key) + '</pre><p class="small muted">Shown once. Store it in your automation tool.</p>'; });
        break;
    }
  });

  function reviewing(btn, promise) {
    busy = true;
    var note = document.createElement('div');
    note.className = 'reviewing card';
    note.innerHTML = '<span class="spinner"></span><div><b>Claude is reviewing the evidence…</b><small class="muted">Reading your workspace, matching milestones, checking for tricks. About 10–30 seconds.</small></div>';
    $('#v-inbox').prepend(note);
    promise.then(function () { note.remove(); busy = false; });
  }
  function reviewToast(j) {
    var r = j.review;
    if (!r) return;
    var plans = S.plans.filter(function (p) { return r.plans.indexOf(p.id) >= 0; });
    var paid = plans.some(function (p) { return ['sent', 'paid'].indexOf(p.status) >= 0; });
    toast(r.status === 'flagged' ? 'Agent flagged this as suspicious. Nothing was paid.' : paid ? 'Proven and inside the mandate: paid with PayPal Payouts.' : plans.length ? 'Payout proposed. Check Payouts.' : 'Reviewed: no payout needed.', r.status === 'flagged' ? 'err' : 'ok');
  }

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var data = {};
    new FormData(f).forEach(function (v, k) { data[k] = v; });
    var btn = f.querySelector('button[type="submit"], button:not([type])');
    if (f.id === 'agentForm') { ev.preventDefault(); var i = $('#agentInput'); ask(i.value.trim()); i.value = ''; return; }
    if (f.id === 'pasteForm') { ev.preventDefault(); reviewing(btn, act(btn, 'event_create', data, reviewToast)); return; }
    if (f.id === 'crewAdd') { ev.preventDefault(); act(btn, 'crew_add', data); return; }
    if (f.id === 'mandateForm') { ev.preventDefault(); act(btn, 'mandate_sign', data); return; }
    if (f.dataset.crew) { ev.preventDefault(); data.id = Number(f.dataset.crew); act(btn, 'crew_update', data); return; }
    if (f.dataset.form) {
      ev.preventDefault();
      data.project_id = Number(f.dataset.project || 0);
      var map = { milestone: 'milestone_add', split: 'split_set', request: 'request_create', project: 'project_add' };
      act(btn, map[f.dataset.form], data, function (j) {
        if (j && j.url) { addMsg('bot', 'Payment link ready: ' + cardHtml({ type: 'paylink', url: j.url, amount: '$' + data.amount, project: '', memo: data.memo || '' })); toast('Payment link ready. Copy it from the project card.', 'ok'); }
      });
    }
  });

  $('#agentFab').addEventListener('click', function () { openAgent($('#agentPanel').hidden); });
  $('#agentClose').addEventListener('click', function () { openAgent(false); });
  $('#agentReset').addEventListener('click', function () {
    api('chat_reset', {}).then(function () { $$('.msg', log).slice(1).forEach(function (m) { m.remove(); }); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') openAgent(false); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden && S) refresh(); });

  window.Konbit = { api: api, toast: toast, refresh: refresh, esc: esc, money: money, state: function () { return S; } };

  try { var saved = sessionStorage.getItem('konbit-view'); if (saved && TITLES[saved]) view = saved; } catch (e) { /* private mode */ }
  refresh().then(function () { setView(view); schedulePoll(); });
})();
