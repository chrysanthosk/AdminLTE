<?php
// profile.php - user profile page
require_once '../auth.php';
require_once '../GoogleAuthenticator.php';

requireLogin();
$user = currentUser($pdo);

$ga = new PHPGangsta_GoogleAuthenticator();
$showQr = false;
$secret = $user['twofa_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $theme = $_POST['theme'];
    $password = $_POST['password'];
    $enable2fa = isset($_POST['twofa']) ? 1 : 0;

    if ($enable2fa && empty($secret)) {
        $secret = $ga->createSecret();
        $showQr = true;
    }

    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, theme = ?, twofa_enabled = ?, twofa_secret = ?, password_hash = ? WHERE id = ?');
        $stmt->execute([$first_name, $last_name, $email, $theme, $enable2fa, $secret, $password_hash, $user['id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, theme = ?, twofa_enabled = ?, twofa_secret = ? WHERE id = ?');
        $stmt->execute([$first_name, $last_name, $email, $theme, $enable2fa, $secret, $user['id']]);
    }

    logAction($pdo, $user['id'], 'Updated profile');
    header('Location: profile.php');
    exit();
}

$page_title = 'My Profile';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>My Profile</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form method="post">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <select name="theme" class="form-control">
                        <option value="light" <?php echo ($user['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo ($user['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" name="twofa" class="form-check-input" <?php echo ($user['twofa_enabled']) ? 'checked' : ''; ?>>
                    <label class="form-check-label">Enable 2FA</label>
                </div>
                <?php if ($user['twofa_enabled'] && $user['twofa_secret']): ?>
                    <div class="form-group">
                        <label>Existing 2FA Secret:</label>
                        <p><?php echo htmlspecialchars($user['twofa_secret']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($showQr): ?>
                    <div class="form-group">
                        <label>Scan this QR code with Google Authenticator:</label>
                        <br>
                        <img src="https://www.google.com/chart?chs=200x200&chld=M|0&cht=qr&chl=<?php echo urlencode('otpauth://totp/' . $user['email'] . '?secret=' . $secret); ?>" alt="QR Code">
                        <p>Secret: <?php echo htmlspecialchars($secret); ?></p>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>