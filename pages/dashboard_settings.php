<?php
// File: pages/dashboard_settings.php

require_once '../auth.php';
requirePermission($pdo, 'dash_settings.manage');

// ────────────────────────────────────────────────────────────────────────────
// (A) AJAX Handlers for Payment Methods (add/edit/delete)
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pm_action'])) {
  header('Content-Type: application/json');
  $action = $_POST['pm_action'];

  try {
    if ($action === 'add') {
      // Add a new payment method
      $name = trim($_POST['name'] ?? '');
      if ($name === '') {
        throw new Exception('Name cannot be empty.');
      }
      // Insert
      $ins = $pdo->prepare("INSERT INTO payment_methods (name) VALUES (:name)");
      $ins->execute(['name' => $name]);
      $newId = $pdo->lastInsertId();
      echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
      exit();
    }

    if ($action === 'edit') {
      // Edit an existing payment method
      $id   = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      if ($id <= 0 || $name === '') {
        throw new Exception('Invalid ID or Name.');
      }
      $upd = $pdo->prepare("UPDATE payment_methods SET name = :name WHERE id = :id");
      $upd->execute(['name' => $name, 'id' => $id]);
      echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
      exit();
    }

    if ($action === 'delete') {
      // Delete a payment method
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new Exception('Invalid ID.');
      }
      $del = $pdo->prepare("DELETE FROM payment_methods WHERE id = :id");
      $del->execute(['id' => $id]);
      echo json_encode(['success' => true, 'id' => $id]);
      exit();
    }

    throw new Exception('Unknown action.');
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
  }
}

