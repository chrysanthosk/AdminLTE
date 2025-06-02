<?php
// login.php — user login with optional 2FA

require_once 'auth.php';
require_once 'GoogleAuthenticator.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $code     = $_POST['code'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['twofa_enabled']) {
            if (empty($code)) {
                $error = 'Please enter the 2FA code.';
            } else {
                $ga = new PHPGangsta_GoogleAuthenticator();
                if ($ga->verifyCode($user['twofa_secret'], $code, 2)) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role']    = $user['role'];
                    logAction($pdo, $user['id'], 'Logged in with 2FA');
                    header('Location: pages/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid 2FA code.';
                }
            }
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            logAction($pdo, $user['id'], 'Logged in');
            header('Location: pages/dashboard.php');
            exit();
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Admin Panel</title>

  <!-- AdminLTE Light CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- AdminLTE Dark CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte-dark.min.css">
  <!-- Font Awesome (icons) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Admin</b>Panel</a>
  </div>

  <div class="card">
    <div class="card-header text-center">
      <!-- Dark/Light toggle switch -->
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="theme-switch">
        <label class="custom-control-label" for="theme-switch">Dark Mode</label>
      </div>
    </div>

    <div class="card-body login-card-body">
      <p class="login-box-msg">Sign in to start your session</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>

      <form action="" method="post">
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="text" name="code" class="form-control" placeholder="2FA Code (if enabled)">
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-key"></span></div>
          </div>
        </div>

        <div class="row">
          <div class="col-8">
            <a href="forgot_password.php">I forgot my password</a>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// On page load, apply saved theme (light/dark) from localStorage
document.addEventListener('DOMContentLoaded', () => {
  const switchEl = document.getElementById('theme-switch');

  function applyTheme(isDark) {
    if (isDark) {
      document.body.classList.add('dark-mode');
      switchEl.checked = true;
      localStorage.theme = 'dark';
    } else {
      document.body.classList.remove('dark-mode');
      switchEl.checked = false;
      localStorage.theme = 'light';
    }
  }

  // Initialize based on saved preference:
  const saved = localStorage.theme || 'light';
  applyTheme(saved === 'dark');

  // Toggle event:
  switchEl.addEventListener('change', () => {
    applyTheme(switchEl.checked);
  });
});
</script>
</body>
</html>