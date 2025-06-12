<?php
// pages/appointments_export.php — CSV export for appointments
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');

// determine whether to fetch only today or all
$flag = ($_GET['flag'] ?? 'today') === 'all' ? 'all' : 'today';
$todayClause = $flag==='today'
  ? "WHERE appointment_date = CURDATE()"
  : "";

$stmt = $pdo->prepare("
  SELECT
    appointment_date,
    CONCAT(DATE_FORMAT(start_time,'%H:%i'),' – ',DATE_FORMAT(end_time,'%H:%i')) AS time,
    IFNULL(CONCAT(c.first_name,' ',c.last_name),a.client_name) AS client_name,
    CONCAT(t.first_name,' ',t.last_name) AS staff_name,
    srv.name AS service_name,
    a.notes
  FROM appointments a
  LEFT JOIN clients c ON a.client_id=c.id
  LEFT JOIN therapists t ON a.staff_id=t.id
  LEFT JOIN services srv ON a.service_id=srv.id
  $todayClause
  ORDER BY appointment_date, start_time
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// send CSV headers
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="appointments_'.$flag.'.csv"');

$fp = fopen('php://output','w');
// column headers
fputcsv($fp, ['Date','Time','Client','Staff','Service','Notes']);
foreach ($rows as $r) {
  fputcsv($fp, [
    $r['appointment_date'],
    $r['time'],
    $r['client_name'],
    $r['staff_name'],
    $r['service_name'],
    $r['notes']
  ]);
}
fclose($fp);
exit;