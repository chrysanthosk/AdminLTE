<?php
// pages/appointment_events.php
// Feeds FullCalendar with a JSON array of all appointments,
// always showing “Service Name – Client Name” (or just Service Name if no client).

require_once '../auth.php';
requirePermission($pdo, 'appointment.manage');

header('Content-Type: application/json');

// 1) Fetch all appointments, join to clients (for client name), join to services → service_categories
$stmt = $pdo->prepare("
  SELECT
    a.id,
    a.appointment_date,
    a.start_time,
    a.end_time,
    a.staff_id            AS resourceId,
    a.client_id,
    a.client_name         AS free_client_name,
    c.first_name          AS client_first,
    c.last_name           AS client_last,
    s.name                AS service_name,
    sc.color              AS event_color
  FROM appointments a
  LEFT JOIN clients c
    ON a.client_id = c.id
  JOIN services s
    ON a.service_id = s.id
  JOIN service_categories sc
    ON s.category_id = sc.id
  ORDER BY a.appointment_date, a.start_time
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
foreach ($rows as $r) {
  // 2) Build ISO 8601 datetime strings for FullCalendar
  $startISO = $r['appointment_date'] . 'T' . $r['start_time'];
  $endISO   = $r['appointment_date'] . 'T' . $r['end_time'];

  // 3) Determine client name (if any)
  if (!empty($r['free_client_name'])) {
    // User typed in a free-text name
    $clientName = $r['free_client_name'];
  } elseif (!empty($r['client_id'])) {
    // Existing client_id → concatenate first + last
    $clientName = trim($r['client_first'] . ' ' . $r['client_last']);
  } else {
    // No client at all
    $clientName = '';
  }

  // 4) Build the event title:
  // Always show “Service Name” first;
  // if $clientName is nonempty, append “ – Client Name”
  if ($clientName !== '') {
    $title = "{$r['service_name']} – {$clientName}";
  } else {
    $title = $r['service_name'];
  }
  $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE);

  // 5) Push into $events array, including the category color
  $events[] = [
    'id'         => $r['id'],
    'resourceId' => $r['resourceId'],
    'title'      => $title,
    'start'      => $startISO,
    'end'        => $endISO,
    'color'      => $r['event_color']
  ];
}

echo json_encode($events);