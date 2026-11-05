// Payout schedule: Bryntum Scheduler with crew members as rows and payouts / due milestones as events.
// Dragging a waiting or scheduled payout asks the server to move its pay day.
(function () {
  'use strict';

  var $ = function (s) { return document.querySelector(s); };
  var K = function () { return window.Konbit; };
  var scheduler = null;

  var STATUS = {
    paid: 'Paid', partial: 'Partly paid', sent: 'Sending', sending: 'Sending', scheduled: 'Scheduled',
    needs_approval: 'Needs your tap', blocked: 'Blocked', failed: 'Failed', due: 'Milestone due'
  };
  var COLORS = { paid: 'green', partial: 'orange', sent: 'blue', sending: 'blue', scheduled: 'indigo', needs_approval: 'yellow', blocked: 'red', failed: 'red', due: 'gray' };

  function isoDay(d) {
    var z = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
    return z.toISOString().slice(0, 10);
  }

  function build(data) {
    var B = window.bryntum.scheduler;
    var start = new Date();
    start.setDate(start.getDate() - 1);
    start.setHours(0, 0, 0, 0);
    var end = new Date(start);
    end.setDate(end.getDate() + 6);
    data.events.forEach(function (ev) {
      ev.eventColor = COLORS[ev.status] || 'gray';
      ev.startDate = new Date(ev.startDate);
    });
    scheduler = new B.Scheduler({
      appendTo: 'scheduler',
      startDate: start,
      endDate: end,
      viewPreset: 'weekAndDay',
      rowHeight: 64,
      barMargin: 10,
      eventStyle: 'plain',
      resourceImagePath: null,
      columns: [{
        type: 'resourceInfo', text: 'Crew', width: 200, showEventCount: false, showImage: false,
        renderer: function (o) { return { children: [{ tag: 'b', text: o.record.name }, { tag: 'small', class: 'b-role', text: o.record.role || '' }] }; }
      }],
      resources: data.resources,
      events: data.events,
      features: {
        eventDrag: { constrainDragToResource: true },
        eventResize: false,
        eventEdit: false,
        eventMenu: false,
        scheduleMenu: false,
        eventDragCreate: false,
        timeRanges: { showCurrentTimeLine: true },
        eventTooltip: {
          template: function (o) {
            var r = o.eventRecord;
            return '<div class="b-tip"><b>' + K().esc(r.name) + '</b><br>' + K().esc(r.projectName || '') + '<br>' + K().esc(STATUS[r.status] || r.status) +
              (r.draggable ? '<br><i>Drag to another day to move the pay day.</i>' : '') + '</div>';
          }
        }
      },
      eventRenderer: function (o) {
        var r = o.eventRecord;
        o.renderData.cls.add('ev-' + r.status);
        // Short label on the bar; the tooltip has the full title.
        return K().esc((r.amount || '') + ' · ' + (STATUS[r.status] || r.status));
      },
      listeners: {
        beforeEventDropFinalize: function (o) {
          var ctx = o.context;
          var rec = ctx.eventRecords[0];
          if (!rec || !rec.planId) { ctx.valid = false; return; }
          ctx.async = true;
          K().api('plan_reschedule', { id: rec.planId, date: isoDay(ctx.startDate) }).then(function (j) {
            K().toast('Pay day moved to ' + j.result.pay_on + '. The mandate is re-checked on that day.', 'ok');
            ctx.finalize(true);
            setTimeout(load, 400);
          }).catch(function (e) {
            K().toast(e.message, 'err');
            ctx.finalize(false);
          });
        }
      }
    });
  }

  function load() {
    var note = $('#schedNote');
    if (!window.bryntum || !window.bryntum.scheduler) {
      $('#scheduler').innerHTML = '<div class="card calm"><b>Bryntum Scheduler is not installed on this server.</b><span class="muted">Run <span class="mono">npm run vendor</span> (see README) to add the trial build.</span></div>';
      return;
    }
    K().api('schedule').then(function (j) {
      if (!j.resources.length) {
        note.textContent = 'Add crew members to see their payout timeline.';
      } else {
        note.textContent = j.events.length + ' payouts and due milestones in view.';
      }
      if (scheduler) {
        j.events.forEach(function (ev) { ev.eventColor = COLORS[ev.status] || 'gray'; ev.startDate = new Date(ev.startDate); });
        scheduler.resourceStore.data = j.resources;
        scheduler.eventStore.data = j.events;
      } else {
        build(j);
      }
    }).catch(function (e) { K().toast(e.message, 'err'); });
  }

  document.addEventListener('konbit:view', function (e) { if (e.detail === 'schedule') load(); });
})();
