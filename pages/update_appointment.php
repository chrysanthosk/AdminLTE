<?php
// pages/update_appointment.php — AJAX endpoint to save edits
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

// 1) Fetch & validate ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid appointment ID']);
  exit();
}

// 2) Load the existing client_id so we can preserve it if no client data is posted
$origStmt = $pdo->prepare("SELECT client_id FROM appointments WHERE id = ?");
$origStmt->execute([$id]);
$origClientId = $origStmt->fetchColumn();  // may be null or >0

// 3) Gather & validate the rest of your fields
$appointment_date = trim($_POST['appointment_date'] ?? '');
$start_time       = trim($_POST['start_time']       ?? '');
$end_time         = trim($_POST['end_time']         ?? '');
$staff_id         = isset($_POST['staff_id'])   ? (int)$_POST['staff_id']   : 0;
$service_id       = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
$notes            = trim($_POST['notes']        ?? '');
$send_sms         = isset($_POST['send_sms'])   ? 1 : 0;

if (!$appointment_date || !$start_time || !$end_time || $staff_id <= 0 || $service_id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Missing required appointment fields.']);
  exit();
}

// 4) Decide whether the user is keeping the existing client, or creating a new one
$client_id    = $origClientId;   // by default, keep the old one
$client_name  = null;
$client_phone = null;

if (isset($_POST['newClientName']) || isset($_POST['newClientPhone'])) {
  // explicit “walk-in” branch
  $client_name  = trim($_POST['newClientName']  ?? '');
  $client_phone = trim($_POST['newClientPhone'] ?? '');
  if ($client_name === '' || $client_phone === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'New client name and phone are required.']);
    exit();
  }
  // drop the old client_id entirely:
  $client_id = null;
}

// 5) Now update
try {
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