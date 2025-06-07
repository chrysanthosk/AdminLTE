<?php
// add_user.php - add a new user
require_once '../auth.php';
requirePermission($pdo, 'user.manage');

$roleId = (int)($_POST['role_id'] ?? 0);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $roleId     = (int)$_POST['role_id'];
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $theme      = $_POST['theme'];

    // Basic validation
    if (!$username || !$email || !$password || !$roleId) {
      $error = 'Please fill in all required fields.';
    } else {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, role_id, first_name, last_name, theme)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
      );
      $stmt->execute([
        $username,
        $email,
        $password_hash,
        $roleId,
        $first_name,
        $last_name,
        $theme
      ]);

      logAction($pdo, $_SESSION['user_id'], 'Added new user: ' . $username);
      header('Location: users.php');
      exit();
    }
}

// Fetch roles for dropdown
$roles = $pdo
  ->query("SELECT id, role_name FROM roles ORDER BY role_name ASC")
  ->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Add User';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Add User</h1></div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label>Username<span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
          <label>Email<span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
          <label>Password<span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="form-group">
          <label>Role<span class="text-danger">*</span></label>
          <select name="role" class="form-control" required>
            <option value="">— Select a role —</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"
                <?= (isset($_POST['role_id']) && $_POST['role']==$r['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['role_name'], ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control"
                 value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
          <label>Theme</label>
          <select name="theme" class="form-control">
            <option value="light" <?= (($_POST['theme'] ?? '')==='light')?'selected':'' ?>>Light</option>
            <option value="dark"  <?= (($_POST['theme'] ?? '')==='dark')?'selected':''  ?>>Dark</option>
          </select>
        </div>

        <button type="submit" class="btn btn-success">Add User</button>
      </form>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>
