<?php
// pages/appointments.php — Manage Appointments Page
require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');

$page_title = 'Manage Appointments';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- (1) After AdminLTE’s CSS, pull in Select2 v4.0.13 CSS -->
<link
  href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css"
  rel="stylesheet"
/>

<!-- (2) Dark‐mode overrides for Select2 (to match AdminLTE dark theme) -->
<style>
  .select2-container .select2-selection--single {
    background-color: #343a40 !important;
    border: 1px solid #6c757d !important;
    color: #fff !important;
    height: calc(1.5em + .75rem + 2px) !important;
  }
  .select2-container .select2-selection--single .select2-selection__rendered {
    color: #e0e0e0 !important;
    line-height: calc(1.5em + .75rem) !important;
  }
  .select2-container .select2-selection--single .select2-selection__arrow b {
    border-color: #e0e0e0 transparent transparent transparent !important;
  }
  .select2-container .select2-dropdown {
    background-color: #343a40 !important;
    border: 1px solid #6c757d !important;
    z-index: 9999 !important;  /* float above modal */
  }
  .select2-container .select2-search--dropdown .select2-search__field {
    background-color: #495057 !important;
    color: #fff !important;
    border: 1px solid #6c757d !important;
    padding: .375rem .75rem !important;
  }
  .select2-container .select2-results__option {
    color: #f8f9fa !important;
  }
  .select2-container .select2-results__option--highlighted {
    background-color: #6c757d !important;
    color: #fff !important;
  }
  .select2-container .select2-selection__placeholder {
    color: #adb5bd !important;
  }

  /* Add a small colored square for each category button */
  .category-color-box {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-right: 4px;
    vertical-align: middle;
    border: 1px solid #888;
  }
</style>

<!-- (3) FullCalendar Scheduler CSS (v6), relative to this page -->
<link href="assets/fullcalendar-scheduler/main.min.css" rel="stylesheet"/>
<link href="assets/fullcalendar-scheduler/resource-timegrid.min.css" rel="stylesheet"/>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Appointments</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button
            class="btn btn-primary"
            data-toggle="modal"
            data-target="#addAppointmentModal"
          >
            <i class="fas fa-plus"></i> Add Appointment
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content: Appointment Calendar -->
  <section class="content">
    <div class="container-fluid">
      <div id="appointmentsMainCalendar" style="height:600px; border:1px solid #444;"></div>
    </div>
  </section>
</div>

