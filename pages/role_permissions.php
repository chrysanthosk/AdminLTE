<?php
// pages/role_permissions.php — manage which permissions each role has

require_once '../auth.php';
requirePermission($pdo, 'role.assign'); // only roles with “role.assign” can manage this

// Fetch all roles (for dropdown)
$stmt = $pdo->query('SELECT id, role_name FROM roles ORDER BY role_name');
$all_roles = $stmt->fetchAll();

// Fetch all permissions (for checkboxes)
$stmt = $pdo->query('SELECT id, permission_key, description FROM permissions ORDER BY permission_key');
$all_perms = $stmt->fetchAll();

// If a role was selected via GET or POST, capture it
$selected_role_id = isset($_REQUEST['role_id']) ? (int)$_REQUEST['role_id'] : null;

// Handle POST: update role_permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_role_id = (int)$_POST['role_id'];
    $checked_perms = isset($_POST['permissions']) ? array_map('intval', $_POST['permissions']) : [];

    // Delete all existing for this role
    $stmt = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
    $stmt->execute([$selected_role_id]);

    // Insert each checked permission
    if (!empty($checked_perms)) {
        $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($checked_perms as $perm_id) {
            $insert->execute([$selected_role_id, $perm_id]);
        }
    }

    logAction($pdo, $_SESSION['user_id'], "Updated permissions for role ID $selected_role_id");
    header("Location: role_permissions.php?role_id=$selected_role_id");
    exit();
}

// If a role is selected, fetch its currently assigned permissions
$assigned_perm_ids = [];
if ($selected_role_id) {
    $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
    $stmt->execute([$selected_role_id]);
    $assigned_perm_ids = array_column($stmt->fetchAll(), 'permission_id');
}

$page_title = 'Role Permissions';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Role Permissions</h1>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- Select Role Dropdown -->
      <form method="get" id="selectRoleForm">
        <div class="form-group">
          <label>Select Role:</label>
          <select name="role_id" class="form-control" onchange="document.getElementById('selectRoleForm').submit()">
            <option value="">-- Choose a Role --</option>
            <?php foreach ($all_roles as $r): ?>
              <option value="<?php echo $r['id']; ?>"
                <?php echo ($selected_role_id == $r['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($r['role_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <?php if ($selected_role_id): ?>
        <!-- Permissions Form -->
        <form method="post">
          <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Assign Permissions to "<?php
                // Find the selected role’s name
                foreach ($all_roles as $r) {
                  if ($r['id'] === $selected_role_id) {
                    echo htmlspecialchars($r['role_name']);
                    break;
                  }
                }
              ?>"</h3>
            </div>
            <div class="card-body">
              <?php foreach ($all_perms as $p): ?>
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         name="permissions[]"
                         value="<?php echo $p['id']; ?>"
                         id="perm_<?php echo $p['id']; ?>"
                         <?php echo in_array($p['id'], $assigned_perm_ids) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="perm_<?php echo $p['id']; ?>">
                    <strong><?php echo htmlspecialchars($p['permission_key']); ?></strong>
                    &mdash; <?php echo htmlspecialchars($p['description']); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="card-footer">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Permissions
              </button>
            </div>
          </div>
        </form>
      <?php else: ?>
        <p>Please select a role above to assign permissions.</p>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>