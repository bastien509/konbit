// Ledger & audit view: one AG Grid with three datasets (payout items, money ledger, agent audit).
(function () {
  'use strict';

  if (!window.agGrid) return;
  var K = function () { return window.Konbit; };
  var $ = function (s) { return document.querySelector(s); };
  if (agGrid.ModuleRegistry && agGrid.AllCommunityModule) {
    agGrid.ModuleRegistry.registerModules([agGrid.AllCommunityModule]);
  }
  // Konbit is light only: white grid, Haiti-blue header, red accent on hover.
  var theme = agGrid.themeQuartz.withParams({
    browserColorScheme: 'light', backgroundColor: '#ffffff', foregroundColor: '#0d1530', headerBackgroundColor: '#00209F',
    headerTextColor: '#ffffff', accentColor: '#00209F', borderColor: '#dde2ee', rowHoverColor: 'rgba(0,32,159,.05)',
    fontFamily: '"DM Sans", system-ui, sans-serif', fontSize: 13, headerFontWeight: 700, wrapperBorderRadius: 6
  });

  var tab = 'items';
  var data = null;
  var grid = null;
  var loading = false;

  function fmtTime(p) { var d = p.value ? new Date(p.value) : null; return d && !isNaN(d) ? d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; }
  function usd(p) { if (p.value === null || p.value === undefined) return ''; var n = Number(p.value); return (n < 0 ? '−$' : '$') + Math.abs(n).toFixed(2); }
  function badge(map) {
    return function (p) {
      if (p.value === null || p.value === undefined || p.node.rowPinned) return p.value === undefined ? '' : String(p.value || '');
      var m = map[p.value] || [String(p.value), 'muted'];
      var s = document.createElement('span');
      s.className = 'chip-s ' + m[1];
      s.textContent = m[0];
      return s;
    };
  }
  var timeCol = { field: 'at', headerName: 'Time', sort: 'desc', width: 150, valueFormatter: fmtTime, filter: 'agDateColumnFilter', filterValueGetter: function (p) { return new Date(p.data.at); } };
  var money = { type: 'rightAligned', valueFormatter: usd, filter: 'agNumberColumnFilter', width: 120, cellClassRules: { 'num-plus': function (p) { return p.value > 0; }, 'num-minus': function (p) { return p.value < 0; } } };

  var ITEM = { planned: ['Planned', 'muted'], sent: ['Sent', 'blue'], pending: ['Pending', 'blue'], success: ['Paid', 'green'], unclaimed: ['Unclaimed', 'gold'], failed: ['Failed', 'red'], returned: ['Returned', 'red'], cancelled: ['Cancelled', 'muted'] };
  var BY = { agent: ['AI agent', 'blue'], rule: ['Split rule', 'muted'], owner: ['You', 'gold'], mandate: ['Mandate (auto)', 'green'] };
  var ACCOUNT = { pot: ['Project pot', 'blue'], reserve: ['Reserve', 'gold'], crew: ['Crew', 'green'], hold: ['Hold', 'muted'] };
  var KIND = { tool: ['Tool call', 'blue'], policy: ['Policy', 'gold'], paypal: ['PayPal', 'green'], event: ['Evidence', 'muted'], owner: ['You', 'gold'], message: ['Chat', 'blue'] };

  var defs = {
    items: [
      timeCol,
      { field: 'crew', headerName: 'Crew', width: 130 },
      Object.assign({ field: 'amount', headerName: 'Amount' }, money),
      { field: 'status', headerName: 'Status', width: 120, cellRenderer: badge(ITEM) },
      { field: 'title', headerName: 'Payout', flex: 1, minWidth: 200 },
      { field: 'project', headerName: 'Project', width: 170 },
      { field: 'by', headerName: 'Proposed by', width: 120, cellRenderer: badge(BY) },
      { field: 'approved_by', headerName: 'Approved by', width: 140, cellRenderer: badge(BY) },
      { field: 'receiver', headerName: 'PayPal receiver', width: 180 },
      { field: 'batch', headerName: 'Payouts batch', width: 150, cellClass: 'mono' },
      { field: 'item_id', headerName: 'Item id', width: 140, cellClass: 'mono' }
    ],
    ledger: [
      timeCol,
      { field: 'account', headerName: 'Account', width: 130, cellRenderer: badge(ACCOUNT) },
      { field: 'type', headerName: 'Type', width: 150 },
      Object.assign({ field: 'amount', headerName: 'Amount', aggFunc: 'sum' }, money),
      { field: 'project', headerName: 'Project', width: 180 },
      { field: 'crew', headerName: 'Crew', width: 130 },
      { field: 'plan', headerName: 'Plan', width: 80 },
      { field: 'note', headerName: 'Note', flex: 1, minWidth: 200 }
    ],
    log: [
      timeCol,
      { field: 'kind', headerName: 'Kind', width: 120, cellRenderer: badge(KIND) },
      { field: 'tool', headerName: 'Action', width: 200 },
      { field: 'detail', headerName: 'Detail', flex: 1, minWidth: 320, tooltipField: 'detail', cellClass: 'mono small' }
    ]
  };

  function pinned() {
    if (tab === 'log' || !data) return [];
    var rows = data[tab];
    var sum = rows.reduce(function (s, r) { return s + (tab === 'items' && ['planned', 'failed', 'returned', 'cancelled'].indexOf(r.status) >= 0 ? 0 : Number(r.amount)); }, 0);
    var row = { at: null, amount: Math.round(sum * 100) / 100 };
    row[tab === 'items' ? 'crew' : 'account'] = tab === 'items' ? 'Total sent' : null;
    if (tab === 'ledger') row.type = 'Net (all accounts)';
    return [row];
  }

  function draw() {
    if (!data) return;
    var el = $('#grid');
    if (!grid) {
      grid = agGrid.createGrid(el, {
        theme: theme, columnDefs: defs[tab], rowData: data[tab], pinnedBottomRowData: pinned(),
        defaultColDef: { sortable: true, filter: true, resizable: true, floatingFilter: false },
        pagination: true, paginationPageSize: 50, animateRows: true, enableCellTextSelection: true, tooltipShowDelay: 300,
        overlayNoRowsTemplate: '<span class="muted">Nothing yet. Load the demo, pay a client link and send the agent some evidence.</span>'
      });
    } else {
      grid.setGridOption('columnDefs', defs[tab]);
      grid.setGridOption('rowData', data[tab]);
      grid.setGridOption('pinnedBottomRowData', pinned());
    }
    grid.setGridOption('quickFilterText', $('#gridSearch').value);
  }

  function load() {
    if (loading) return;
    loading = true;
    K().api('grid').then(function (j) { data = j; draw(); }).catch(function (e) { K().toast(e.message, 'err'); }).then(function () { loading = false; });
  }

  document.addEventListener('konbit:view', function (e) { if (e.detail === 'ledger') load(); });
  $('#gridTabs').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-tab]');
    if (!b) return;
    tab = b.dataset.tab;
    Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); });
    draw();
  });
  $('#gridSearch').addEventListener('input', function () { if (grid) grid.setGridOption('quickFilterText', this.value); });
  $('#gridCsv').addEventListener('click', function () { if (grid) grid.exportDataAsCsv({ fileName: 'konbit-' + tab + '.csv' }); });
})();
