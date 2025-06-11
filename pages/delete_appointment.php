<?php
// pages/delete_appointment.php — Deletes an appointment (JSON response)
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid ID']);
  exit();
}

try {
  $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
  $stmt->execute([$id]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}