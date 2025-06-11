<?php
// pages/save_appointment.php
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

// Collect & validate inputs
$data = [
  'service_id'   => isset($_POST['service_id'])   ? intval($_POST['service_id'])   : null,
  'start_iso'    => $_POST['start_iso']    ?? null,
  'end_iso'      => $_POST['end_iso']      ?? null,
  'staff_id'     => isset($_POST['staff_id'])     ? intval($_POST['staff_id'])     : null,
  'client_id'    => isset($_POST['client_id'])    ? intval($_POST['client_id'])    : null,
  'client_name'  => trim($_POST['client_name']  ?? ''),
  'client_phone' => trim($_POST['client_phone'] ?? ''),
  'notes'        => trim($_POST['notes']       ?? ''),
  'send_sms'     => isset($_POST['send_sms'])    ? 1 : 0,
];

// Check required fields
if (!$data['service_id'] || !$data['start_iso'] || !$data['end_iso'] || !$data['staff_id']) {
    echo json_encode(['success' => false, 'error' => 'Missing required appointment data (service, time, or staff).']);
    exit;
}

try {
    $pdo->beginTransaction();

    // If no existing client_id, create a new client
    if (empty($data['client_id'])) {
        if (!$data['client_name'] || !$data['client_phone']) {
            throw new Exception('New client name and phone are required if no existing client is selected.');
        }
        $cstmt = $pdo->prepare("
            INSERT INTO clients (first_name, last_name, mobile)
            VALUES (?, ?, ?)
        ");
        $names = explode(' ', $data['client_name'], 2);
        $first = $names[0];
        $last  = $names[1] ?? '';
        $cstmt->execute([$first, $last, $data['client_phone']]);
        $data['client_id'] = $pdo->lastInsertId();
    }

    // Parse start/end from ISO to date + time
    try {
        $dtStart = new DateTime($data['start_iso']);
        $dtEnd   = new DateTime($data['end_iso']);
    } catch (Exception $e) {
        throw new Exception('Invalid date/time format.');
    }

    $appointment_date = $dtStart->format('Y-m-d');
    $start_time       = $dtStart->format('H:i:s');
    $end_time         = $dtEnd->format('H:i:s');

    // Insert appointment
    $astmt = $pdo->prepare("
        INSERT INTO appointments
          (appointment_date, start_time, end_time, staff_id,
           client_id, client_name, client_phone, service_id,
           notes, send_sms)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ok = $astmt->execute([
        $appointment_date,
        $start_time,
        $end_time,
        $data['staff_id'],
        $data['client_id'],
        // Only store name/phone if new client
        $data['client_id'] ? null : $data['client_name'],
        $data['client_id'] ? null : $data['client_phone'],
        $data['service_id'],
        $data['notes'],
        $data['send_sms']
    ]);

    if (!$ok) {
        $info = $astmt->errorInfo();
        throw new Exception('Database error: ' . ($info[2] ?? 'unknown'));
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}