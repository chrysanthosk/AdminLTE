<?php
// pages/users.php - list and manage users with modal Add/Edit
require_once '../auth.php';
requirePermission($pdo, 'user.manage');

// Handle Create / Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id    = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role_id    = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $theme      = $_POST['theme'] ?? 'light';

    // Basic validation
    if (!$username || !$email || !$role_id || (!$user_id && !$password)) {
      // require password only for new users
      $error = 'Please fill in all required fields.';
    } else {
      // if updating, choose appropriate SQL
      if ($user_id) {
        if ($password) {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $pdo->prepare(
            'UPDATE users SET username=?, email=?, password_hash=?, role_id=?, first_name=?, last_name=?, theme=? WHERE id=?'
          );
          $params = [$username, $email, $hash, $role_id, $first_name, $last_name, $theme, $user_id];
        } else {
          $stmt = $pdo->prepare(
            'UPDATE users SET username=?, email=?, role_id=?, first_name=?, last_name=?, theme=? WHERE id=?'
          );
          $params = [$username, $email, $role_id, $first_name, $last_name, $theme, $user_id];
        }
        $stmt->execute($params);
        logAction($pdo, $_SESSION['user_id'], 'Updated user ID: ' . $user_id);
      } else {
        // create new user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
          'INSERT INTO users (username, email, password_hash, role_id, first_name, last_name, theme) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash, $role_id, $first_name, $last_name, $theme]);
        $newId = $pdo->lastInsertId();
        logAction($pdo, $_SESSION['user_id'], 'Created new user ID: ' . $newId);
      }
      header('Location: users.php'); exit();
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$delId]);
    logAction($pdo, $_SESSION['user_id'], 'Deleted user ID: ' . $delId);
    header('Location: users.php'); exit();
}

// Fetch roles for dropdown
$roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch users
$stmt = $pdo->query(
  "SELECT u.id, u.username, u.email, u.role_id,
          COALESCE(r.role_name,'—') AS role,
          u.first_name, u.last_name, u.theme, u.created_at
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     ORDER BY u.id ASC"
);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'User Administration';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>User Administration</h1></div>
        <div class="col-sm-6 text-right">
          <button class="btn btn-success" data-toggle="modal" data-target="#userModal" onclick="openCreateModalUser()">
            <i class="fas fa-user-plus"></i> Add User
          </button>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Theme</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($u['role'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($u['theme'], ENT_QUOTES) ?></td>
            <td>
              <button class="btn btn-sm btn-info"
                onclick="openEditModalUser(
                  <?= $u['id'] ?>,
                  '<?= addslashes($u['username']) ?>',
                  '<?= addslashes($u['email']) ?>',
                  <?= $u['role_id'] ?>,
                  '<?= addslashes($u['first_name']) ?>',
                  '<?= addslashes($u['last_name']) ?>',
                  '<?= addslashes($u['theme']) ?>'
                )">
                <i class="fas fa-edit"></i> Edit
              </button>
              <a href="users.php?delete_id=<?= $u['id'] ?>"
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete user ID <?= $u['id'] ?>?');">
                <i class="fas fa-trash"></i> Delete
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="post" id="userForm">
        <div class="modal-header">
          <h5 class="modal-title" id="userModalLabel">Add / Edit User</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="user_id" value="">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="username" id="username" required>
            </div>
            <div class="form-group col-md-6">
              <label>Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" name="email" id="email" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Password <span class="text-danger" id="passwordRequired">*</span></label>
              <input type="password" class="form-control" name="password" id="password">
            </div>
            <div class="form-group col-md-6">
              <label>Role <span class="text-danger">*</span></label>
              <select class="form-control" name="role_id" id="role_id" required>
                <option value="">— Select a role —</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>First Name</label>
              <input type="text" class="form-control" name="first_name" id="first_name">
            </div>
            <div class="form-group col-md-6">
              <label>Last Name</label>
              <input type="text" class="form-control" name="last_name" id="last_name">
            </div>
          </div>
          <div class="form-group">
            <label>Theme</label>
            <select class="form-control" name="theme" id="theme">
              <option value="light">Light</option>
              <option value="dark">Dark</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Prepare modal for creating a new user
function openCreateModalUser() {
  $('#userModalLabel').text('Add User');
  $('#user_id').val('');
  $('#username, #email, #password, #first_name, #last_name').val('');
  $('#role_id').val('');
  $('#theme').val('light');
  $('#passwordRequired').show();
  $('#userModal').modal('show');
}

// Prepare modal for editing an existing user
function openEditModalUser(id, username, email, roleId, firstName, lastName, theme) {
  $('#userModalLabel').text('Edit User');
  $('#user_id').val(id);
  $('#username').val(username);
  $('#email').val(email);
  $('#password').val('');
  $('#role_id').val(roleId);
  $('#first_name').val(firstName);
  $('#last_name').val(lastName);
  $('#theme').val(theme);
  $('#passwordRequired').hide();
  $('#userModal').modal('show');
}
</script>