<!-- Add Appointment Modal (removed aria-hidden attribute entirely) -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" role="dialog" aria-labelledby="addAppointmentLabel">
  <div class="modal-dialog modal-lg" role="document">
    <form id="appointmentForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addAppointmentLabel">New Appointment</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Client fields -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="existingClient">Existing Client</label>
              <select id="existingClient" name="client_id" class="w-100">
                <option></option>
                <?php
                $stmt = $pdo->query("
                  SELECT id, first_name, last_name, mobile
                    FROM clients
                   ORDER BY first_name, last_name
                ");
                while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  $label = htmlspecialchars("{$c['first_name']} {$c['last_name']} ({$c['mobile']})");
                  echo "<option value=\"{$c['id']}\">{$label}</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label for="newClientName">New Client Name</label>
              <input type="text" class="form-control" id="newClientName" name="client_name" placeholder="If not on list" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="newClientPhone">New Client Phone</label>
              <input type="text" class="form-control" id="newClientPhone" name="client_phone" placeholder="Phone" />
            </div>
            <div class="form-group col-md-6">
              <label for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any notes…"></textarea>
            </div>
          </div>
          <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" id="sendSmsToggle" name="send_sms" />
            <label class="form-check-label" for="sendSmsToggle">Send SMS (future feature)</label>
          </div>

          <hr/>

          <!-- Filter by Service Category -->
          <div class="mb-2">
            <strong>Filter by Category:</strong>
            <?php
            // Pull each category’s color from service_categories.color
            $cstmt = $pdo->query("SELECT id, name, color FROM service_categories ORDER BY name");
            while ($cat = $cstmt->fetch(PDO::FETCH_ASSOC)) {
              $catName  = htmlspecialchars($cat['name']);
              $catColor = htmlspecialchars($cat['color']);
              echo "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary category-filter\" data-category-id=\"{$cat['id']}\">"
                 . "<span class=\"category-color-box\" style=\"background:{$catColor};\"></span>"
                 . "{$catName}</button> ";
            }
            echo '<button type="button" class="btn btn-sm btn-outline-secondary category-filter" data-category-id="">'
               . '<span class="category-color-box" style="background:#6c757d;"></span>All</button>';
            ?>
          </div>

          <!-- Draggable Services List -->
          <div id="serviceList" class="mb-3" style="max-height:150px; overflow-y:auto; border:1px solid #444; padding:10px;">
            <?php
            // Join services → service_categories to pull each service’s category color
            $sstmt = $pdo->query("
              SELECT s.id, s.name, s.category_id, sc.color AS cat_color
                FROM services s
                JOIN service_categories sc ON s.category_id = sc.id
               ORDER BY s.name
            ");
            while ($srv = $sstmt->fetch(PDO::FETCH_ASSOC)) {
              $srvLabel = htmlspecialchars($srv['name']);
              $catId    = (int)$srv['category_id'];
              $bgColor  = htmlspecialchars($srv['cat_color']);
              echo "<div class=\"fc-event draggable-service\" data-service-id=\"{$srv['id']}\" data-category-id=\"{$catId}\" "
                 . "style=\"margin-bottom:5px; padding:5px; background:{$bgColor}; color:#fff; cursor:move; border-radius:3px;\">"
                 . "{$srvLabel}</div>";
            }
            ?>
          </div>
          <hr/>

          <!-- (4) Go To Date selector above the mini-calendar -->
          <div class="form-group">
            <label for="goToDate">Go To Date:</label>
            <input type="date" id="goToDate" class="form-control" />
          </div>

          <!-- Mini Calendar for Drag‐Drop -->
          <div id="appointmentCalendarModal" style="height:350px; border:1px solid #444;"></div>
          <input type="hidden" id="hiddenServiceId" name="service_id" />
          <input type="hidden" id="hiddenStartTime" name="start_iso" />
          <input type="hidden" id="hiddenEndTime" name="end_iso" />
          <input type="hidden" id="hiddenStaffId" name="staff_id" />
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add Appointment</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- (5) jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- (6) Bootstrap JS bundle (Popper + Bootstrap) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- (7) FullCalendar Scheduler UMD (relative to this page) -->
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<!-- (8) Select2 JS (v4.0.13) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // ─── (A) Clear stray modal/backdrop on page load ───
  $('#addAppointmentModal').modal('hide');
  $('.modal-backdrop').remove();

  // ─── (B) Initialize Select2 on “Existing Client” ───
  if (typeof $.fn.select2 !== 'function') {
    console.error('Select2 did NOT load!');
    return;
  }
  $('#existingClient').select2({
    placeholder: 'Type to search clients…',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#addAppointmentModal')
  });

  // ─── (C) Verify FullCalendar UMD loaded ───
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

  // ─── (D) Mini‐calendar inside the “Add Appointment” modal ───
  $('#addAppointmentModal').on('shown.bs.modal', function () {
    // (D1) Bind Draggable only once per element
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

    // (D2) Build the mini-calendar with AM/PM labels
    const modalEl = document.getElementById('appointmentCalendarModal');
    const modalCalendar = new FullCalendar.Calendar(modalEl, {
      schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      plugins: plugins,
      locale: 'en-gb',
      timeZone: 'local',
      initialView: 'resourceTimeGridDay',
      slotMinTime: '06:00:00',
      slotMaxTime: '22:00:00',
      slotDuration: '00:05:00',
      slotLabelInterval: '00:15:00',
      allDaySlot: false,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''   // remove 1/3/5-day buttons
      },
      nowIndicator: true,
      slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
      titleFormat:   { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
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

      // (D3) If a second service is dropped, remove the existing one
      eventReceive: function(info) {
        const allEv = modalCalendar.getEvents();
        if (allEv.length > 1) {
          // Remove the first (older) event, keep only newest
          allEv[0].remove();
        }
      },

      // (D4) Clicking on any event removes it immediately
      eventClick: function(info) {
        info.event.remove();
      },

      events: []
    });
    modalCalendar.render();
    $('#addAppointmentModal').data('calendarInstance', modalCalendar);

    // (D5) “Go To Date” input – change date to that day
    $('#goToDate').off('change').on('change', function() {
      const val = $(this).val();
      if (val) {
        modalCalendar.gotoDate(val);
      }
    });
    // Initialize the GoToDate input to today’s date
    const today = new Date();
    const yyyy = today.getFullYear();
    const MM   = String(today.getMonth()+1).padStart(2,'0');
    const dd   = String(today.getDate()).padStart(2,'0');
    $('#goToDate').val(`${yyyy}-${MM}-${dd}`);
    modalCalendar.gotoDate(`${yyyy}-${MM}-${dd}`);
  });

  $('#addAppointmentModal').on('hide.bs.modal', function () {
    $('.modal-backdrop').remove();
    const cal = $(this).data('calendarInstance');
    if (cal) {
      cal.destroy();
      $(this).removeData('calendarInstance');
    }
    $('#appointmentForm')[0].reset();
    $('#serviceList .draggable-service').removeData('event');
    // We do NOT remove data-draggableBound, so Draggable remains bound exactly once
  });

  // ─── (E) Filter Services by Category ───
  $('.category-filter').on('click', function() {
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

  // ─── (F) Submit “Add Appointment” Form ───
  $('#appointmentForm').on('submit', function(e) {
    e.preventDefault();

    const modalCal = $('#addAppointmentModal').data('calendarInstance');
    if (!modalCal) {
      return alert('Calendar not instantiated—please reopen the modal.');
    }

    const evList = modalCal.getEvents();
    if (evList.length === 0 || !evList[0].start) {
      return alert('Please drag a service onto the mini-calendar to pick a valid time.');
    }

    // Always take the last‐dropped event
    const ev = evList[evList.length - 1];

    // Build local “YYYY-MM-DD HH:mm:00” strings
    function formatLocal(dt) {
      const yyyy = dt.getFullYear();
      const MM   = String(dt.getMonth()+1).padStart(2,'0');
      const dd   = String(dt.getDate()).padStart(2,'0');
      const hh   = String(dt.getHours()).padStart(2,'0');
      const mm   = String(dt.getMinutes()).padStart(2,'0');
      return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
    }

    const startLocal = formatLocal(ev.start);
    const endDateObj = ev.end || new Date(ev.start.getTime() + 30*60*1000);
    const endLocal   = formatLocal(endDateObj);

    const resources = ev.getResources();
    if (!resources || resources.length === 0) {
      return alert('Could not find the assigned staff—please drop onto a staff row.');
    }
    const staffId = resources[0].id;

    // Determine client (existing or new)
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
      return alert('Please select an existing client or enter a new client’s name & phone.');
    }

    const notes   = $('#notes').val().trim();
    const sendSms = $('#sendSmsToggle').is(':checked') ? 1 : 0;

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
        $('#addAppointmentModal').modal('hide');
        mainCalendar.refetchEvents();
      } else {
        alert('Error saving appointment: ' + resp.error);
      }
    }, 'json');
  });

  // ─── (G) Main Calendar Setup ───
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
        titleFormat:     { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
        resources: resources,
        events: '/pages/appointment_events.php',
        editable: true,
        eventResizableFromStart: true,
        eventDurationEditable: true,
        selectable: true,

        // (G1) When an appointment is dragged to a new time/row
        eventDrop: function(info) {
          const ev    = info.event;
          const id    = ev.id;
          const start = ev.start;
          const end   = ev.end || new Date(ev.start.getTime() + 30*60*1000);
          function formatLocal(dt) {
            const yyyy = dt.getFullYear();
            const MM   = String(dt.getMonth()+1).padStart(2,'0');
            const dd   = String(dt.getDate()).padStart(2,'0');
            const hh   = String(dt.getHours()).padStart(2,'0');
            const mm   = String(dt.getMinutes()).padStart(2,'0');
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

        // (G2) When an appointment is resized
        eventResize: function(info) {
          const ev    = info.event;
          const id    = ev.id;
          const start = ev.start;
          const end   = ev.end;
          function formatLocal(dt) {
            const yyyy = dt.getFullYear();
            const MM   = String(dt.getMonth()+1).padStart(2,'0');
            const dd   = String(dt.getDate()).padStart(2,'0');
            const hh   = String(dt.getHours()).padStart(2,'0');
            const mm   = String(dt.getMinutes()).padStart(2,'0');
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

        eventClick: function(info) { /* future if needed */ }
      });
      mainCalendar.render();
    });
});
</script>