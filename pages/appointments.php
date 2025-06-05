<?php
// pages/appointments.php  — corrected version with duplicate‐variable fix

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

<!-- (2) Flashing‐appointment CSS (must come after all other CSS) -->
<style>
  @keyframes blink-flash {
    0%   { opacity: 1; }
    50%  { opacity: 0.3; }
    100% { opacity: 1; }
  }
  .fc-event.blinking-event {
    animation: blink-flash 1s infinite;
  }
  .category-color-box {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-right: 4px;
    vertical-align: middle;
    border-radius: 2px;
    border: 1px solid #666;
  }
</style>

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

  <!-- Main Content -->
  <section class="content">
    <div class="container-fluid">
      <div id="appointmentsMainCalendar" style="height:600px; border:1px solid #444;"></div>
    </div>
  </section>
</div>

<!-- ──────────── “Add Appointment” Modal ──────────── -->
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
          <!-- (A) Client Fields -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="existingClient">Existing Client</label>
              <select id="existingClient" name="client_id" class="form-control select2bs4">
                <option value=""></option>
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

          <!-- (B) Filter by Service Category -->
          <div class="mb-2">
            <strong>Filter by Category:</strong>
            <?php
            $cstmt = $pdo->query("SELECT id, name, color FROM service_categories ORDER BY name");
            while ($cat = $cstmt->fetch(PDO::FETCH_ASSOC)) {
              $catName  = htmlspecialchars($cat['name']);
              $catColor = htmlspecialchars($cat['color']);
              echo "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary category-filter\" data-category-id=\"{$cat['id']}\">"
                 . "<span class=\"category-color-box\" style=\"background:{$catColor};\"></span>{$catName}</button> ";
            }
            echo "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary category-filter\" data-category-id=\"\">All</button>";
            ?>
          </div>

          <!-- (C) Draggable Services List -->
          <div id="serviceList" class="mb-3" style="max-height:150px; overflow-y:auto; border:1px solid #444; padding:10px;">
            <?php
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
              echo "<div class=\"fc-event draggable-service\"
                         data-service-id=\"{$srv['id']}\"
                         data-category-id=\"{$catId}\"
                         style=\"margin-bottom:5px; padding:5px; background:{$bgColor}; color:#fff; cursor:move; border-radius:3px;\">
                      {$srvLabel}</div>";
            }
            ?>
          </div>

          <hr/>

          <!-- (D) “Go To Date” Picker -->
          <div class="form-group">
            <label for="goToDate">Go To Date:</label>
            <input type="date" id="goToDate" class="form-control" />
          </div>

          <!-- (E) Mini Calendar for Drag-Drop -->
          <div id="appointmentCalendarModal" style="height:350px; border:1px solid #444;"></div>
          <input type="hidden" id="hiddenServiceId" name="service_id" />
          <input type="hidden" id="hiddenStartTime" name="start_time" />
          <input type="hidden" id="hiddenEndTime" name="end_time" />
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

