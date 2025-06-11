<?php
// pages/edit_appointment.php — Returns HTML form for editing via AJAX
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

// 1) Get & validate ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'Invalid appointment ID';
  exit();
}

// 2) Fetch the appointment
$stmt = $pdo->prepare("
  SELECT
    a.*,
    COALESCE(CONCAT(c.first_name,' ',c.last_name), a.client_name) AS full_client_name
  FROM appointments a
  LEFT JOIN clients c ON a.client_id = c.id
  WHERE a.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$apt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$apt) {
  http_response_code(404);
  echo 'Appointment not found';
  exit();
}

// 3) Fetch dropdown data
$therapists = $pdo->query("
  SELECT id, first_name, last_name
    FROM therapists
   WHERE show_in_calendar = 1
   ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

$services = $pdo->query("
  SELECT id, name
    FROM services
   ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// 4) Output the **entire** form (matching add form’s ID!)
?>
<form id="assignFormEdit">
  <input type="hidden" name="id" value="<?= (int)$apt['id'] ?>">

  <div class="form-group">
    <label>Date</label>
    <input
      type="date"
      name="appointment_date"
      class="form-control"
      value="<?= htmlspecialchars($apt['appointment_date']) ?>"
      required
    >
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Start Time</label>
      <input
        type="time"
        name="start_time"
        class="form-control"
        value="<?= htmlspecialchars($apt['start_time']) ?>"
        required
      >
    </div>
    <div class="form-group col-md-6">
      <label>End Time</label>
      <input
        type="time"
        name="end_time"
        class="form-control"
        value="<?= htmlspecialchars($apt['end_time']) ?>"
        required
      >
    </div>
  </div>

  <div class="form-group">
    <label>Therapist</label>
    <select name="staff_id" class="form-control" required>
      <?php foreach ($therapists as $t): ?>
        <option
          value="<?= (int)$t['id'] ?>"
          <?= $t['id']==$apt['staff_id'] ? 'selected' : '' ?>
        >
          <?= htmlspecialchars($t['first_name'].' '.$t['last_name'],ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label>Client</label>
    <!-- readonly display + hidden client_id -->
    <input
      type="text"
      class="form-control mb-2"
      value="<?= htmlspecialchars($apt['full_client_name'],ENT_QUOTES) ?>"
      readonly
    >
    <input
      type="hidden"
      name="client_id"
      value="<?= (int)$apt['client_id'] ?>"
    >
  </div>

  <div class="form-group">
    <label>Service</label>
    <select name="service_id" class="form-control" required>
      <?php foreach ($services as $s): ?>
        <option
          value="<?= (int)$s['id'] ?>"
          <?= $s['id']==$apt['service_id'] ? 'selected' : '' ?>
        >
          <?= htmlspecialchars($s['name'],ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control"><?= htmlspecialchars($apt['notes'],ENT_QUOTES) ?></textarea>
  </div>

  <div class="form-check mb-3">
    <input
      type="checkbox"
      name="send_sms"
      id="edit_send_sms"
      class="form-check-input"
      <?= $apt['send_sms'] ? 'checked' : '' ?>
    >
    <label class="form-check-label" for="edit_send_sms">Send SMS</label>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
    <button type="button" class="btn btn-primary" id="saveAppointmentBtn">Save changes</button>
  </div>
</form>

<script>
// 5) Bind only once after injecting the form
$('#saveAppointmentBtn').on('click', function() {
  $.post('update_appointment.php', $('#assignFormEdit').serialize(), function(resp) {
    if (resp.success) {
      $('#assignModal').modal('hide');
      mainCalendar.refetchEvents();
    } else {
      alert('Error: ' + resp.error);
    }
  }, 'json');
});
</script>