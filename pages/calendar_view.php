<?php
// calendar_view.php

require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

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
          </tr>
        </thead>
        <tbody>
          <?php
          // Fetch today’s appointments (exact same logic as before)
          $today = date('Y-m-d');
          $stmt = $pdo->prepare("
            SELECT
              a.start_time, a.end_time,
              COALESCE(c.first_name || ' ' || c.last_name, a.client_name) AS client_name,
              u.first_name || ' ' || u.last_name AS staff_name,
              s.name AS service_name,
              a.notes
            FROM appointments a
            LEFT JOIN clients c ON a.client_id = c.id
            LEFT JOIN users u ON a.staff_id = u.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date = :today
            ORDER BY a.start_time
          ");
          $stmt->execute(['today' => $today]);
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $start = date('H:i', strtotime($row['start_time']));
            $end   = date('H:i', strtotime($row['end_time']));
            $timeRange = "{$start} – {$end}";
            $client  = htmlspecialchars($row['client_name']);
            $staff   = htmlspecialchars($row['staff_name']);
            $service = htmlspecialchars($row['service_name']);
            $notes   = htmlspecialchars($row['notes']);
            echo "
              <tr>
                <td>{$timeRange}</td>
                <td>{$client}</td>
                <td>{$staff}</td>
                <td>{$service}</td>
                <td>{$notes}</td>
              </tr>
            ";
          }
          ?>
        </tbody>
      </table>
    </div>
  </section>
</div> <!-- /.content-wrapper -->

