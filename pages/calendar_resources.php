<?php
// pages/calendar_resources.php — returns JSON list of therapists

require_once '../auth.php';
requirePermission($pdo, 'calendar_view.view');

// Fetch therapists who should show in calendar
$stmt = $pdo->prepare('
  SELECT id, first_name, last_name, color, position
    FROM therapists
   WHERE show_in_calendar = 1
   ORDER BY position ASC, first_name ASC, last_name ASC
');
$stmt->execute();
$therapists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resources = [];
foreach ($therapists as $t) {
    $resources[] = [
        'id'         => $t['id'],
        'title'      => $t['first_name'] . ' ' . $t['last_name'],
        'eventColor' => $t['color']
    ];
}

header('Content-Type: application/json');
echo json_encode($resources);