<?php
// pages/email_settings.php — configure and test SMTP settings

require_once '../auth.php';
requirePermission($pdo, 'email.manage');

// Load existing settings (if any)
$stmt = $pdo->query('SELECT * FROM email_settings LIMIT 1');
$settings = $stmt->fetch();

// Initialize status messages
$saveStatus = '';
$testStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grab fields from POST (may be empty if not submitted)
    $smtp_host   = trim($_POST['smtp_host']   ?? '');
    $smtp_port   = (int) ($_POST['smtp_port']   ?? 0);
    $smtp_user   = trim($_POST['smtp_user']   ?? '');
    $smtp_pass   = trim($_POST['smtp_pass']   ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? '');
    $from_email  = trim($_POST['from_email']  ?? '');
    $from_name   = trim($_POST['from_name']   ?? '');

    // If saving settings (Save Settings button clicked)
    if (isset($_POST['save_smtp'])) {
        if ($settings) {
            $stmt = $pdo->prepare('
                UPDATE email_settings
                   SET smtp_host   = ?,
                       smtp_port   = ?,
                       smtp_user   = ?,
                       smtp_pass   = ?,
                       smtp_secure = ?,
                       from_email  = ?,
                       from_name   = ?
                 WHERE id = ?
            ');
            $stmt->execute([
                $smtp_host,
                $smtp_port,
                $smtp_user,
                $smtp_pass,
                $smtp_secure,
                $from_email,
                $from_name,
                $settings['id']
            ]);
            logAction($pdo, $_SESSION['user_id'], 'Updated SMTP settings');
            $saveStatus = '<div class="alert alert-success">Settings updated successfully.</div>';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO email_settings
                    (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $smtp_host,
                $smtp_port,
                $smtp_user,
                $smtp_pass,
                $smtp_secure,
                $from_email,
                $from_name
            ]);
            logAction($pdo, $_SESSION['user_id'], 'Saved new SMTP settings');
            $saveStatus = '<div class="alert alert-success">Settings saved successfully.</div>';
        }

        // Reload settings from DB
        $stmt     = $pdo->query('SELECT * FROM email_settings LIMIT 1');
        $settings = $stmt->fetch();
    }

    // If testing SMTP (Test SMTP button clicked)
    if (isset($_POST['test_smtp'])) {
        // Determine which “From” to use: prefer just-posted values; otherwise fall back to saved settings
        $testFromEmail = !empty($from_email) ? $from_email : ($settings['from_email'] ?? '');
        $testFromName  = !empty($from_name)  ? $from_name  : ($settings['from_name']  ?? '');
        $testRecipient = trim($_POST['test_email'] ?? '');

        // Validate “From Email”
        if (empty($testFromEmail) || !filter_var($testFromEmail, FILTER_VALIDATE_EMAIL)) {
            $testStatus = '<div class="alert alert-danger">Please enter a valid “From” email address.</div>';
        }
        // Validate “From Name”
        elseif (empty($testFromName)) {
            $testStatus = '<div class="alert alert-danger">Please enter a valid “Send Name.”</div>';
        }
        // Validate “To” only if user actually filled it
        elseif (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            $testStatus = '<div class="alert alert-danger">Please enter a valid test email address.</div>';
        } else {
            // Use either the newly posted SMTP values or fallback to saved ones
            $host   = !empty($smtp_host)   ? $smtp_host   : ($settings['smtp_host']   ?? '');
            $port   = $smtp_port !== 0     ? $smtp_port   : ($settings['smtp_port']   ?? 0);
            $user   = !empty($smtp_user)   ? $smtp_user   : ($settings['smtp_user']   ?? '');
            $pass   = !empty($smtp_pass)   ? $smtp_pass   : ($settings['smtp_pass']   ?? '');
            $secure = !empty($smtp_secure) ? $smtp_secure : ($settings['smtp_secure'] ?? '');

            // Load PHPMailer
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // SMTP configuration
                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $user;
                $mail->Password   = $pass;
                $mail->SMTPSecure = $secure;   // "tls" or "ssl"
                $mail->Port       = $port;

                // Use the chosen “From Email” and “From Name”
                $mail->setFrom($testFromEmail, $testFromName);
                $mail->addAddress($testRecipient);

                $mail->isHTML(true);
                $mail->Subject = 'SMTP Settings Test';
                $mail->Body    = '<p>This is a test email to verify SMTP settings.</p>';
                $mail->AltBody = 'This is a test email to verify SMTP settings.';

                $mail->send();
                logAction($pdo, $_SESSION['user_id'], "Sent SMTP test to {$testRecipient}");
                $testStatus = '<div class="alert alert-success">'
                            . 'Test email sent successfully to '
                            . htmlspecialchars($testRecipient) . '.</div>';
            } catch (Exception $e) {
                $errorInfo = htmlspecialchars($mail->ErrorInfo);
                logAction($pdo, $_SESSION['user_id'], "Failed SMTP test: {$errorInfo}");
                $testStatus = '<div class="alert alert-danger">'
                            . 'Failed to send test email: ' . $errorInfo
                            . '</div>';
            }
        }
    }
}

