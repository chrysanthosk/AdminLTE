<?php
// File: pages/insert_add_modal.php
// Partial view for "Add Appointment" modal (included in appointments.php)
?>
<div class="modal fade" id="addAppointmentModal" tabindex="-1" role="dialog" aria-labelledby="addAppointmentLabel">
  <div class="modal-dialog modal-lg" role="document">
    <form id="appointmentForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addAppointmentLabel">New Appointment</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Client fields -->
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
            <label class="form-check-label" for="sendSmsToggle">Send SMS (future feature)</label>
          </div>

          <hr/>

          <!-- Filter by Service Category -->
          <div class="mb-2">
            <strong>Filter by Category:</strong>
            <?php
            $cstmt = $pdo->query("SELECT id, name, color FROM service_categories ORDER BY name");
            while ($cat = $cstmt->fetch(PDO::FETCH_ASSOC)) {
              $catName  = htmlspecialchars($cat['name']);
              $catColor = htmlspecialchars($cat['color']);
              echo "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary category-filter\" data-category-id=\"{$cat['id']}\">"
                 . "<span class=\"category-color-box\" style=\"background:{$catColor};\"></span>"
                 . "{$catName}</button> ";
            }
            echo '<button type="button" class="btn btn-sm btn-outline-secondary category-filter" data-category-id="">'
               . '<span class="category-color-box" style="background:#6c757d;"></span>All</button>';
            ?>
          </div>

          <!-- Draggable Services List -->
          <div id="serviceList" class="mb-3" style="max-height:150px; overflow-y:auto; border:1px solid #444; padding:10px;">
            <?php
            $sstmt = $pdo->query("SELECT s.id, s.name, s.category_id, sc.color AS cat_color
              FROM services s
              JOIN service_categories sc ON s.category_id = sc.id
              ORDER BY s.name");
            while ($srv = $sstmt->fetch(PDO::FETCH_ASSOC)) {
              $srvLabel = htmlspecialchars($srv['name']);
              $catId    = (int)$srv['category_id'];
              $bgColor  = htmlspecialchars($srv['cat_color']);
              echo "<div class=\"draggable-service\" data-service-id=\"{$srv['id']}\" data-category-id=\"{$catId}\" "
                 . "style=\"margin-bottom:5px; padding:5px; background:{$bgColor}; color:#fff; cursor:move; border-radius:3px;\">"
                 . "{$srvLabel}</div>";
            }
            ?>
          </div>
          <hr/>

          <!-- Go To Date selector -->
          <div class="form-group">
            <label for="goToDate">Go To Date:</label>
            <input type="date" id="goToDate" class="form-control" />
          </div>

          <!-- Mini Calendar for Drag‐Drop -->
          <div id="appointmentCalendarModal" style="height:350px; border:1px solid #444;"></div>
          <input type="hidden" id="hiddenServiceId" name="service_id" />
          <input type="hidden" id="hiddenStartTime" name="start_iso" />
          <input type="hidden" id="hiddenEndTime" name="end_iso" />
          <input type="hidden" id="hiddenStaffId" name="staff_id" />
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add Appointment</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
