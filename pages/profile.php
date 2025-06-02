<?php
// pages/profile.php — user profile page

require_once '../auth.php';
require_once '../GoogleAuthenticator.php';

requireLogin();
$user   = currentUser($pdo);
$ga     = new PHPGangsta_GoogleAuthenticator();
$secret = $user['twofa_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $email      = $_POST['email'];
    $theme      = $_POST['theme'];
    $password   = $_POST['password'];
    $enable2fa  = isset($_POST['twofa']) ? 1 : 0;

    if ($enable2fa && empty($secret)) {
        $secret = $ga->createSecret();
        $user['twofa_secret'] = $secret;
    }

    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users
            SET first_name = ?, last_name = ?, email = ?, theme = ?, twofa_enabled = ?, twofa_secret = ?, password_hash = ?
            WHERE id = ?');
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $theme,
            $enable2fa,
            $secret,
            $password_hash,
            $user['id']
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE users
            SET first_name = ?, last_name = ?, email = ?, theme = ?, twofa_enabled = ?, twofa_secret = ?
            WHERE id = ?');
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $theme,
            $enable2fa,
            $secret,
            $user['id']
        ]);
    }

    logAction($pdo, $user['id'], 'Updated profile');

    // Refresh $user and secret
    $user   = currentUser($pdo);
    $secret = $user['twofa_secret'];

    // Redirect unless this was the very first time enabling 2FA
    if (!($enable2fa && !empty($secret))) {
        header('Location: profile.php');
        exit();
    }
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
          <input type="text" name="first_name" class="form-control"
                 value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control"
                 value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="form-group">
          <label>Password (leave blank to keep current)</label>
          <input type="password" name="password" class="form-control">
        </div>
        <div class="form-group">
          <label>Theme</label>
          <select name="theme" class="form-control">
            <option value="light" <?php echo ($user['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
            <option value="dark"  <?php echo ($user['theme'] === 'dark')  ? 'selected' : ''; ?>>Dark</option>
          </select>
        </div>
        <div class="form-group form-check">
          <input type="checkbox" name="twofa" class="form-check-input"
                 <?php echo ($user['twofa_enabled']) ? 'checked' : ''; ?>>
          <label class="form-check-label">Enable Two-Factor Authentication (2FA)</label>
        </div>

        <?php if ($user['twofa_enabled'] && !empty($secret)): ?>
          <div class="form-group">
            <label>Your 2FA Secret (scan with Google Authenticator or Authy):</label><br>
            <?php
              $otpUrl = 'otpauth://totp/' . rawurlencode($user['email']) . '?secret=' . rawurlencode($secret);
              $qrSrc  = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otpUrl);
            ?>
            <img src="<?php echo $qrSrc; ?>" alt="QR Code" style="width:200px;height:200px;"><br>
            <small>Or enter this code manually: <code><?php echo htmlspecialchars($secret); ?></code></small>
            <br>
            <small><em>otpauth URL: <?php echo htmlspecialchars($otpUrl); ?></em></small>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Update Profile</button>
      </form>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>