// ────────────────────────────────────────────────────────────────────────────
// (B) Fetch or Create the Single Settings Row (id=1)
// ────────────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM dashboard_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
  // If no row exists, insert defaults:
  $insert = $pdo->prepare("
    INSERT INTO dashboard_settings (
      id,dashboard_name
      company_name, company_vat_number, company_phone_number, company_address,
      sms_appointments_enabled, sms_appointments_message,
      sms_birthdays_enabled, sms_birthdays_message,
      sms_sent_appointments_count, sms_sent_birthdays_count
    ) VALUES (
      1,,'',
      '', '', '', '',
      0, '',
      0, '',
      0, 0
    )
  ");
  $insert->execute();
  // Re‐fetch:
  $stmt->execute();
  $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ────────────────────────────────────────────────────────────────────────────
// (C) Handle Form Submission (POST => Save/Update Dashboard Settings)
// (unchanged from previous version)
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['pm_action'])) {
  // Collect and sanitize inputs:
  $dashboardName        = trim($_POST['dashboard_name'] ?? '');
  $company_name         = trim($_POST['company_name'] ?? '');
  $company_vat_number   = trim($_POST['company_vat_number'] ?? '');
  $company_phone_number = trim($_POST['company_phone_number'] ?? '');
  $company_address      = trim($_POST['company_address'] ?? '');

  $sms_appt_enabled  = isset($_POST['sms_appointments_enabled']) ? 1 : 0;
  $sms_appt_message  = trim($_POST['sms_appointments_message'] ?? '');
  if (strlen($sms_appt_message) > 165) {
    $sms_appt_message = substr($sms_appt_message, 0, 165);
  }

  $sms_bday_enabled  = isset($_POST['sms_birthdays_enabled']) ? 1 : 0;
  $sms_bday_message  = trim($_POST['sms_birthdays_message'] ?? '');
  if (strlen($sms_bday_message) > 165) {
    $sms_bday_message = substr($sms_bday_message, 0, 165);
  }

  // Update the row with id=1
  $update = $pdo->prepare("
    UPDATE dashboard_settings SET
      dashboard_name             = :dashboard_name,
      company_name                 = :company_name,
      company_vat_number           = :company_vat_number,
      company_phone_number         = :company_phone_number,
      company_address              = :company_address,
      sms_appointments_enabled     = :sms_appointments_enabled,
      sms_appointments_message     = :sms_appointments_message,
      sms_birthdays_enabled        = :sms_birthdays_enabled,
      sms_birthdays_message        = :sms_birthdays_message
    WHERE id = 1
  ");
  $update->execute([
    'dashboard_name'           => $dashboardName,
    'company_name'               => $company_name,
    'company_vat_number'         => $company_vat_number,
    'company_phone_number'       => $company_phone_number,
    'company_address'            => $company_address,
    'sms_appointments_enabled'   => $sms_appt_enabled,
    'sms_appointments_message'   => $sms_appt_message,
    'sms_birthdays_enabled'      => $sms_bday_enabled,
    'sms_birthdays_message'      => $sms_bday_message
  ]);

  // Re‐fetch for display
  $stmt->execute();
  $settings = $stmt->fetch(PDO::FETCH_ASSOC);

  $flashMessage = "Settings updated successfully.";
}

// ────────────────────────────────────────────────────────────────────────────
// (D) Fetch all Payment Methods for display in the “Payment Options” section
// ────────────────────────────────────────────────────────────────────────────
$pmStmt = $pdo->query("SELECT id, name FROM payment_methods ORDER BY name ASC");
$allPaymentMethods = $pmStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Dashboard Settings</h1>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content -->
  <section class="content">
    <div class="container-fluid">

      <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= htmlspecialchars($flashMessage) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="dashboard_settings.php">
<!-- Global Settings -->
      <div class="card card-primary">
        <div class="card-header">
          <h3 class="card-title">Global Settings</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label for="dashboardName">Dashboard Name</label>
            <input
              type="text"
              class="form-control"
              id="dashboardName"
              name="dashboard_name"
              value="<?= htmlspecialchars($settings['dashboard_name'] ?? '', ENT_QUOTES) ?>"
              placeholder="Enter a display name for your dashboard"
            >
          </div>
        </div>
      </div>
        <div class="row">
          <!-- ─── Left Column: Company Information ──────────────────────────────── -->
          <div class="col-md-6">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Company Information</h3>
              </div>
              <div class="card-body">
                <!-- Company Name -->
                <div class="form-group">
                  <label for="company_name">Company Name</label>
                  <input
                    type="text"
                    class="form-control"
                    id="company_name"
                    name="company_name"
                    value="<?= htmlspecialchars($settings['company_name']) ?>"
                    required
                  >
                </div>

                <!-- VAT Number -->
                <div class="form-group">
                  <label for="company_vat_number">Company VAT Number</label>
                  <input
                    type="text"
                    class="form-control"
                    id="company_vat_number"
                    name="company_vat_number"
                    value="<?= htmlspecialchars($settings['company_vat_number']) ?>"
                  >
                </div>

                <!-- Phone Number -->
                <div class="form-group">
                  <label for="company_phone_number">Company Telephone Number</label>
                  <input
                    type="text"
                    class="form-control"
                    id="company_phone_number"
                    name="company_phone_number"
                    value="<?= htmlspecialchars($settings['company_phone_number']) ?>"
                  >
                </div>

                <!-- Address -->
                <div class="form-group">
                  <label for="company_address">Company Address</label>
                  <textarea
                    class="form-control"
                    id="company_address"
                    name="company_address"
                    rows="3"
                  ><?= htmlspecialchars($settings['company_address']) ?></textarea>
                </div>
              </div>
            </div>

            <!-- ─── New “Payment Options” Section ─────────────────────────── -->
            <div class="card card-success">
              <div class="card-header">
                <h3 class="card-title">Payment Options</h3>
              </div>
              <div class="card-body">
                <!-- (D1) Add New Payment Method Form -->
                <div class="form-inline mb-3">
                  <input
                    type="text"
                    id="newPaymentMethod"
                    class="form-control"
                    placeholder="New payment method…"
                    style="width: auto; margin-right: 8px;"
                  >
                  <button type="button" id="btnAddPM" class="btn btn-primary">Add</button>
                </div>

                <!-- (D2) Table of Existing Payment Methods -->
                <table class="table table-bordered" id="paymentMethodsTable">
                  <thead>
                    <tr>
                      <th style="width: 70%;">Name</th>
                      <th style="width: 30%;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allPaymentMethods as $pm): ?>
                      <tr data-id="<?= $pm['id'] ?>">
                        <td class="pm-name"><?= htmlspecialchars($pm['name']) ?></td>
                        <td>
                          <button type="button" class="btn btn-sm btn-info btnEditPM" title="Edit">
                            <i class="fas fa-edit"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-danger btnDeletePM" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ─── Right Column: SMS Settings ──────────────────────────────────── -->
          <div class="col-md-6">
            <!-- SMS Settings for Appointments -->
            <div class="card card-info">
              <div class="card-header">
                <h3 class="card-title">SMS Settings for Appointments</h3>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <div class="custom-control custom-switch">
                    <input
                      type="checkbox"
                      class="custom-control-input"
                      id="sms_appointments_enabled"
                      name="sms_appointments_enabled"
                      <?= $settings['sms_appointments_enabled'] ? 'checked' : '' ?>
                    >
                    <label class="custom-control-label" for="sms_appointments_enabled">
                      Send SMS for Appointments
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label for="sms_appointments_message">SMS Message (max 165 chars)</label>
                  <textarea
                    class="form-control"
                    id="sms_appointments_message"
                    name="sms_appointments_message"
                    rows="3"
                    maxlength="165"
                    placeholder="Enter appointment SMS template…"
                  ><?= htmlspecialchars($settings['sms_appointments_message']) ?></textarea>
                </div>
              </div>
            </div>

            <!-- SMS Settings for Birthdays -->
            <div class="card card-warning">
              <div class="card-header">
                <h3 class="card-title">SMS Settings for Birthdays</h3>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <div class="custom-control custom-switch">
                    <input
                      type="checkbox"
                      class="custom-control-input"
                      id="sms_birthdays_enabled"
                      name="sms_birthdays_enabled"
                      <?= $settings['sms_birthdays_enabled'] ? 'checked' : '' ?>
                    >
                    <label class="custom-control-label" for="sms_birthdays_enabled">
                      Send SMS for Birthdays
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label for="sms_birthdays_message">SMS Message (max 165 chars)</label>
                  <textarea
                    class="form-control"
                    id="sms_birthdays_message"
                    name="sms_birthdays_message"
                    rows="3"
                    maxlength="165"
                    placeholder="Enter birthday SMS template…"
                  ><?= htmlspecialchars($settings['sms_birthdays_message']) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Save Dashboard Settings -->
        <div class="row">
          <div class="col-12 text-center mb-3">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save"></i> Save Settings
            </button>
          </div>
        </div>
      </form>

      <!-- Placeholder SMS Info Table (unchanged) -->
      <div class="row">
        <div class="col-12">
          <div class="card card-outline card-secondary">
            <div class="card-header">
              <h3 class="card-title">SMS Information (Placeholder)</h3>
            </div>
            <div class="card-body p-0">
              <table class="table table-hover" id="dashboardSmsTable">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>SMS Status &amp; Message</th>
                    <th>SMS Sent Count</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Appointments</td>
                    <td>
                      <?= $settings['sms_appointments_enabled']
                          ? '<span class="text-success">Enabled</span>:<br>'
                            . nl2br(htmlspecialchars($settings['sms_appointments_message']))
                          : '<span class="text-muted">Disabled</span>'
                      ?>
                    </td>
                    <td><?= (int)$settings['sms_sent_appointments_count'] ?></td>
                  </tr>
                  <tr>
                    <td>Birthdays</td>
                    <td>
                      <?= $settings['sms_birthdays_enabled']
                          ? '<span class="text-success">Enabled</span>:<br>'
                            . nl2br(htmlspecialchars($settings['sms_birthdays_message']))
                          : '<span class="text-muted">Disabled</span>'
                      ?>
                    </td>
                    <td><?= (int)$settings['sms_sent_birthdays_count'] ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div> <!-- /.container-fluid -->
  </section> <!-- /.content -->
</div> <!-- /.content-wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- ─────────────────────────────────────────────────────────────────────────────── -->
<!-- Required JS: jQuery, Bootstrap, plus our custom Payment‐Methods JS below -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
  // ─── (PM1) Add New Payment Method via AJAX ─────────────────────────────
  $('#btnAddPM').click(function(e) {
    e.preventDefault();
    const name = $('#newPaymentMethod').val().trim();
    if (!name) {
      return alert('Please enter a payment method name.');
    }
    $.post('dashboard_settings.php', {
      pm_action: 'add',
      name: name
    }, function(resp) {
      if (resp.success) {
        // Append new row to table
        const newId = resp.id;
        const newName = resp.name;
        $('#paymentMethodsTable tbody').append(`
          <tr data-id="${newId}">
            <td class="pm-name">${$('<div>').text(newName).html()}</td>
            <td>
              <button class="btn btn-sm btn-info btnEditPM" title="Edit">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-danger btnDeletePM" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        `);
        $('#newPaymentMethod').val('');
      } else {
        alert('Error: ' + resp.error);
      }
    }, 'json');
  });

  // ─── (PM2) Edit Payment Method (open modal) ───────────────────────────
  let editPMId = null;
  $('#paymentMethodsTable').on('click', '.btnEditPM', function() {
    const $tr = $(this).closest('tr');
    editPMId = $tr.data('id');
    const currentName = $tr.find('.pm-name').text();
    $('#editPaymentMethodInput').val(currentName);
    $('#editPaymentMethodModal').modal('show');
  });

  // ─── (PM3) Save Edited Payment Method via AJAX ───────────────────────
  $('#btnSaveEditPM').click(function() {
    const newName = $('#editPaymentMethodInput').val().trim();
    if (!newName) {
      return alert('Name cannot be empty.');
    }
    $.post('dashboard_settings.php', {
      pm_action: 'edit',
      id: editPMId,
      name: newName
    }, function(resp) {
      if (resp.success) {
        // Update table row
        $(`#paymentMethodsTable tbody tr[data-id="${editPMId}"] .pm-name`)
          .text(resp.name);
        $('#editPaymentMethodModal').modal('hide');
      } else {
        alert('Error: ' + resp.error);
      }
    }, 'json');
  });

  // ─── (PM4) Delete Payment Method via AJAX ────────────────────────────
  $('#paymentMethodsTable').on('click', '.btnDeletePM', function() {
    const $tr = $(this).closest('tr');
    const id = $tr.data('id');
    if (!confirm('Delete this payment method?')) return;
    $.post('dashboard_settings.php', {
      pm_action: 'delete',
      id: id
    }, function(resp) {
      if (resp.success) {
        $tr.remove();
      } else {
        alert('Error: ' + resp.error);
      }
    }, 'json');
  });
});
</script>

<!-- ─────────────────────────────────────────────────────────────────────────────── -->
<!-- Edit‐Payment Modal (invisible until triggered) -->
<div
  class="modal fade"
  id="editPaymentMethodModal"
  tabindex="-1"
  role="dialog"
  aria-labelledby="editPaymentMethodLabel"
  aria-hidden="true"
>
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editPaymentMethodLabel">Edit Payment Method</h5>
        <button
          type="button"
          class="close"
          data-dismiss="modal"
          aria-label="Close"
        >
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input
          type="text"
          id="editPaymentMethodInput"
          class="form-control"
          placeholder="Payment method name"
        >
      </div>
      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          data-dismiss="modal"
        >
          Cancel
        </button>
        <button
          type="button"
          class="btn btn-primary"
          id="btnSaveEditPM"
        >
          Save
        </button>
      </div>
    </div>
  </div>
</div>