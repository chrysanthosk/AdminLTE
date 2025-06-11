<?php
// pages/today_appointments.php — snippet for Today’s Appointments
$today = date('Y-m-d');
$stmt = $pdo->prepare("
  SELECT id, start_time, end_time,
         COALESCE(CONCAT(c.first_name,' ',c.last_name), client_name) AS client_name,
         CONCAT(t.first_name,' ',t.last_name) AS staff_name,
         srv.name AS service_name, notes
    FROM appointments a
    LEFT JOIN clients    c   ON a.client_id   = c.id
    LEFT JOIN therapists t   ON a.staff_id    = t.id
    LEFT JOIN services   srv ON a.service_id  = srv.id
   WHERE a.appointment_date = :today
   ORDER BY a.start_time
");
$stmt->execute(['today'=>$today]);
?>
<div id="todayAptsContainer">
  <h3>Today’s Appointments</h3>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Time</th><th>Client</th><th>Staff</th><th>Service</th>
        <th>Notes</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r=$stmt->fetch(PDO::FETCH_ASSOC)):
        $st=date('H:i',strtotime($r['start_time']));
        $en=date('H:i',strtotime($r['end_time']));
      ?>
      <tr>
        <td><?= "$st–$en" ?></td>
        <td><?= htmlspecialchars($r['client_name'],ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r['staff_name'],ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r['service_name'],ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r['notes'],ENT_QUOTES) ?></td>
        <td>
          <button class="btn btn-sm btn-info"
            onclick="openAssignModal(<?= $r['id'] ?>,0,0,1,'')">
            Edit
          </button>
          <button class="btn btn-sm btn-danger"
            onclick="deleteApt(<?= $r['id'] ?>)">
            Delete
          </button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>