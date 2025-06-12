<?php
// calendar_view.php

require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<?php
  // Build the same $jsRes array of therapists for your “Add Appointment” modal
  $tstmt = $pdo->prepare("
    SELECT id, first_name, last_name, color, position
      FROM therapists
     WHERE show_in_calendar = 1
     ORDER BY position ASC, first_name ASC
  ");
  $tstmt->execute();
  $therapists = $tstmt->fetchAll(PDO::FETCH_ASSOC);
  $jsRes = [];
  foreach ($therapists as $t) {
    $jsRes[] = [
      'id'         => $t['id'],
      'title'      => "{$t['first_name']} {$t['last_name']}",
      'eventColor' => $t['color']
    ];
  }
?>
<!-- ─── CSS Includes ─────────────────────────────────────────────────────────────── -->

<!-- FullCalendar Scheduler CSS (make sure these files exist under /assets/fullcalendar-scheduler/) -->
<!--<link href="assets/fullcalendar-scheduler/main.min.css" rel="stylesheet"/>
<link href="assets/fullcalendar-scheduler/resource-timegrid.min.css" rel="stylesheet"/>
-->
<!-- Select2 CSS (v4.0.13) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>

<style>
  /* ─── Custom Styles ─────────────────────────────────────────────────────────── */

  /* Flashing event (blinking) */
  .blinking-event {
    animation: blink 1s infinite alternate;
  }
  @keyframes blink {
    from { opacity: 1; }
    to   { opacity: 0.3; }
  }

  /* Draggable service items in modal */
  .draggable-service {
    padding: 6px;
    margin-bottom: 6px;
    cursor: move;
    color: #fff;
    background-color: #007bff;
  }

  /* Category filter buttons in modal */
  .category-filter {
    margin-right: 5px;
    margin-bottom: 5px;
  }
  .category-color-box {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-right: 5px;
    border-radius: 2px;
  }

  /* Main calendar container: fixed height (~300px) with vertical scroll for the rest */
  #appointmentsMainCalendar {
    border: 1px solid #444;
    height: 300px; /* show roughly 3 hours, rest scrollable */
    overflow-y: auto;
  }

  /* Table header for Today’s Appointments */
  #todaysAppointmentsTable thead th {
    background-color: #007bff;
    color: #fff;
  }

  /* Within modal, make service list scrollable */
  #serviceList {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #ddd;
    background-color: #f9f9f9;
    margin-bottom: 15px;
  }
  /* force your calendar element to fill its parent horizontally */
  #appointmentsMainCalendar {
    width: 100% !important;
  }
</style>

<div class="content-wrapper">
  <!-- ─── Page Header ──────────────────────────────────────────────────────────────── -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Appointments Calendar</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button
            id="btnAddAppt"
            class="btn btn-primary"
            data-toggle="modal"
            data-target="#addApptModal"
          >
            <i class="fas fa-plus"></i> Add Appointment
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- ─── Main Calendar ────────────────────────────────────────────────────────────── -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div id="appointmentsMainCalendar"></div>
        </div>
      </div>
    </div>
  </section>
<?php
    // Fetch today’s appointments (exact same logic as before)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
             SELECT
                a.id,
                a.start_time,
                a.end_time,
                IFNULL(CONCAT(c.first_name,' ',c.last_name), a.client_name) AS client_name,
                CONCAT(t.first_name,' ',t.last_name)                  AS staff_name,
                srv.name                                              AS service_name,
                a.notes
              FROM appointments a
              LEFT JOIN clients    c   ON a.client_id   = c.id
              LEFT JOIN therapists t   ON a.staff_id    = t.id
              LEFT JOIN services   srv ON a.service_id  = srv.id
              WHERE a.appointment_date = :today
              ORDER BY a.start_time
    ");
    $stmt->execute(['today' => $today]);
?>
  <!-- ─── Today’s Appointments Table ──────────────────────────────────────────────── -->
  <section class="content">
    <div class="container-fluid">
      <h2>Today’s Appointments</h2>
      <table id="todaysAppointmentsTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>Time</th>
            <th>Client</th>
            <th>Staff</th>
            <th>Service</th>
            <th>Notes</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
           <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
             $start = date('H:i', strtotime($row['start_time']));
             $end   = date('H:i', strtotime($row['end_time']));
           ?>
           <tr>
             <td><?= htmlspecialchars("$start – $end",ENT_QUOTES) ?></td>
             <td><?= htmlspecialchars($row['client_name'],ENT_QUOTES) ?></td>
             <td><?= htmlspecialchars($row['staff_name'],ENT_QUOTES) ?></td>
             <td><?= htmlspecialchars($row['service_name'],ENT_QUOTES) ?></td>
             <td><?= htmlspecialchars($row['notes'],ENT_QUOTES) ?></td>
            <td>
              <button class="btn btn-sm btn-info edit-apt-btn" data-id="<?= (int)$row['id'] ?>">Edit</button>
              <button class="btn btn-sm btn-danger delete-apt-btn" data-id="<?= (int)$row['id'] ?>">Delete</button>
            </td>
           </tr>
           <?php endwhile; ?>
        </tbody>
      </table>
  <!-- ──────────── “Add Appointment” Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="addApptModal" tabindex="-1" role="dialog" aria-labelledby="addApptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- This container will be populated via AJAX from appointments.php?action=add&ajax=1 -->
        <div id="addApptFormContainer">
          <div class="text-center">
            <i class="fas fa-spinner fa-spin"></i> Loading…
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Appointment Modal -->
      <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editAppointmentLabel">Edit Appointment</h5>
              <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
              <!-- form content will load here via AJAX -->
              <div id="editAppointmentBody" class="p-3">Loading…</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>

