<?php
// File: pages/dashboard_settings.php

require_once '../auth.php';
requirePermission($pdo, 'dash_settings.manage');

// ------------------------------------------------------
// (A) Fetch or Create the Single Settings Row (id=1)
// ------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM dashboard_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
  // If no row exists, insert defaults:
  $insert = $pdo->prepare("
    INSERT INTO dashboard_settings (
      id,
      company_name, company_vat_number, company_phone_number, company_address,
      sms_appointments_enabled, sms_appointments_message,
      sms_birthdays_enabled, sms_birthdays_message,
      sms_sent_appointments_count, sms_sent_birthdays_count
    ) VALUES (
      1,
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

// ------------------------------------------------------
// (B) Handle Form Submission (POST => Save/Update)
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Collect and sanitize inputs:
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

// ------------------------------------------------------
// (C) Render the Page
// ------------------------------------------------------
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
        <div class="row">
          <!-- ─── Left Column: Company Information ────────────────────────────── -->
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
          </div>

          <!-- ─── Right Column: SMS Settings ──────────────────────────────────── -->
          <div class="col-md-6">
            <!-- (1) SMS Settings for Appointments -->
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

            <!-- (2) SMS Settings for Birthdays -->
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

        <!-- ─── Submit Button ─────────────────────────────────────────────────────── -->
        <div class="row">
          <div class="col-12 text-center mb-3">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save"></i> Save Settings
            </button>
          </div>
        </div>
      </form>

      <!-- ─── Placeholder Table: “SMS Information” ─────────────────────────────── -->
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