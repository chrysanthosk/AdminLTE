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
    margin-bottom: 10px;
  }
  .today-appointments-header h3 {
    margin: 0;
  }
  .today-appointments-controls {
    display: flex;
    gap: 10px;
    align-items: center;
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
  /* Basic styling for the table */
  #todayAppointments {
    width: 100%;
    border-collapse: collapse;
  }
  #todayAppointments th,
  #todayAppointments td {
    border: 1px solid #ddd;
    padding: 8px;
  }
  #todayAppointments th {
    background-color: #f4f4f4;
  }
  /* Hide rows beyond the “Show X” limit */
  .hidden-row {
    display: none;
  }
</style>

<div class="content-wrapper">
  <!-- (1) Page Header with Add Appointment button -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Calendar View</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Link to your existing appointments.php page -->
          <a href="/pages/appointments.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Appointment
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- (2) Calendar Container -->
  <section class="content">
    <div class="container-fluid">
      <div id="calendarViewContainer" style="height:600px; border:1px solid #444;"></div>
    </div>
  </section>

  <!-- (3) “Today’s Appointments” Table -->
  <section class="content today-appointments-container">
    <div class="container-fluid">
      <div class="today-appointments-header">
        <h3>Today's Appointments</h3>
        <div class="today-appointments-controls">
          <label for="showCount">Show:</label>
          <select id="showCount" class="form-control form-control-sm">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="100">100</option>
            <option value="200">200</option>
            <option value="all">All</option>
          </select>

          <label for="searchToday" style="margin-left: 10px;">Search:</label>
          <input type="text" id="searchToday" class="form-control form-control-sm" placeholder="Search…">
        </div>
      </div>

      <table id="todayAppointments">
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
          // Fetch today’s appointments (server-side)
          $today = date('Y-m-d');
          $stmt = $pdo->prepare("
            SELECT
              a.id,
              a.start_time,
              a.end_time,
              COALESCE(CONCAT(c.first_name,' ',c.last_name), a.client_name) AS client_name,
              s.name AS service_name,
              CONCAT(t.first_name,' ',t.last_name) AS staff_name,
              a.notes
            FROM appointments a
            LEFT JOIN clients c ON a.client_id = c.id
            JOIN services s ON a.service_id = s.id
            JOIN therapists t ON a.staff_id = t.id
            WHERE a.appointment_date = ?
            ORDER BY a.start_time
          ");
          $stmt->execute([$today]);
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          $rowCount = count($rows);
          foreach ($rows as $idx => $r) {
            $start     = substr($r['start_time'], 0, 5);
            $end       = substr($r['end_time'], 0, 5);
            $timeLabel = htmlspecialchars("{$start} – {$end}");
            $staff     = htmlspecialchars($r['staff_name']);
            $client    = htmlspecialchars($r['client_name']);
            $service   = htmlspecialchars($r['service_name']);
            $notes     = htmlspecialchars($r['notes']);
            // Each row gets a data-index for JS paging
            echo "<tr data-row-index=\"{$idx}\">"
               . "<td>{$timeLabel}</td>"
               . "<td>{$staff}</td>"
               . "<td>{$client}</td>"
               . "<td>{$service}</td>"
               . "<td>{$notes}</td>"
               . "</tr>";
          }
          if ($rowCount === 0) {
            echo '<tr><td colspan="5" style="text-align:center;">No appointments for today.</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>

<!-- (4) Required JS: jQuery, Select2, FullCalendar Scheduler UMD (must match your existing setup) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  // ─── (A) Initialize Select2 (if you plan to use it anywhere else)
  if ($.fn.select2) {
    $('.select2bs4').select2({ theme: 'bootstrap4', width: '100%' });
  }

  // ─── (B) Render the FullCalendar (same as your appointments.php) ───
  let calendar;
  fetch('./calendar_resources.php')
    .then(r => r.json())
    .then(resources => {
      const calendarEl = document.getElementById('calendarViewContainer');
      calendar = new FullCalendar.Calendar(calendarEl, {
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

        // (B1) Flash any in‐progress appointment (optional)
        eventClassNames: function(arg) {
          const now     = new Date();
          const evStart = arg.event.start;
          const evEnd   = arg.event.end || new Date(evStart.getTime() + 30*60000);
          if (now >= evStart && now < evEnd) {
            return ['blinking-event'];
          }
          return [];
        },

        // (B2) You can add eventClick, eventDrop, etc., but in calendar_view we’re read‐only
        eventClick: function(info) {
          // Optionally show details or link to edit
        }
      });

      calendar.render();
    });

  // ─── (C) “Today’s Appointments” table filtering & paging ───
  const $table       = $('#todayAppointments');
  const $rows        = $table.find('tbody tr');
  const totalRows    = $rows.length;
  const $showCount   = $('#showCount');
  const $searchInput = $('#searchToday');

  function applyFilterAndPaging() {
    const searchTerm = $searchInput.val().toLowerCase();
    const showVal    = $showCount.val();
    let visibleCount = 0;

    $rows.each(function() {
      const $r    = $(this);
      const text  = $r.text().toLowerCase();
      const match = text.indexOf(searchTerm) !== -1;

      if (!match) {
        $r.addClass('hidden-row');
      } else {
        // If “All” is not selected, only show the first N matching rows
        if (showVal !== 'all') {
          if (visibleCount < parseInt(showVal, 10)) {
            $r.removeClass('hidden-row');
            visibleCount++;
          } else {
            $r.addClass('hidden-row');
          }
        } else {
          // “All” = show every matching row
          $r.removeClass('hidden-row');
          visibleCount++;
        }
      }
    });
  }

  // Bind search input
  $searchInput.on('keyup', function() {
    applyFilterAndPaging();
  });

  // Bind “Show” dropdown
  $showCount.on('change', function() {
    applyFilterAndPaging();
  });

  // Initialize (so default “10” works on page load)
  applyFilterAndPaging();
});
</script>