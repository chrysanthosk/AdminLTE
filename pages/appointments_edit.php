<?php
// File: pages/appointments_edit.php
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
  echo '<div class="alert alert-danger">Invalid appointment ID.</div>';
  exit;
}

$stmt = $pdo->prepare("SELECT
a.id,
a.appointment_date,
a.start_time,
a.end_time,
IFNULL(CONCAT(c.first_name,' ',c.last_name),a.client_name) AS client_name,
a.client_name AS new_client_name,
a.client_phone,
a.staff_id,
a.service_id,
a.notes
FROM appointments a
LEFT JOIN clients c ON a.client_id=c.id
WHERE a.id = ?");
$stmt->execute([$id]);
$appt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$appt) {
  echo '<div class="alert alert-danger">Appointment not found.</div>';
  exit;
}

// Fetch staff and services
$staff = $pdo->query("SELECT id, first_name, last_name FROM therapists ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<form id="editAppointmentForm">
  <input type="hidden" name="id" value="<?= $appt['id'] ?>">

  <div class="form-group">
    <label>Client</label>
    <input type="text" class="form-control" readonly
      value="<?= htmlspecialchars($appt['client_name'] ?: $appt['new_client_name'], ENT_QUOTES) ?>">
  </div>

  <div class="form-group">
    <label>Date</label>
    <input type="date" name="appointment_date" class="form-control" required
      value="<?= htmlspecialchars($appt['appointment_date'], ENT_QUOTES) ?>">
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Start Time</label>
      <input type="time" name="start_time" class="form-control" required
        value="<?= htmlspecialchars($appt['start_time'], ENT_QUOTES) ?>">
    </div>
    <div class="form-group col-md-6">
      <label>End Time</label>
      <input type="time" name="end_time" class="form-control" required
        value="<?= htmlspecialchars($appt['end_time'], ENT_QUOTES) ?>">
    </div>
  </div>

  <div class="form-group">
    <label>Staff</label>
    <select name="staff_id" class="form-control" required>
      <?php foreach ($staff as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $s['id']==$appt['staff_id']?'selected':'' ?>>
        <?= htmlspecialchars($s['first_name'].' '.$s['last_name'], ENT_QUOTES) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label>Service</label>
    <select name="service_id" class="form-control" required>
      <?php foreach ($services as $srv): ?>
      <option value="<?= $srv['id'] ?>" <?= $srv['id']==$appt['service_id']?'selected':'' ?>>
        <?= htmlspecialchars($srv['name'], ENT_QUOTES) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($appt['notes'], ENT_QUOTES) ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>