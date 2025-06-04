<?php
// pages/save_appointment.php
require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

$data = [
  'service_id'    => $_POST['service_id'] ?? null,
  'start_iso'     => $_POST['start_iso']  ?? null,
  'end_iso'       => $_POST['end_iso']    ?? null,
  'staff_id'      => $_POST['staff_id']   ?? null,
  'client_id'     => $_POST['client_id']  ?? null,
  'client_name'   => trim($_POST['client_name']  ?? ''),
  'client_phone'  => trim($_POST['client_phone'] ?? ''),
  'notes'         => trim($_POST['notes'] ?? ''),
  'send_sms'      => (int) ($_POST['send_sms'] ?? 0),
];

try {
  $pdo->beginTransaction();

  // If no existing client_id, create a new client
  if (empty($data['client_id'])) {
    if (!$data['client_name'] || !$data['client_phone']) {
      throw new Exception('New client name and phone are required if not selecting an existing client.');
    }
    $cstmt = $pdo->prepare("
      INSERT INTO clients (first_name, last_name, mobile)
      VALUES (?, ?, ?)
    ");
    // If you need to split first/last name, parse $data['client_name'] accordingly.
    $names = explode(' ', $data['client_name'], 2);
    $first = $names[0];
    $last  = $names[1] ?? '';
    $cstmt->execute([$first, $last, $data['client_phone']]);
    $data['client_id'] = $pdo->lastInsertId();
  }

  // Parse start/end from ISO to date + time
  $dtStart = new DateTime($data['start_iso']);
  $appointment_date = $dtStart->format('Y-m-d');
  $start_time = $dtStart->format('H:i:s');
  $dtEnd = new DateTime($data['end_iso']);
  $end_time = $dtEnd->format('H:i:s');

  // Insert appointment
  $astmt = $pdo->prepare("
    INSERT INTO appointments
      (appointment_date, start_time, end_time, staff_id,
       client_id, client_name, client_phone, service_id,
       notes, send_sms)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $astmt->execute([
    $appointment_date,
    $start_time,
    $end_time,
    $data['staff_id'],
    $data['client_id'],
    // Only store client_name/phone if a new client; else leave null
    $data['client_name'] ? $data['client_name'] : null,
    $data['client_phone'] ? $data['client_phone'] : null,
    $data['service_id'],
    $data['notes'],
    $data['send_sms']
  ]);

  $pdo->commit();
  echo json_encode(['success' => true]);
  exit;
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}