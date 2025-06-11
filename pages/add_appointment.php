<?php
// pages/add_appointment.php — AJAX‐only “add” form
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'appointment.manage');
?>
<style>
  /* only styling the mini-calendar container */
  #appointmentCalendarModal { width: 100% !important; }
</style>

<!-- NOTE: no <form> or <div class="modal-content"> or modal-header/footer here,
     those come from the wrapper in appointments.php -->

<form id="appointmentForm">
  <div class="form-row">
    <div class="form-group col-md-6">
      <label for="existingClient">Existing Client</label>
      <select id="existingClient" name="client_id" class="form-control w-100">
        <option value=""></option>
        <?php
        $stmt = $pdo->query("SELECT id, first_name, last_name, mobile FROM clients ORDER BY first_name, last_name");
        while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $label = htmlspecialchars("{$c['first_name']} {$c['last_name']} ({$c['mobile']})");
          echo "<option value=\"{$c['id']}\">{$label}</option>";
        }
        ?>
      </select>
    </div>
    <div class="form-group col-md-6">
      <label for="newClientName">New Client Name</label>
      <input type="text" class="form-control" id="newClientName" name="client_name" placeholder="If not on list" />
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label for="newClientPhone">New Client Phone</label>
      <input type="text" class="form-control" id="newClientPhone" name="client_phone" placeholder="Phone" />
    </div>
    <div class="form-group col-md-6">
      <label for="notes">Notes</label>
      <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any notes…"></textarea>
    </div>
  </div>

  <div class="form-group form-check">
    <input type="checkbox" class="form-check-input" id="sendSmsToggle" name="send_sms" />
    <label class="form-check-label" for="sendSmsToggle">Send SMS</label>
  </div>

  <hr/>

  <strong>Filter by Category:</strong>
  <div class="mb-2">
    <?php
    $cstmt = $pdo->query("SELECT id, name, color FROM service_categories ORDER BY name");
    while ($cat = $cstmt->fetch(PDO::FETCH_ASSOC)) {
      $nm = htmlspecialchars($cat['name']);
      $clr = htmlspecialchars($cat['color']);
      echo "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary category-filter\" data-category-id=\"{$cat['id']}\">"
         . "<span class=\"category-color-box\" style=\"background:{$clr};\"></span>{$nm}</button> ";
    }
    echo '<button type="button" class="btn btn-sm btn-outline-secondary category-filter" data-category-id="">'
       . '<span class="category-color-box" style="background:#6c757d;"></span>All</button>';
    ?>
  </div>

  <div id="serviceList" style="border:1px solid #ddd; padding:10px; max-height:150px; overflow-y:auto;">
    <?php
    $sstmt = $pdo->query("
      SELECT s.id, s.name, s.category_id, sc.color AS cat_color
        FROM services s
        JOIN service_categories sc ON s.category_id = sc.id
       ORDER BY s.name
    ");
    while ($srv = $sstmt->fetch(PDO::FETCH_ASSOC)) {
      $label = htmlspecialchars($srv['name']);
      $bg    = htmlspecialchars($srv['cat_color']);
      echo "<div class=\"draggable-service\" data-service-id=\"{$srv['id']}\" data-category-id=\"{$srv['category_id']}\" "
         . "style=\"background:{$bg};color:#fff;padding:5px;margin-bottom:5px;cursor:move;border-radius:3px;\">"
         . "{$label}</div>";
    }
    ?>
  </div>

  <hr/>

  <div class="form-group">
    <label for="goToDate">Go To Date:</label>
    <input type="date" id="goToDate" class="form-control" />
  </div>

  <div id="appointmentCalendarModal" style="height:350px; border:1px solid #ddd;"></div>

  <!-- hidden fields for POST -->
  <input type="hidden" name="service_id">
  <input type="hidden" name="start_iso">
  <input type="hidden" name="end_iso">
  <input type="hidden" name="staff_id">
</form>