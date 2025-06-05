<?php
// pages/calendar_view.php — FullCalendar + Today's Appointments table

require_once '../auth.php';
requirePermission($pdo, 'calendar_view.view');

$page_title = 'Calendar View';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
  /* (A) Any custom CSS needed for the “Today’s Appointments” table and its filters */
  .today-appointments-container {
    margin-top: 20px;
  }
  .today-appointments-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  #todayAppointments {
    width: 100%;
    border-collapse: collapse;
  }
  #todayAppointments th,
  #todayAppointments td {
    border: 1px solid #ddd;
    padding: 8px;
  }
  /* Use AdminLTE theme color for table header */
  #todayAppointments thead th {
    background-color: #007bff; /* bg-primary */
    color: white;
  }
  /* Hide rows beyond the “Show X” limit */
  .hidden-row {
    display: none;
  }
</style>

<div class="content-wrapper">
  <!-- (1) Page Header with Add Appointment button (now a modal trigger) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Calendar View</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active">Calendar View</li>
          </ol>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-6">
          <!-- Left side can be empty or hold filters, etc. -->
        </div>
        <div class="col-sm-6 text-right">
          <!-- Modal trigger instead of direct link -->
          <button id="btnAddAppt" class="btn btn-primary" data-toggle="modal" data-target="#addApptModal">
            <i class="fas fa-plus"></i> Add Appointment
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- (2) Add Appointment Modal (empty body; will be populated by AJAX) -->
  <div class="modal fade" id="addApptModal" tabindex="-1" role="dialog" aria-labelledby="addApptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addApptModalLabel">Add New Appointment</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="addApptFormContainer">
            <div class="text-center">
              <i class="fas fa-spinner fa-spin"></i> Loading…
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- (3) Calendar Container (FullCalendar will render here) -->
  <section class="content">
    <div class="container-fluid">
      <!-- We keep a fixed-height wrapper so FullCalendar can “fill parent” and show a scrollbar -->
      <div id="calendarViewContainer" style="height: 600px; border: 1px solid #444;"></div>
    </div>
  </section>

  <!-- (4) Today’s Appointments table below the calendar -->
  <section class="content">
    <div class="container-fluid">
      <div class="today-appointments-container">
        <div class="card">
          <div class="card-header today-appointments-header">
            <h3 class="card-title">Today's Appointments</h3>
            <div>
              <label for="showCount">Show:</label>
              <select id="showCount" class="form-control form-control-sm d-inline-block" style="width: auto;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
              <label for="searchInput" class="ml-3">Search:</label>
              <input type="text" id="searchInput" class="form-control form-control-sm d-inline-block" placeholder="Filter titles…" style="width: auto;">
            </div>
          </div>
          <div class="card-body">
            <table id="todayAppointments" class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Staff</th>
                  <th>Client</th>
                  <th>Service</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Fetch today's appointments (server-side)
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("
                  SELECT
                    a.id,
                    a.start_time,
                    a.end_time,
                    u.first_name AS staff_first,
                    u.last_name AS staff_last,
                    c.first_name AS client_first,
                    c.last_name AS client_last,
                    s.name AS service_name,
                    a.notes
                  FROM appointments a
                  JOIN users u     ON a.staff_id = u.id
                  JOIN clients c   ON a.client_id = c.id
                  JOIN services s  ON a.service_id = s.id
                  WHERE DATE(a.start_time) = :today
                  ORDER BY a.start_time ASC
                ");
                $stmt->execute(['today'=>$today]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  $time = date('g:i A', strtotime($row['start_time'])) . ' – ' . date('g:i A', strtotime($row['end_time']));
                  $staffName  = htmlspecialchars("{$row['staff_first']} {$row['staff_last']}");
                  $clientName = htmlspecialchars("{$row['client_first']} {$row['client_last']}");
                  $service    = htmlspecialchars($row['service_name']);
                  $notes      = htmlspecialchars($row['notes']);
                  echo "<tr>
                          <td>{$time}</td>
                          <td>{$staffName}</td>
                          <td>{$clientName}</td>
                          <td>{$service}</td>
                          <td>{$notes}</td>
                        </tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div> <!-- /.content-wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- ─── (5) jQuery (already included by AdminLTE footer, but if not, ensure it’s here) ─── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- ─── (6) Bootstrap JS bundle (Popper + Bootstrap) ─── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- ─── (7) FullCalendar Scheduler UMD (local copy) ─── -->
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<!-- ─── (8) moment-timezone (if needed) ─── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.43/moment-timezone-with-data.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // (B) Fetch resource data, then render FullCalendar
    fetch('./calendar_resources.php')
    .then(r => r.json())
    .then(resources => {
      const calendarEl = document.getElementById('calendarViewContainer');
      const calendar = new FullCalendar.Calendar(calendarEl, {
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
        plugins: FullCalendar.globalPlugins,
        timeZone: 'local',

        // ─── LIMIT TO 3 HOURS (6 AM – 9 AM) ───
        initialView: 'resourceTimeGridDay',
        height: 'parent',
        contentHeight: 'auto',
        scrollTime: '06:00:00',
        slotMinTime: '06:00:00',
        slotMaxTime: '09:00:00',

        views: {
          resourceTimeGridDay: {
            type: 'resourceTimeGrid',
            buttonText: '1 day',
            slotMinTime: '06:00:00',
            slotMaxTime: '09:00:00'
          },
          resourceTimeGridThreeDay: {
            type: 'resourceTimeGrid',
            duration: { days: 3 },
            buttonText: '3 days',
            slotMinTime: '06:00:00',
            slotMaxTime: '09:00:00'
          },
          resourceTimeGridFiveDay: {
            type: 'resourceTimeGrid',
            duration: { days: 5 },
            buttonText: '5 days',
            slotMinTime: '06:00:00',
            slotMaxTime: '09:00:00'
          }
        },

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

        // (B1) Flash any in-progress appointment (optional)
        eventClassNames: function(arg) {
          const now     = new Date();
          const evStart = arg.event.start;
          const evEnd   = arg.event.end || new Date(evStart.getTime() + 30*60000);
          if (evStart < now && now < evEnd) { return ['bg-warning']; }
          return [];
        },

        // (B2) When an event is dropped (time change), ask “Confirm?”
        eventDrop: function(info) {
          let _tempEvent = info.event,
              _tempOldStart = info.oldEvent.start,
              _tempOldEnd   = info.oldEvent.end;
          $('#confirmChangeModal').modal('show').off('click', '#confirm-change-btn')
          .on('click', '#confirm-change-btn', function() {
            const id = _tempEvent.id;
            const startLocal = moment(_tempEvent.start).tz(moment.tz.guess()).format();
            const endLocal   = moment(_tempEvent.end ?? _tempEvent.start).tz(moment.tz.guess()).format();
            $.post('/pages/update_appointment_time.php', {
              id: id,
              start_time: startLocal,
              end_time: endLocal
            }, function(resp) {
              if (!resp.success) {
                alert('Could not update appointment: ' + resp.error);
                _tempEvent.setStart(_tempOldStart);
                _tempEvent.setEnd(_tempOldEnd);
              }
              $('#confirmChangeModal').modal('hide');
            }, 'json');
          });
        }
      });

      calendar.render();
    });
  });

  // ─── (C) When “Add Appointment” modal pops up, load the form via AJAX ───
  $(document).ready(function(){
    $('#addApptModal').on('show.bs.modal', function(e) {
      let container = $('#addApptFormContainer');
      container.html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
      $.ajax({
        url: 'appointments.php',
        method: 'GET',
        data: { action: 'add', ajax: 1 },
        success: function(html) {
          container.html(html);
        },
        error: function() {
          container.html('<div class="alert alert-danger">Unable to load form. Please try again.</div>');
        }
      });
    });
  });

  // ─── (D) Filter & paging for “Today's Appointments” table ───
  $(document).ready(function(){
    const $table     = $('#todayAppointments tbody tr');
    const $searchInput= $('#searchInput');
    const $showCount = $('#showCount');

    function applyFilterAndPaging() {
      const filter = $searchInput.val().toLowerCase();
      const limit  = parseInt($showCount.val(), 10);
      let visibleCount = 0;

      $table.each(function() {
        const $row = $(this);
        const text = $row.text().toLowerCase();
        if (filter !== '' && text.indexOf(filter) === -1) {
          $row.addClass('hidden-row');
        } else {
          $row.removeClass('hidden-row');
          if (visibleCount < limit) {
            $row.show();
            visibleCount++;
          } else {
            $row.hide();
          }
        }
      });
    }

    $searchInput.on('keyup', applyFilterAndPaging);
    $showCount.on('change', applyFilterAndPaging);
    applyFilterAndPaging(); // initialize on page load
  });
</script>