<?php
// pages/permissions.php — manage the permissions table (CRUD)

require_once '../auth.php';
requirePermission($pdo, 'permission.manage');

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $perm_id      = isset($_POST['perm_id']) ? (int)$_POST['perm_id'] : null;
    $perm_key     = trim($_POST['permission_key']);
    $perm_desc    = trim($_POST['description']);

    if ($perm_id) {
        // Update existing permission
        $stmt = $pdo->prepare('UPDATE permissions SET permission_key = ?, description = ? WHERE id = ?');
        $stmt->execute([$perm_key, $perm_desc, $perm_id]);
        logAction($pdo, $_SESSION['user_id'], "Updated permission ID $perm_id");
    } else {
        // Insert new permission
        $stmt = $pdo->prepare('INSERT INTO permissions (permission_key, description) VALUES (?, ?)');
        $stmt->execute([$perm_key, $perm_desc]);
        logAction($pdo, $_SESSION['user_id'], "Created new permission $perm_key");
    }

    header('Location: permissions.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM permissions WHERE id = ?');
    $stmt->execute([$del_id]);
    logAction($pdo, $_SESSION['user_id'], "Deleted permission ID $del_id");
    header('Location: permissions.php');
    exit();
}

// Fetch all permissions
$stmt = $pdo->query('SELECT * FROM permissions ORDER BY permission_key');
$all_perms = $stmt->fetchAll();
$page_title = 'Permissions';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Permissions</h1></div>
        <div class="col-sm-6 text-right">
                <button class="btn btn-success" data-toggle="modal" data-target="#permModal" onclick="openCreateModal()">
                  <i class="fas fa-plus"></i> Add Permission
                </button>
              </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <!-- Permissions Table -->
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Permission Key</th>
            <th>Description</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_perms as $p): ?>
            <tr>
              <td><?php echo $p['id']; ?></td>
              <td><?php echo htmlspecialchars($p['permission_key']); ?></td>
              <td><?php echo htmlspecialchars($p['description']); ?></td>
              <td><?php echo $p['created_at']; ?></td>
              <td>
                <button
                  class="btn btn-sm btn-info"
                  onclick="openEditModal(<?php echo $p['id']; ?>,
                                         '<?php echo addslashes($p['permission_key']); ?>',
                                         '<?php echo addslashes($p['description']); ?>')">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <a
                  href="permissions.php?delete_id=<?php echo $p['id']; ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Delete this permission?');">
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

<!-- Add / Edit Permission Modal -->
<div class="modal fade" id="permModal" tabindex="-1" aria-labelledby="permModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="permForm">
        <div class="modal-header">
          <h5 class="modal-title" id="permModalLabel">Add / Edit Permission</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="perm_id" id="perm_id" value="">
          <div class="form-group">
            <label>Permission Key</label>
            <input type="text" name="permission_key" id="permission_key" class="form-control" required>
            <small class="form-text text-muted">
              Must be unique (e.g. <code>user.manage</code>, <code>role.assign</code>, etc.).
            </small>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" id="description" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Permission</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Open modal in "create new" mode
function openCreateModal() {
  document.getElementById('permModalLabel').innerText = 'Add Permission';
  document.getElementById('perm_id').value = '';
  document.getElementById('permission_key').value = '';
  document.getElementById('description').value = '';
  $('#permModal').modal('show');
}

// Open modal in "edit" mode
function openEditModal(id, key, desc) {
  document.getElementById('permModalLabel').innerText = 'Edit Permission';
  document.getElementById('perm_id').value = id;
  document.getElementById('permission_key').value = key;
  document.getElementById('description').value = desc;
  $('#permModal').modal('show');
}
</script>