<!-- ──────────── “Add Appointment” Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="addApptModal" tabindex="-1" role="dialog" aria-labelledby="addApptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <!--<h5 class="modal-title" id="addApptModalLabel">Add New Appointment</h5> -->
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
    container.html(`
      <div class="text-center">
        <i class="fas fa-spinner fa-spin"></i> Loading…
      </div>
    `);

    $.ajax({
      url: '/pages/appointments.php',
      method: 'GET',
      data: { action: 'add', ajax: 1 },
      success: function(html) {
        container.html(html);

        // (1A) Initialize Select2 on “Existing Client” & other dropdowns
        if (typeof $.fn.select2 !== 'function') {
          console.error('Select2 did NOT load!');
          return;
        }
        $('#existingClient').select2({
          placeholder: 'Type to search clients…',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#addApptModal')   // ← WAS #addAppointmentModal
        });
        $('#staffSelect, #serviceSelect').select2({
          theme: 'bootstrap4',
          width: '100%',
          allowClear: true,
          dropdownParent: $('#addApptModal')   // ← WAS #addAppointmentModal
        });

        // (1B) Verify FullCalendar is loaded
        if (
          typeof FullCalendar === 'undefined' ||
          typeof FullCalendar.Calendar !== 'function' ||
          typeof FullCalendar.Draggable !== 'function' ||
          !Array.isArray(FullCalendar.globalPlugins)
        ) {
          console.error('FullCalendar Scheduler UMD did not load.');
          return;
        }
        const plugins = FullCalendar.globalPlugins;

        // (1C) Bind FullCalendar.Draggable to services in #serviceList
        $('#serviceList .draggable-service').each(function() {
          if (!$(this).data('draggableBound')) {
            const serviceId = $(this).data('service-id');
            const eventObj  = {
              title: $(this).text().trim(),
              extendedProps: { service_id: serviceId }
            };
            $(this).data('event', eventObj);

            new FullCalendar.Draggable(this, {
              itemSelector: '.draggable-service',
              eventData: () => $(this).data('event')
            });
            $(this).data('draggableBound', true);
          }
        });

        // (1D) Build the mini-calendar inside the modal
        const modalEl = document.getElementById('appointmentCalendarModal');
        const modalCalendar = new FullCalendar.Calendar(modalEl, {
          schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
          plugins: plugins,
          locale: 'en-gb',
          timeZone: 'local',

          // Modal’s 1-day/3-day/5-day views
          views: {
            resourceTimeGridDay: {
              type: 'resourceTimeGrid',
              buttonText: '1 day'
            },
            resourceTimeGridThreeDay: {
              type: 'resourceTimeGrid',
              duration: { days: 3 },
              buttonText: '3 days'
            },
            resourceTimeGridFiveDay: {
              type: 'resourceTimeGrid',
              duration: { days: 5 },
              buttonText: '5 days'
            }
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

          // Abbreviated weekday + full month/year in modal title
          titleFormat: { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' },

          // Load therapists as resources (same PHP logic as appointments.php)
          resources: <?php
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
            echo json_encode($jsRes);
          ?>,
          editable: true,
          droppable: true,
          eventResizableFromStart: true,
          eventDurationEditable: true,

          // (D3) Only keep the newest dropped service event
          eventReceive: function(info) {
            const allEv = modalCalendar.getEvents();
            if (allEv.length > 1) {
              allEv[0].remove();
            }
          },

          // (D4) Clicking an event in modal removes it immediately
          eventClick: function(info) {
            info.event.remove();
          },

          // (D5) Flash any “now” event in modal
          eventClassNames: function(arg) {
            const now     = new Date();
            const evStart = arg.event.start;
            const evEnd   = arg.event.end || new Date(evStart.getTime() + 30 * 60000);
            if (now >= evStart && now < evEnd) {
              return ['blinking-event'];
            }
            return [];
          },

          // (D6) Compact multi-day title in modal
          datesSet: function(arg) {
            const vt = arg.view.type;
            if (vt === 'resourceTimeGridThreeDay' || vt === 'resourceTimeGridFiveDay') {
              const start = arg.view.currentStart;
              const end   = new Date(arg.view.currentEnd.getTime() - 1);

              const startText = new Intl.DateTimeFormat('en-US', {
                weekday: 'short',
                day: 'numeric'
              }).format(start);

              const endText = new Intl.DateTimeFormat('en-US', {
                weekday: 'short',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
              }).format(end);

              const titleEl = document.querySelector('.fc-toolbar-title');
              if (titleEl) {
                titleEl.innerText = startText + ' — ' + endText;
              }
            }
            // Single-day uses “Thu, June 5, 2025” by titleFormat
          },

          events: []
        });
        modalCalendar.render();
        $('#addApptModal').data('calendarInstance', modalCalendar);

        // (1E) “Go To Date” input in modal
        $('#goToDate').off('change').on('change', function() {
          const val = $(this).val();
          if (val) {
            modalCalendar.gotoDate(val);
          }
        });
        // Initialize “Go To Date” to today
        const today = new Date();
        const yyyy  = today.getFullYear();
        const MM    = String(today.getMonth() + 1).padStart(2, '0');
        const dd    = String(today.getDate()).padStart(2, '0');
        $('#goToDate').val(`${yyyy}-${MM}-${dd}`);
        modalCalendar.gotoDate(`${yyyy}-${MM}-${dd}`);
      },
      error: function() {
        container.html(`
          <div class="alert alert-danger">
            Unable to load form. Please try again later.
          </div>
        `);
      }
    });
  });

  // ─── (2) When modal hides, destroy mini-calendar & reset form ───────────────────
  $('#addApptModal').on('hide.bs.modal', function() {
    const cal = $(this).data('calendarInstance');
    if (cal) {
      cal.destroy();
      $(this).removeData('calendarInstance');
    }
    $('#appointmentForm')[0].reset();
    $('#serviceList .draggable-service').removeData('event');
    $('.modal-backdrop').remove();
    // Retain data-draggableBound so services remain draggable next open
  });

  // ─── (3) Filter Services by Category (delegated) ────────────────────────────────
  $(document).on('click', '.category-filter', function() {
    const catId = $(this).data('category-id');
    $('#serviceList .draggable-service').each(function() {
      const svcCat = $(this).data('category-id');
      if (!catId || svcCat == catId) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  });

  // ─── (4) Handle “Add Appointment” form submit inside modal ───────────────────────
  $(document).on('submit', '#appointmentForm', function(e) {
    e.preventDefault();

    const modalCal = $('#addApptModal').data('calendarInstance');
    if (!modalCal) {
      return alert('Calendar not ready—please reopen the modal.');
    }

    const evList = modalCal.getEvents();
    if (evList.length === 0 || !evList[0].start) {
      return alert('Please drag a service onto the calendar to pick a time.');
    }
    const ev = evList[evList.length - 1];

    function formatLocal(dt) {
      const yyyy = dt.getFullYear();
      const MM   = String(dt.getMonth() + 1).padStart(2, '0');
      const dd   = String(dt.getDate()).padStart(2, '0');
      const hh   = String(dt.getHours()).padStart(2, '0');
      const mm   = String(dt.getMinutes()).padStart(2, '0');
      return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
    }
    const startLocal = formatLocal(ev.start);
    const endDateObj = ev.end || new Date(ev.start.getTime() + 30 * 60000);
    const endLocal   = formatLocal(endDateObj);

    const resources = ev.getResources();
    if (!resources || resources.length === 0) {
      return alert('Could not find assigned staff—drop onto a staff row.');
    }
    const staffId = resources[0].id;

    // Determine client (existing vs new)
    const existingClientId = $('#existingClient').val();
    const newClientName    = $('#newClientName').val().trim();
    const newClientPhone   = $('#newClientPhone').val().trim();
    let clientId   = null;
    let clientName = null;
    let clientPhone= null;
    if (existingClientId) {
      clientId = existingClientId;
    } else if (newClientName && newClientPhone) {
      clientName  = newClientName;
      clientPhone = newClientPhone;
    } else {
      return alert('Select existing client or enter name & phone for new client.');
    }

    const notes   = $('#notes').val().trim();
    const sendSms = $('#sendSmsToggle').is(':checked') ? 1 : 0;

    // POST to save_appointment.php
    $.post('/pages/save_appointment.php', {
      service_id:   ev.extendedProps.service_id,
      start_iso:    startLocal,
      end_iso:      endLocal,
      staff_id:     staffId,
      client_id:    clientId,
      client_name:  clientName,
      client_phone: clientPhone,
      notes:        notes,
      send_sms:     sendSms
    }, function(resp) {
      if (resp.success) {
        $('#addApptModal').modal('hide');
        mainCalendar.refetchEvents();
      } else {
        alert('Error saving appointment: ' + resp.error);
      }
    }, 'json');
  });

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

        // Define main views: 1-day / 3-day / 5-day / month
        initialView: 'resourceTimeGridDay',
        height: window.innerHeight - 130,
        views: {
          resourceTimeGridDay: {
            type: 'resourceTimeGrid',
            buttonText: '1 day'
          },
          resourceTimeGridThreeDay: {
            type: 'resourceTimeGrid',
            duration: { days: 3 },
            buttonText: '3 days'
          },
          resourceTimeGridFiveDay: {
            type: 'resourceTimeGrid',
            duration: { days: 5 },
            buttonText: '5 days'
          },
          month: {
            // Abbreviate month title: “Jun 2025”
            titleFormat: { month: 'short', year: 'numeric' }
          }
        },

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

        // Abbreviate weekday headers in month view
        dayHeaderFormat: { weekday: 'short' },

        slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },

        // Default title for single-day; multi-day overridden below
        titleFormat: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },

        resources: resources,
        events: '/pages/appointment_events.php',
        editable: true,
        eventResizableFromStart: true,
        eventDurationEditable: true,
        selectable: true,

        // (E1) Flash any “now” appointment
        eventClassNames: function(arg) {
          const now     = new Date();
          const evStart = arg.event.start;
          const evEnd   = arg.event.end || new Date(evStart.getTime() + 30 * 60000);
          if (now >= evStart && now < evEnd) {
            return ['blinking-event'];
          }
          return [];
        },

        // (E2) Compact multi-day title: “5 Thu — 7 Sat June 2025”
        datesSet: function(arg) {
          const vt = arg.view.type;
          if (vt === 'resourceTimeGridThreeDay' || vt === 'resourceTimeGridFiveDay') {
            const start = arg.view.currentStart;
            const end   = new Date(arg.view.currentEnd.getTime() - 1);

            const startText = new Intl.DateTimeFormat('en-US', {
              weekday: 'short',
              day: 'numeric'
            }).format(start);

            const endText = new Intl.DateTimeFormat('en-US', {
              weekday: 'short',
              day: 'numeric',
              month: 'long',
              year: 'numeric'
            }).format(end);

            const titleEl = document.querySelector('.fc-toolbar-title');
            if (titleEl) {
              titleEl.innerText = startText + ' — ' + endText;
            }
          }
          // Single-day (“resourceTimeGridDay”) uses default titleFormat
        },

        // (E3) Drag existing appointment to new time/staff
        eventDrop: function(info) {
          const ev    = info.event;
          const id    = ev.id;
          const start = ev.start;
          const end   = ev.end || new Date(ev.start.getTime() + 30 * 60000);
          function formatLocal(dt) {
            const yyyy = dt.getFullYear();
            const MM   = String(dt.getMonth() + 1).padStart(2, '0');
            const dd   = String(dt.getDate()).padStart(2, '0');
            const hh   = String(dt.getHours()).padStart(2, '0');
            const mm   = String(dt.getMinutes()).padStart(2, '0');
            return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
          }
          $.post('/pages/update_appointment_time.php', {
            id:         id,
            start_time: formatLocal(start),
            end_time:   formatLocal(end)
          }, function(resp) {
            if (!resp.success) {
              alert('Could not update appointment: ' + resp.error);
              info.revert();
            }
          }, 'json');
        },

        // (E4) Resize existing appointment
        eventResize: function(info) {
          const ev    = info.event;
          const id    = ev.id;
          const start = ev.start;
          const end   = ev.end;
          function formatLocal(dt) {
            const yyyy = dt.getFullYear();
            const MM   = String(dt.getMonth() + 1).padStart(2, '0');
            const dd   = String(dt.getDate()).padStart(2, '0');
            const hh   = String(dt.getHours()).padStart(2, '0');
            const mm   = String(dt.getMinutes()).padStart(2, '0');
            return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
          }
          $.post('/pages/update_appointment.php', {
            id:         id,
            start_time: formatLocal(start),
            end_time:   formatLocal(end)
          }, function(resp) {
            if (!resp.success) {
              alert('Could not update appointment: ' + resp.error);
              info.revert();
            }
          }, 'json');
        },

        // (E5) Click to edit (placeholder)
        eventClick: function(info) {
          // You can open an edit modal if desired
        }
      });
      mainCalendar.render();

      $(document).on('collapsed.lte.pushmenu expanded.lte.pushmenu', function() {
        setTimeout(() => mainCalendar.updateSize(), 300);
      });

         $('.main-sidebar').on('transitionend', () => {
              mainCalendar.updateSize();
            });
    });
});
</script>