<!-- ──────────── “Confirm Change” Modal ──────────── -->
<div class="modal fade" id="confirmChangeModal" tabindex="-1" role="dialog" aria-labelledby="confirmChangeLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmChangeLabel">Confirm Appointment Time Change</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria‐hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="change-text">Are you sure you want to move this appointment?</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="confirm-change-btn" class="btn btn-primary">Yes, Save</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- ─── (5) jQuery ─── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- ─── (6) Bootstrap JS bundle (Popper+Bootstrap) ─── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- ─── (7) FullCalendar Scheduler UMD (local copy) ─── -->
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<!-- ─── (8) Select2 (v4.0.13) ─── -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // ─── (A) Immediately hide any stray modal/backdrop on load ───
  $('#addAppointmentModal').modal('hide');
  $('.modal-backdrop').remove();

  // ─── (B) Initialize Select2 on “Existing Client” ───
  $('#existingClient').select2({
    placeholder: 'Type to search clients…',
    allowClear: true,
    width: '100%',
    theme: 'bootstrap4'
  });

  // Temp storage for event being changed
  let _tempEvent, _tempOldStart, _tempOldEnd;

  // ─── (D) “Add Appointment” Modal: bind Draggable + render mini-calendar ───
  $('#addAppointmentModal').on('shown.bs.modal', function () {
    // Bind each service to be draggable (only once)
    $('#serviceList .draggable-service').each(function() {
      const $svcEl = $(this);
      if ($svcEl.data('draggableBound')) return;

      const serviceId = $svcEl.data('service-id');
      const eventObj  = {
        title: $svcEl.text().trim(),
        extendedProps: { service_id: serviceId }
      };
      $svcEl.data('event', eventObj);

      new FullCalendar.Draggable(this, {
        itemSelector: '.draggable-service',
        eventData: () => $svcEl.data('event')
      });
      $svcEl.data('draggableBound', true);
    });

    // Render mini‐calendar inside modal
    const modalEl       = document.getElementById('appointmentCalendarModal');
    const modalCalendar = new FullCalendar.Calendar(modalEl, {
      schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      plugins: [
        FullCalendar.interactionPlugin,
        FullCalendar.timeGridPlugin,
        FullCalendar.resourcePlugin,
        FullCalendar.resourceTimeGridPlugin
      ],
      initialView: 'resourceTimeGridDay',
      slotMinTime: '08:00:00',
      slotMaxTime: '22:00:00',
      slotDuration: '00:05:00',
      slotLabelInterval: '00:15:00',
      allDaySlot: false,
      headerToolbar: false,
      nowIndicator: true,

      // Pull therapists as resources
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

      // If a second service is dropped, remove the first
      eventReceive: function(info) {
        const allEv = modalCalendar.getEvents();
        if (allEv.length > 1) {
          allEv[0].remove();
        }
        // Store hidden inputs for “Add Appointment”
        $('#hiddenServiceId').val(info.event.extendedProps.service_id);
        $('#hiddenStartTime').val(info.event.start.toISOString());
        $('#hiddenEndTime').val((info.event.end || new Date(info.event.start.getTime() + 30*60000)).toISOString());
        $('#hiddenStaffId').val(info.event.getResources()[0].id);
      },

      // Click on a dropped event removes it
      eventClick: function(info) {
        info.event.remove();
      },

      events: []
    });

    modalCalendar.render();
    $(this).data('calendarInstance', modalCalendar);
  });

  // When “Add Appointment” modal closes, destroy mini-calendar & reset form
  $('#addAppointmentModal').on('hide.bs.modal', function () {
    const cal = $(this).data('calendarInstance');
    if (cal) {
      cal.destroy();
      $(this).removeData('calendarInstance');
    }
    $('#appointmentForm')[0].reset();
    $('#serviceList .draggable-service').removeData('event');
  });

  // ─── (E) Filter services by category in modal ───
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

  // ─── (F) Submit “Add Appointment” form ───
  $('#appointmentForm').on('submit', function(e) {
    e.preventDefault();

    const modalCal = $('#addAppointmentModal').data('calendarInstance');
    if (!modalCal) {
      return alert('Calendar not instantiated—please reopen the modal.');
    }

    const evList = modalCal.getEvents();
    if (evList.length === 0 || !evList[evList.length - 1].start) {
      return alert('Please drag a service onto the mini-calendar to pick a valid time.');
    }
    // Always take the last‐dropped event
    const ev = evList[evList.length - 1];

    // Helper to format “YYYY-MM-DD HH:mm:00”
    function formatLocal(dt) {
      const yyyy = dt.getFullYear();
      const MM   = String(dt.getMonth() + 1).padStart(2, '0');
      const dd   = String(dt.getDate()).padStart(2, '0');
      const hh   = String(dt.getHours()).padStart(2, '0');
      const mm   = String(dt.getMinutes()).padStart(2, '0');
      return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
    }
    const startLocal = formatLocal(ev.start);
    const endLocal   = ev.end
                       ? formatLocal(ev.end)
                       : formatLocal(new Date(ev.start.getTime() + 30*60000));
    const staffId    = ev.getResources()[0].id;

    const existingClientId = $('#existingClient').val();
    const newClientName    = $('#newClientName').val().trim();
    const newClientPhone   = $('#newClientPhone').val().trim();
    let clientId   = null;
    let clientName = '';
    let clientPhone= '';
    if (existingClientId) {
      clientId = existingClientId;
    } else if (newClientName && newClientPhone) {
      clientName  = newClientName;
      clientPhone = newClientPhone;
    } else {
      return alert('Please select an existing client or enter new client name & phone.');
    }

    const notes   = $('#notes').val().trim();
    const sendSms = $('#sendSmsToggle').is(':checked') ? 1 : 0;

    $.post('/pages/save_appointment.php', {
      service_id:   ev.extendedProps.service_id,
      start_time:   startLocal,
      end_time:     endLocal,
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

  // ─── (G) Initialize the Main Calendar ───
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

        // ─── (G1) Flash in‐progress appointments ───
        eventClassNames: function(arg) {
          const now     = new Date();
          const evStart = arg.event.start;
          const evEnd   = arg.event.end || new Date(evStart.getTime() + 30*60000);
          if (now >= evStart && now < evEnd) {
            return ['blinking-event'];
          }
          return [];
        },

        // ─── (G2) Confirm on eventDrop ───
        eventDrop: function(info) {
          _tempEvent    = info.event;
          _tempOldStart = info.oldEvent.start;
          _tempOldEnd   = info.oldEvent.end || new Date(_tempOldStart.getTime() + 30*60000);

          const newStart = info.event.start;
          // renamed this for clarity so we don’t shadow newEnd below
          const computedEnd = info.event.end
                              ? info.event.end
                              : new Date(newStart.getTime() + (_tempOldEnd.getTime() - _tempOldStart.getTime()));

          function formatPretty(d) {
            return d.toLocaleString([], {
              weekday: 'short',
              year:    'numeric',
              month:   'short',
              day:     'numeric',
              hour:    'numeric',
              minute:  '2-digit',
              hour12:  true
            });
          }
          const oldStr    = formatPretty(_tempOldStart);
          const oldEndStr = formatPretty(_tempOldEnd);
          const newStr    = formatPretty(newStart);
          const newEndStr = formatPretty(computedEnd);

          $('#change-text').text(
            `Change appointment from ${oldStr} – ${oldEndStr} to ${newStr} – ${newEndStr}?`
          );
          $('#confirm-change-btn').data('confirmed', false);
          $('#confirmChangeModal').modal('show');

          $('#confirmChangeModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            if (!$('#confirm-change-btn').data('confirmed')) {
              info.revert();
            }
          });
        },

        // ─── (G3) Confirm on eventResize ───
        eventResize: function(info) {
          _tempEvent    = info.event;
          _tempOldStart = info.oldEvent.start;
          _tempOldEnd   = info.oldEvent.end;

          const newStart = info.event.start;
          const computedEnd = info.event.end;

          function formatPretty(d) {
            return d.toLocaleString([], {
              weekday: 'short',
              year:    'numeric',
              month:   'short',
              day:     'numeric',
              hour:    'numeric',
              minute:  '2-digit',
              hour12:  true
            });
          }
          const oldStr    = formatPretty(_tempOldStart);
          const oldEndStr = formatPretty(_tempOldEnd);
          const newStr    = formatPretty(newStart);
          const newEndStr = formatPretty(computedEnd);

          $('#change-text').text(
            `Change appointment from ${oldStr} – ${oldEndStr} to ${newStr} – ${newEndStr}?`
          );
          $('#confirm-change-btn').data('confirmed', false);
          $('#confirmChangeModal').modal('show');

          $('#confirmChangeModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            if (!$('#confirm-change-btn').data('confirmed')) {
              info.revert();
            }
          });
        },

        eventClick: function(info) {
          // (optional) future “view details” or “delete” logic
        }
      });

      mainCalendar.render();
    });

  // ─── (H) When user clicks “Yes, Save” on Confirm Change ───
  $('#confirm-change-btn').on('click', function() {
    $(this).data('confirmed', true);

    const ev      = _tempEvent;
    const id      = ev.id;
    const start   = ev.start;
    const endDate = ev.end || new Date(start.getTime() + (_tempOldEnd.getTime() - _tempOldStart.getTime()));

    // Format as “YYYY-MM-DD HH:mm:00” for backend
    function formatLocal(dt) {
      const yyyy = dt.getFullYear();
      const MM   = String(dt.getMonth() + 1).padStart(2, '0');
      const dd   = String(dt.getDate()).padStart(2, '0');
      const hh   = String(dt.getHours()).padStart(2, '0');
      const mm   = String(dt.getMinutes()).padStart(2, '0');
      return `${yyyy}-${MM}-${dd} ${hh}:${mm}:00`;
    }
    const startLocal = formatLocal(start);
    const endLocal   = formatLocal(endDate);

    $.post('/pages/update_appointment_time.php', {
      id:         id,
      start_time: startLocal,
      end_time:   endLocal
    }, function(resp) {
      if (!resp.success) {
        alert('Could not update appointment: ' + resp.error);
        _tempEvent.setStart(_tempOldStart);
        _tempEvent.setEnd(_tempOldEnd);
      }
      $('#confirmChangeModal').modal('hide');
    }, 'json');
  });
});
</script>