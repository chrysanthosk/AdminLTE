<?php
// pages/update_appointment_time.php — AJAX endpoint to update only time/staff
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

// Validate inputs
$id    = (int)($_POST['id']        ?? 0);
$start = trim($_POST['start_time'] ?? '');
$end   = trim($_POST['end_time']   ?? '');
$staff = (int)($_POST['staff_id']  ?? 0);

if ($id <= 0 || !$start || !$end) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Missing required fields.']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    UPDATE appointments
       SET start_time = :st,
           end_time   = :et,
           staff_id   = :sid,
           updated_at = NOW()
     WHERE id = :id
  ");
  $stmt->execute([
    'st'  => $start,
    'et'  => $end,
    'sid' => $staff,
    'id'  => $id
  ]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}