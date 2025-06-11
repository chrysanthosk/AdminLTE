<?php
// pages/appointments.php — Manage Appointments Page with Calendar/Table Toggle
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');
$page_title = 'Manage Appointments';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- CSS: FullCalendar Scheduler, DataTables, Select2 -->
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>
<style>
  .blinking-event { animation: blink 1s infinite alternate; }
  @keyframes blink { from{opacity:1;} to{opacity:0.3;} }
  #appointmentsMainCalendar { border:1px solid #444; height:600px; width:100% !important; }
  #tableSection { display:none; }
</style>

<div class="content-wrapper">
  <!-- View Toggle + Add Appointment Button -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <div class="btn-group" role="group" aria-label="View toggle">
            <button id="btnViewCalendar" class="btn btn-outline-primary active">Calendar View</button>
            <button id="btnViewTable"    class="btn btn-outline-primary">Table View</button>
          </div>
        </div>
        <div class="col-sm-6 text-right">
          <button id="btnAddAppt" class="btn btn-primary"><i class="fas fa-plus"></i> Add Appointment</button>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <!-- Calendar Section -->
      <div id="calendarSection">
        <div id="appointmentsMainCalendar"></div>
      </div>

      <!-- Table Section -->
      <div id="tableSection">
        <div class="row mb-2">
          <div class="col-md-3">
            <select id="lengthMenu" class="form-control">
              <option value="10">10 entries</option>
              <option value="20">20 entries</option>
              <option value="50">50 entries</option>
              <option value="100">100 entries</option>
              <option value="200">200 entries</option>
            </select>
          </div>
          <div class="col-md-3">
            <select id="filterTodayAll" class="form-control">
              <option value="today">Today's appointments</option>
              <option value="all">All appointments</option>
            </select>
          </div>
          <div class="col-md-6 text-right">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search by client name...">
          </div>
        </div>
        <table id="appointmentsListTable" class="table table-bordered table-hover">
          <thead>
            <tr>
              <th>Date</th><th>Time</th><th>Client</th><th>Staff</th><th>Service</th><th>Notes</th><th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="appointmentForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">New Appointment</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body" id="addApptFormContainer">
          <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Appointment</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Appointment</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="editAppointmentBody">
        <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
      </div>
      <div class="modal-footer">
        <!-- Edit Appointment form should include its own Save button within appointments_edit.php -->
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- JS: jQuery, Bootstrap, FullCalendar, DataTables, Select2, Moment -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/fullcalendar-scheduler/index.global.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

