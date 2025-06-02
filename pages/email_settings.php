<?php
// email_settings.php - configure SMTP settings
require_once '../auth.php';
requirePermission($pdo, 'email.manage');

$stmt = $pdo->query('SELECT * FROM email_settings LIMIT 1');
$settings = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_host = $_POST['smtp_host'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_user = $_POST['smtp_user'];
    $smtp_pass = $_POST['smtp_pass'];
    $smtp_secure = $_POST['smtp_secure'];

    if ($settings) {
        $stmt = $pdo->prepare('UPDATE email_settings SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_secure = ? WHERE id = ?');
        $stmt->execute([$smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure, $settings['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_secure]);
    }
    logAction($pdo, $_SESSION['user_id'], 'Updated email settings');
    header('Location: email_settings.php');
    exit();
}

$page_title = 'Email Settings';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Email Settings</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form method="post">
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="text" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>SMTP User</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>SMTP Secure (tls or ssl)</label>
                    <input type="text" name="smtp_secure" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_secure'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>