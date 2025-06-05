<?php
// pages/update_appointment_time.php

header('Content-Type: application/json');
require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');

// Read & validate POST parameters
$id        = $_POST['id'] ?? '';
$startFull = $_POST['start_time'] ?? '';
$endFull   = $_POST['end_time']   ?? '';

// Basic sanity checks
if (empty($id) || empty($startFull) || empty($endFull)) {
    echo json_encode([ 'success' => false, 'error' => 'Missing parameters.' ]);
    exit;
}

// Validate / parse the datetime strings
// Expecting format "YYYY-MM-DD HH:mm:00"
if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startFull)
 || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endFull)
) {
    echo json_encode([ 'success' => false, 'error' => 'Invalid date/time format.' ]);
    exit;
}

// Split date vs. time
// $startFull = "2025-06-04 08:00:00"
$datePart  = substr($startFull, 0, 10);    // "2025-06-04"
$timeStart = substr($startFull, 11, 8);     // "08:00:00"
$timeEnd   = substr($endFull,   11, 8);     // "09:00:00"

// Now run the UPDATE
try {
    $stmt = $pdo->prepare("
        UPDATE appointments
           SET appointment_date = :appt_date,
               start_time       = :start_time,
               end_time         = :end_time,
               updated_at       = NOW()
         WHERE id = :id
    ");

    $stmt->execute([
        ':appt_date'  => $datePart,
        ':start_time' => $timeStart,
        ':end_time'   => $timeEnd,
        ':id'         => $id
    ]);

    if ($stmt->rowCount() === 0) {
        // No rows updated—perhaps invalid ID?
        echo json_encode([ 'success' => false, 'error' => 'No appointment found with that ID.' ]);
    } else {
        echo json_encode([ 'success' => true ]);
    }
} catch (PDOException $e) {
    // In case of SQL error
    echo json_encode([ 'success' => false, 'error' => 'Database error: ' . $e->getMessage() ]);
}


