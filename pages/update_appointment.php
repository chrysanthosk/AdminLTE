<?php
// pages/update_appointment.php — AJAX endpoint to save edits
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

// 1) Fetch & validate ID
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid appointment ID']);
  exit();
}

// 2) Gather fields
$appointment_date = $_POST['appointment_date'] ?? '';
$start_time       = $_POST['start_time']       ?? '';
$end_time         = $_POST['end_time']         ?? '';
$staff_id         = (int)($_POST['staff_id']   ?? 0);
$service_id       = (int)($_POST['service_id'] ?? 0);
$notes            = trim($_POST['notes']      ?? '');
$send_sms         = isset($_POST['send_sms'])  ? 1 : 0;

// 3) Determine client
$client_id    = (int)($_POST['client_id'] ?? 0);
$client_name  = null;
$client_phone = null;
if ($client_id) {
  // existing client: clear name/phone so they stay null in DB
  $client_name  = null;
  $client_phone = null;
} else {
  // new walk-in: require both fields
  $client_name  = trim($_POST['newClientName']  ?? '');
  $client_phone = trim($_POST['newClientPhone'] ?? '');
  if ($client_name === '' || $client_phone === '') {
    http_response_code(400);
    echo json_encode([
      'success'=>false,
      'error'=>'New client name and phone are required if not selecting an existing client.'
    ]);
    exit();
  }
  // store name/phone and leave client_id null
  $client_id = null;
}

// 4) Validate required fields
if (!$appointment_date || !$start_time || !$end_time || $staff_id <= 0 || $service_id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Missing required appointment fields.']);
  exit();
}

try {
  // 5) Update the appointment row
  $stmt = $pdo->prepare("
    UPDATE appointments
       SET appointment_date = :ad,
           start_time       = :st,
           end_time         = :et,
           staff_id         = :sid,
           client_id        = :cid,
           client_name      = :cname,
           client_phone     = :cphone,
           service_id       = :svcid,
           notes            = :notes,
           send_sms         = :sms,
           updated_at       = NOW()
     WHERE id = :id
  ");
  $stmt->execute([
    'ad'     => $appointment_date,
    'st'     => $start_time,
    'et'     => $end_time,
    'sid'    => $staff_id,
    'cid'    => $client_id,
    'cname'  => $client_name,
    'cphone' => $client_phone,
    'svcid'  => $service_id,
    'notes'  => $notes,
    'sms'    => $send_sms,
    'id'     => $id
  ]);

  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}