<script>
$(function(){
  // ─── 1) VIEW TOGGLE ─────────────────────────────────────────────
  $('#btnViewCalendar').click(function(){
    $('#btnViewCalendar').addClass('active');
    $('#btnViewTable').removeClass('active');
    $('#tableSection').hide();
    $('#calendarSection').show();
  });
  $('#btnViewTable').click(function(){
    $('#btnViewTable').addClass('active');
    $('#btnViewCalendar').removeClass('active');
    $('#calendarSection').hide();
    $('#tableSection').show();
    loadTable();
  });

  // ─── 2) MAIN CALENDAR INIT ─────────────────────────────────────
  let mainCalendar;
  fetch('calendar_resources.php')
    .then(r => r.json())
    .then(resources => {
      const el = document.getElementById('appointmentsMainCalendar');
      mainCalendar = new FullCalendar.Calendar(el, {
        schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
        plugins: FullCalendar.globalPlugins,
        timeZone: 'local',
        initialView: 'resourceTimeGridDay',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'resourceTimeGridDay,resourceTimeGridThreeDay,resourceTimeGridFiveDay'
        },
        views: {
          resourceTimeGridDay: { buttonText: '1 day' },
          resourceTimeGridThreeDay: { type: 'resourceTimeGrid', duration: { days: 3 }, buttonText: '3 days' },
          resourceTimeGridFiveDay: { type: 'resourceTimeGrid', duration: { days: 5 }, buttonText: '5 days' }
        },
        resources,
        events: 'appointment_events.php',
        editable: true,
        selectable: true,
        nowIndicator: true,
        eventClassNames: arg => {
          const now = new Date(), s = arg.event.start, e = arg.event.end || new Date(s.getTime()+1800000);
          return (now >= s && now < e) ? ['blinking-event'] : [];
        }
      });
      mainCalendar.render();
    });

  // ─── 3) ADD APPOINTMENT ──────────────────────────────────────────
  $('#btnAddAppt').click(() => {
    const $modal = $('#addAppointmentModal');
    const $container = $modal.find('#addApptFormContainer')
      .html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
    $modal.modal('show');

    $.get('add_appointment.php', formHtml => {
      $container.html(formHtml);

      // a) Select2
      $container.find('#existingClient, #staffSelect, #serviceSelect').select2({
        width: '100%', dropdownParent: $modal
      });

      // b) Category filter
      $modal.off('click', '.category-filter')
            .on('click', '.category-filter', function(){
        const cat = $(this).data('category-id');
        $container.find('#serviceList .draggable-service').each(function(){
          $(this).toggle(!cat || $(this).data('category-id') == cat);
        });
      });

      // c) Draggable services
      if (FullCalendar.Draggable) {
        $container.find('#serviceList .draggable-service').each(function(){
          const $svc = $(this);
          if (!$svc.data('draggableBound')) {
            const eventObj = { title: $svc.text().trim(), extendedProps: { service_id: $svc.data('service-id') } };
            $svc.data('event', eventObj);
            new FullCalendar.Draggable(this, {
              itemSelector: '.draggable-service',
              eventData: () => $svc.data('event')
            });
            $svc.data('draggableBound', true);
          }
        });
      }

      // d) Mini-calendar
      const modalEl = document.getElementById('appointmentCalendarModal');
      fetch('calendar_resources.php')
        .then(r => r.json())
        .then(resources => {
          const cal = new FullCalendar.Calendar(modalEl, {
            schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
            plugins: FullCalendar.globalPlugins,
            initialView: 'resourceTimeGridDay',
            headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'resourceTimeGridDay,resourceTimeGridThreeDay,resourceTimeGridFiveDay'
            },
            views: {
              resourceTimeGridDay: { buttonText: '1 day' },
              resourceTimeGridThreeDay: { type: 'resourceTimeGrid', duration: { days: 3 }, buttonText: '3 days' },
              resourceTimeGridFiveDay: { type: 'resourceTimeGrid', duration: { days: 5 }, buttonText: '5 days' }
            },
            slotMinTime: '06:00:00', slotMaxTime: '22:00:00',
            resources, editable: true, droppable: true, nowIndicator: true,
            eventReceive(info) { const evs = cal.getEvents(); if (evs.length>1) evs[0].remove(); },
            eventClick(info) { info.event.remove(); }
          });
          cal.render();
          $modal.data('calendarInstance', cal);

          // Go To Date
          const iso = new Date().toISOString().slice(0,10);
          $modal.find('#goToDate')
            .val(iso)
            .off('change')
            .on('change', function(){ cal.gotoDate(this.value); });
        });

      // e) Form submit
      $modal.off('submit','#appointmentForm')
            .on('submit','#appointmentForm', function(e){
        e.preventDefault();
        const cal = $modal.data('calendarInstance');
        const evs = cal.getEvents();
        if (!evs.length||!evs[0].start) return alert('Drag a service onto the calendar to pick a time.');
        const ev = evs[evs.length-1];
        const fmt = dt=> dt.toISOString().slice(0,19).replace('T',' ');
        const data = {
          service_id: ev.extendedProps.service_id,
          start_iso: fmt(ev.start),
          end_iso:   fmt(ev.end||new Date(ev.start.getTime()+1800000)),
          staff_id: ev.getResources()[0].id,
          notes: $modal.find('#notes').val().trim(),
          send_sms: $modal.find('#sendSmsToggle').is(':checked')?1:0
        };
        const existing = $modal.find('#existingClient').val();
        const nName    = $modal.find('#newClientName').val().trim();
        const nPhone   = $modal.find('#newClientPhone').val().trim();
        if (existing) data.client_id = existing;
        else if (nName&&nPhone) { data.client_name=nName; data.client_phone=nPhone; }
        else return alert('Select an existing client or enter new name & phone.');

        $.post('save_appointment.php',data,resp=>{
          if(resp.success){ $modal.modal('hide'); mainCalendar.refetchEvents(); }
          else alert('Error saving appointment: '+resp.error);
        },'json');
      });
    });
  });

  // ─── 4) CLEANUP ON HIDE ─────────────────────────────────────────
  $('#addAppointmentModal').on('hide.bs.modal', function(){
    const cal=$(this).data('calendarInstance'); if(cal) cal.destroy();
    $(this).removeData('calendarInstance');
    $('#appointmentForm')[0].reset();
    $('#serviceList .draggable-service').removeData('event');
    $('.modal-backdrop').remove();
  });

  // ─── 5) EDIT APPOINTMENT ───────────────────────────────────────
  $(document).on('click','.edit-apt-btn',function(){
    const id=$(this).data('id');
    $('#editAppointmentBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
    $('#editAppointmentModal').modal('show');
    $.get('appointments_edit.php',{id},html=>{
      $('#editAppointmentBody').html(html);
      $('#editAppointmentForm').on('submit',function(e){
        e.preventDefault();
        $.post('update_appointment.php',$(this).serialize(),resp=>{
          if(resp.success){$('#editAppointmentModal').modal('hide');mainCalendar.refetchEvents();}
          else alert('Error:'+resp.error);
        },'json');
      });
    });
  });

  // ─── 6) TABLE VIEW ─────────────────────────────────────────────
  let table;
  function loadTable(){
    if($.fn.DataTable.isDataTable('#appointmentsListTable')){table.ajax.reload();return;}
    table=$('#appointmentsListTable').DataTable({
      ajax:{url:'appointments_list.php',data:d=>{d.flag=$('#filterTodayAll').val();}},
      pageLength:parseInt($('#lengthMenu').val(),10),lengthChange:false,searching:false,
      columns:[
        {data:'appointment_date'},{data:'time'},{data:'client_name'},{data:'staff_name'},{data:'service_name'},{data:'notes'},
        {data:null,render:d=>`<button class="btn btn-sm btn-info edit-apt-btn" data-id="${d.id}">Edit</button>
         <button class="btn btn-sm btn-danger delete-apt-btn" data-id="${d.id}">Delete</button>`}
      ]
    });
    $('#lengthMenu').change(()=>table.page.len(parseInt($('#lengthMenu').val(),10)).draw());
    $('#filterTodayAll').change(loadTable);
    $('#tableSearch').on('keyup',()=>table.column(2).search($('#tableSearch').val()).draw());
    $('#appointmentsListTable').on('click','.delete-apt-btn',function(){
      const id=$(this).data('id');if(!confirm('Delete this appointment?'))return;
      $.post('delete_appointment.php',{id},r=>{ if(r.success)table.ajax.reload(); else alert('Error:'+r.error); },'json');
    });
  }
});
</script>