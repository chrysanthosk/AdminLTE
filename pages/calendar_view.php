<?php
// pages/calendar_view.php — Calendar of Appointments by Therapist
require_once '../auth.php';
requirePermission($pdo, 'calendar_view.view');

$page_title = 'Calendar View';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Calendar View</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Buttons to switch between 5-day / 3-day / 1-day -->
          <button id="view5" class="btn btn-secondary">5 days View</button>
          <button id="view3" class="btn btn-secondary">3 days View</button>
          <button id="view1" class="btn btn-secondary">1 day View</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content: calendar container -->
  <section class="content">
    <div class="container-fluid">
      <div id="calendar"></div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>

<!--
  FullCalendar Scheduler v6.1.17 global bundle (includes all plugins & CSS).
  Note: you do NOT need to specify `plugins:` when using this bundle.
-->
<!-- <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.17/index.global.min.js"></script> -->
<!-- If you prefer local copy, download the same URL to pages/assets/fullcalendar-scheduler/index.global.min.js
     and use: <script src="assets/fullcalendar-scheduler/index.global.min.js"></script> -->
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1) Fetch therapists (resources) from calendar_resources.php (same folder)
  const resourcesUrl = './calendar_resources.php';
  console.log('Fetching resources from:', resourcesUrl);

  fetch(resourcesUrl)
    .then(response => {
      console.log('Fetch status:', response.status, response.statusText);
      if (!response.ok) {
        throw new Error('Network response was not OK: ' + response.status);
      }
      return response.json();
    })
    .then(resources => {
      console.log('Resources JSON:', resources);

      // 2) Initialize FullCalendar with Scheduler plugin
      const calendarEl = document.getElementById('calendar');
      const calendar = new FullCalendar.Calendar(calendarEl, {
        // If your project is AGPL-licensed, keep this key.
        // Otherwise replace with your commercial Scheduler license key.
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',

        // No `plugins:` needed because the global bundle registers everything.

        // 3) Define 1-day, 3-day, and 5-day resourceTimeGrid views:
        views: {
          resourceTimeGridOneDay: {
            type: 'resourceTimeGrid',
            duration: { days: 1 },
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

        // 4) Show the 1-day view by default
        initialView: 'resourceTimeGridOneDay',

        // 5) Time range 08:00–22:00
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',

        // 6) Each “slot” is 5 minutes, with a label every 15 minutes
        slotDuration: '00:05:00',
        slotLabelInterval: '00:15:00',

        // 7) Remove the “all-day” row
        allDaySlot: false,

        // 8) Hide built-in header toolbar (we have custom buttons)
        headerToolbar: false,

        // 9) Show “now” indicator
        nowIndicator: true,

        // 10) Set a fixed height (e.g. chunk roughly 3 hours tall). Adjust px as needed.
        height: 450,

        // 11) Scroll initial vertical position to 08:00
        scrollTime: '08:00:00',

        // 12) Load therapists as resources
        resources: resources,

        // 13) No events loaded by default
        events: []
      });
      calendar.render();

      // 14) Wire up the view-switch buttons
      document.getElementById('view5').addEventListener('click', () => {
        calendar.changeView('resourceTimeGridFiveDay');
      });
      document.getElementById('view3').addEventListener('click', () => {
        calendar.changeView('resourceTimeGridThreeDay');
      });
      document.getElementById('view1').addEventListener('click', () => {
        calendar.changeView('resourceTimeGridOneDay');
      });
    })
    .catch(err => {
      console.error('Error fetching resources:', err);
      document.getElementById('calendar').innerHTML =
        '<div class="alert alert-danger">Could not load therapists for calendar.</div>';
    });
});
</script>