<!-- ─────────────────────────────────────────────────────────────────────────────────── -->
<!-- Required JS Files: jQuery, Bootstrap, FullCalendar, Select2, moment.js ──────────── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.43/moment-timezone-with-data.min.js"></script>

<script>
$(document).ready(function() {
  // ─── (1) Load & initialize “Add Appointment” form when modal opens ───────────────
  $('#addApptModal').on('shown.bs.modal', function() {
    const container = $('#addApptFormContainer');
    container.html(`<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>`);
    $.ajax({
      url: '/pages/appointments.php',
      method: 'GET',
      data: { action: 'add', ajax: 1 },
      success: function(html) {
        container.html(html);

        // (1A) Select2
        if (typeof $.fn.select2 !== 'function') {
          console.error('Select2 did NOT load!');
          return;
        }
        $('#existingClient').select2({
          placeholder: 'Type to search clients…',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#addApptModal')
        });
        $('#staffSelect, #serviceSelect').select2({
          theme: 'bootstrap4',
          width: '100%',
          allowClear: true,
          dropdownParent: $('#addApptModal')
        });

        // (1B) FullCalendar loaded?
        if (
          typeof FullCalendar === 'undefined' ||
          typeof FullCalendar.Calendar !== 'function' ||
          typeof FullCalendar.Draggable !== 'function' ||
          !Array.isArray(FullCalendar.globalPlugins)
        ) {
          console.error('FullCalendar did not load.');
          return;
        }
        const plugins = FullCalendar.globalPlugins;

        // (1C) Bind Draggable services
        $('#serviceList .draggable-service').each(function() {
          if (!$(this).data('draggableBound')) {
            const serviceId = $(this).data('service-id');
            const eventObj = { title: $(this).text().trim(), extendedProps: { service_id: serviceId } };
            $(this).data('event', eventObj);
            new FullCalendar.Draggable(this, {
              itemSelector: '.draggable-service',
              eventData: () => $(this).data('event')
            });
            $(this).data('draggableBound', true);
          }
        });

        // (1D) Mini‐calendar in modal
        const modalEl = document.getElementById('appointmentCalendarModal');
        const modalCalendar = new FullCalendar.Calendar(modalEl, {
          schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
          plugins: plugins,
          locale: 'en-gb',
          timeZone: 'local',
          views: {
            resourceTimeGridDay: { type: 'resourceTimeGrid', buttonText: '1 day' },
            resourceTimeGridThreeDay: { type: 'resourceTimeGrid', duration: { days: 3 }, buttonText: '3 days' },
            resourceTimeGridFiveDay: { type: 'resourceTimeGrid', duration: { days: 5 }, buttonText: '5 days' }
          },
          initialView: 'resourceTimeGridDay',
          slotMinTime: '06:00:00',
          slotMaxTime: '22:00:00',
          slotDuration: '00:05:00',
          slotLabelInterval: '00:15:00',
          allDaySlot: false,
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'resourceTimeGridDay,resourceTimeGridThreeDay,resourceTimeGridFiveDay'
          },
          nowIndicator: true,
          slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
          titleFormat: { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' },
          resources: <?= json_encode($jsRes) ?>,
          editable: true,
          droppable: true,
          eventResizableFromStart: true,
          eventDurationEditable: true,
          eventReceive: function(info) {
            const evs = modalCalendar.getEvents();
            if (evs.length > 1) evs[0].remove();
          },
          eventClick: function(info) { info.event.remove(); },
          eventClassNames: function(arg) {
            const now = new Date(), s = arg.event.start, e = arg.event.end || new Date(s.getTime()+1800000);
            return (now>=s && now<e) ? ['blinking-event'] : [];
          },
          datesSet: function(arg) {
            const vt = arg.view.type;
            if (vt==='resourceTimeGridThreeDay'||vt==='resourceTimeGridFiveDay') {
              const start = arg.view.currentStart, end = new Date(arg.view.currentEnd.getTime()-1);
              const st = new Intl.DateTimeFormat('en-US',{weekday:'short',day:'numeric'}).format(start);
              const et = new Intl.DateTimeFormat('en-US',{weekday:'short',day:'numeric',month:'long',year:'numeric'}).format(end);
              document.querySelector('.fc-toolbar-title').innerText = st + ' — ' + et;
            }
          },
          events: []
        });
        modalCalendar.render();
        $('#addApptModal').data('calendarInstance', modalCalendar);

        // (1E) Go To Date
        $('#goToDate').off('change').on('change', function() {
          modalCalendar.gotoDate(this.value);
        });
        const today = new Date(), iso = today.toISOString().slice(0,10);
        $('#goToDate').val(iso);
        modalCalendar.gotoDate(iso);
      },
      error: function() {
        container.html('<div class="alert alert-danger">Unable to load form. Please try again later.</div>');
      }
    });
  });

  // ─── (2) Destroy mini‐calendar on hide ──────────────────────────────────────────
  $('#addApptModal').on('hide.bs.modal', function() {
    const cal = $(this).data('calendarInstance');
    if (cal) cal.destroy();
    $(this).removeData('calendarInstance');
    $('#appointmentForm')[0]?.reset();
    $('#serviceList .draggable-service').removeData('event');
    $('.modal-backdrop').remove();
  });

  // ─── (3) Filter Services by Category ──────────────────────────────────────────
  $(document).on('click', '.category-filter', function() {
    const catId = $(this).data('category-id');
    $('#serviceList .draggable-service').each(function() {
      $(this).toggle(!catId || $(this).data('category-id') == catId);
    });
  });

  // ─── (4) Submit “Add Appointment” ──────────────────────────────────────────
  $(document).on('submit', '#appointmentForm', function(e) {
    e.preventDefault();
    const cal = $('#addApptModal').data('calendarInstance');
    const evs = cal?.getEvents() || [];
    if (!evs.length || !evs[0].start) {
      return alert('Please drag a service onto the calendar to pick a time.');
    }
    const ev = evs[evs.length-1];
    const fmt = dt => dt.toISOString().slice(0,19).replace('T',' ');
    const data = {
      service_id: ev.extendedProps.service_id,
      start_iso:  fmt(ev.start),
      end_iso:    fmt(ev.end || new Date(ev.start.getTime()+30*60000)),
      staff_id:   ev.getResources()[0].id,
      notes:      $('#notes').val().trim(),
      send_sms:   $('#sendSmsToggle').is(':checked')?1:0
    };
    // existing vs new client
    const ex = $('#existingClient').val(), nN = $('#newClientName').val().trim(), nP = $('#newClientPhone').val().trim();
    if (ex) { data.client_id = ex; }
    else if (nN && nP) { data.client_name = nN; data.client_phone = nP; }
    else { return alert('Select existing client or enter name & phone.'); }

    $.post('/pages/save_appointment.php', data, function(resp) {
      if (resp.success) {
        $('#addApptModal').modal('hide');
        mainCalendar.refetchEvents();
      } else {
        alert('Error saving appointment: ' + resp.error);
      }
    }, 'json');
  });

  // Utility to format a JS Date as "YYYY-MM-DD HH:mm:00" in the browser's local time
  function formatLocal(dt) {
    const yyyy = dt.getFullYear();
    const MM   = String(dt.getMonth() + 1).padStart(2, '0');
    const dd   = String(dt.getDate()).padStart(2, '0');
    const hh   = String(dt.getHours()).padStart(2, '0');
    const mm   = String(dt.getMinutes()).padStart(2, '0');
    return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
  }

  // ─── (5) Initialize Main Calendar ───────────────────────────────────────────────
  let mainCalendar;
  fetch('./calendar_resources.php')
    .then(r => r.json())
    .then(resources => {
      const el = document.getElementById('appointmentsMainCalendar');
      mainCalendar = new FullCalendar.Calendar(el, {
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
        plugins: FullCalendar.globalPlugins,
        timeZone: 'local',
        initialView: 'resourceTimeGridDay',
        height: window.innerHeight - 130,
        views: {
          resourceTimeGridDay:  { type:'resourceTimeGrid', buttonText:'1 day' },
          resourceTimeGridThreeDay: { type:'resourceTimeGrid', duration:{days:3}, buttonText:'3 days' },
          resourceTimeGridFiveDay:  { type:'resourceTimeGrid', duration:{days:5}, buttonText:'5 days' },
          month: { titleFormat:{month:'short',year:'numeric'} }
        },
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'resourceTimeGridDay,resourceTimeGridThreeDay,resourceTimeGridFiveDay'
        },
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        slotDuration: '00:05:00',
        slotLabelInterval: '00:15:00',
        allDaySlot: false,
        nowIndicator: true,
        dayHeaderFormat: { weekday:'short' },
        slotLabelFormat: { hour:'numeric', minute:'2-digit', hour12:true },
        titleFormat:     { weekday:'long', day:'numeric', month:'long', year:'numeric' },
        resources: resources,
        events: '/pages/appointment_events.php',
        editable: true,
        selectable: true,
        eventResizableFromStart: true,
        eventDurationEditable: true,

        // Flash “now” appointment
        eventClassNames: function(arg) {
          const now = new Date(), s = arg.event.start, e = arg.event.end || new Date(s.getTime()+1800000);
          return (now>=s && now<e) ? ['blinking-event'] : [];
        },

        // Compact multi-day title
        datesSet: function(arg) {
          const vt = arg.view.type;
          if (vt==='resourceTimeGridThreeDay'||vt==='resourceTimeGridFiveDay') {
            const start = arg.view.currentStart, end = new Date(arg.view.currentEnd.getTime()-1);
            const st = new Intl.DateTimeFormat('en-US',{weekday:'short',day:'numeric'}).format(start);
            const et = new Intl.DateTimeFormat('en-US',{weekday:'short',day:'numeric',month:'long',year:'numeric'}).format(end);
            document.querySelector('.fc-toolbar-title').innerText = st + ' — ' + et;
          }
        },

        //  Drag existing appointment to new time/staff
        eventDrop: function(info) {
          const ev = info.event;
          const data = {
            id:         ev.id,
            start_time: formatLocal(ev.start),
            // if no ev.end (resize via drop), assume 30m duration
            end_time:   formatLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
            staff_id:   ev.getResources()[0]?.id
          };
          $.post('/pages/update_appointment_time.php', data, function(resp) {
            if (resp.success) {
              mainCalendar.refetchEvents();
            } else {
              alert('Could not update appointment: ' + resp.error);
              info.revert();
            }
          }, 'json');
        },

        // Resize existing appointment → save new time/staff
       eventResize: function(info) {
          const ev = info.event;
          const data = {
            id:         ev.id,
            start_time: formatLocal(ev.start),
            end_time:   formatLocal(ev.end   || new Date(ev.start.getTime() + 30*60000)),
            staff_id:   ev.getResources()[0]?.id
          };
          $.post('/pages/update_appointment_time.php', data, function(resp) {
            if (resp.success) {
              mainCalendar.refetchEvents();
            } else {
              alert('Could not update appointment: ' + resp.error);
              info.revert();
            }
          }, 'json');
        },

        // Click → edit inline
        eventClick: function(info) {
          const id = info.event.id;
          $('#editAppointmentBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
          $('#editAppointmentModal').modal('show');
          $.get('/pages/appointments_edit.php', { id: id }, function(html) {
            $('#editAppointmentBody').html(html);
            $('#editAppointmentForm').off('submit').on('submit', function(e) {
              e.preventDefault();
              $.post('/pages/update_appointment.php', $(this).serialize(), function(resp) {
                if (resp.success) {
                  $('#editAppointmentModal').modal('hide');
                  mainCalendar.refetchEvents();
                } else {
                  alert('Error: ' + resp.error);
                }
              }, 'json');
            });
          });
        }
      });

      mainCalendar.render();

      // Sidebar collapse/expand → resize
      $(document).on('collapsed.lte.pushmenu expanded.lte.pushmenu', function() {
        setTimeout(() => mainCalendar.updateSize(), 300);
      });
      $('.main-sidebar').on('transitionend', () => mainCalendar.updateSize());
    });

  // ─── (6) Edit via table buttons ────────────────────────────────────────────
  $(document).on('click','.edit-apt-btn',function(){
    const id = $(this).data('id');
    $('#editAppointmentBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
    $('#editAppointmentModal').modal('show');
    $.get('/pages/appointments_edit.php',{ id: id }, function(html){
      $('#editAppointmentBody').html(html);
      $('#editAppointmentForm').off('submit').on('submit', function(e){
        e.preventDefault();
        $.post('/pages/update_appointment.php', $(this).serialize(), function(resp){
          if (resp.success) {
            $('#editAppointmentModal').modal('hide');
            mainCalendar.refetchEvents();
          } else {
            alert('Error: ' + resp.error);
          }
        }, 'json');
      });
    });
  });

  // ─── (7) Delete appointment ────────────────────────────────────────────
  $(document).on('click','.delete-apt-btn', function(){
    const id = $(this).data('id');
    if (!confirm('Delete this appointment?')) return;
    $.post('/pages/delete_appointment.php',{ id: id }, function(resp){
      if (resp.success) {
        mainCalendar.refetchEvents();
      } else {
        alert('Delete failed: ' + resp.error);
      }
    }, 'json');
  });
});
</script>