// Prepare variables for form fields (use saved settings if no POST)
$smtp_host   = $settings['smtp_host']   ?? '';
$smtp_port   = $settings['smtp_port']   ?? '';
$smtp_user   = $settings['smtp_user']   ?? '';
$smtp_pass   = $settings['smtp_pass']   ?? '';
$smtp_secure = $settings['smtp_secure'] ?? '';
$from_email  = $settings['from_email']  ?? '';
$from_name   = $settings['from_name']   ?? '';

$page_title = 'Email Settings';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Email Settings</h1></div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <!-- Show status messages -->
      <?php echo $saveStatus; ?>
      <?php echo $testStatus; ?>

      <div class="card">
        <div class="card-body">
          <form method="post">
            <!-- SMTP Host -->
            <div class="form-group">
              <label>SMTP Host</label>
              <input
                type="text" name="smtp_host" class="form-control"
                value="<?php echo htmlspecialchars($smtp_host); ?>"
                placeholder="e.g. smtp.example.com" required>
            </div>

            <!-- SMTP Port -->
            <div class="form-group">
              <label>SMTP Port</label>
              <input
                type="number" name="smtp_port" class="form-control"
                value="<?php echo htmlspecialchars($smtp_port); ?>"
                placeholder="e.g. 587" required>
            </div>

            <!-- SMTP Username -->
            <div class="form-group">
              <label>SMTP Username</label>
              <input
                type="text" name="smtp_user" class="form-control"
                value="<?php echo htmlspecialchars($smtp_user); ?>"
                placeholder="e.g. user@example.com" required>
            </div>

            <!-- SMTP Password -->
            <div class="form-group">
              <label>SMTP Password</label>
              <input
                type="password" name="smtp_pass" class="form-control"
                value="<?php echo htmlspecialchars($smtp_pass); ?>"
                placeholder="SMTP account password" required>
            </div>

            <!-- SMTP Secure -->
            <div class="form-group">
              <label>SMTP Secure (tls or ssl)</label>
              <input
                type="text" name="smtp_secure" class="form-control"
                value="<?php echo htmlspecialchars($smtp_secure); ?>"
                placeholder="tls or ssl" required>
            </div>

            <!-- From Email -->
            <div class="form-group">
              <label>From Email Address</label>
              <input
                type="email" name="from_email" class="form-control"
                value="<?php echo htmlspecialchars($from_email); ?>"
                placeholder="e.g. noreply@example.com" required>
              <small class="form-text text-muted">
                The “From” address used when sending test or application emails.
              </small>
            </div>

            <!-- From Name -->
            <div class="form-group">
              <label>Send Name</label>
              <input
                type="text" name="from_name" class="form-control"
                value="<?php echo htmlspecialchars($from_name); ?>"
                placeholder="e.g. MyApp Notifications" required>
              <small class="form-text text-muted">
                The display name shown in the recipient’s inbox.
              </small>
            </div>

            <!-- Buttons: Save and Test -->
            <div class="form-row">
              <div class="form-group col-md-4">
                <button
                  type="submit" name="save_smtp"
                  class="btn btn-primary btn-block">
                  Save Settings
                </button>
              </div>
              <div class="form-group col-md-8">
                <div class="input-group">
                  <input
                    type="email" name="test_email" class="form-control"
                    placeholder="Enter an email address to test SMTP">
                  <div class="input-group-append">
                    <button
                      type="submit" name="test_smtp"
                      class="btn btn-secondary">
                      Test SMTP
                    </button>
                  </div>
                </div>
                <small class="form-text text-muted">
                  The test email will be sent from "<?php echo htmlspecialchars($from_name . ' <' . $from_email . '>'); ?>."
                </small>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>