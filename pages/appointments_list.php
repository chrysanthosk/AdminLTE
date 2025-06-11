<?php
// File: pages/appointments_list.php
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');
header('Content-Type: application/json');

$flag = $_GET['flag'] ?? 'today';
$params = [];
$sql = "SELECT
a.id,
a.appointment_date,
a.start_time,
a.end_time,
IFNULL(CONCAT(c.first_name,' ',c.last_name),a.client_name) AS client_name,
CONCAT(t.first_name,' ',t.last_name) AS staff_name,
srv.name AS service_name,
a.notes
FROM appointments a
LEFT JOIN clients c ON a.client_id=c.id
LEFT JOIN therapists t ON a.staff_id=t.id
LEFT JOIN services srv ON a.service_id=srv.id";
if ($flag === 'today') {
    $sql .= " WHERE a.appointment_date = :today";
    $params['today'] = date('Y-m-d');
}
$sql .= " ORDER BY a.appointment_date DESC, a.start_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $time = date('H:i', strtotime($row['start_time'])) . ' – ' . date('H:i', strtotime($row['end_time']));
    $data[] = [
        'id' => (int)$row['id'],
        'appointment_date' => $row['appointment_date'],
        'time' => $time,
        'client_name' => $row['client_name'],
        'staff_name' => $row['staff_name'],
        'service_name' => $row['service_name'],
        'notes' => $row['notes'],
    ];
}
echo json_encode(['data' => $data]);