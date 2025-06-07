<?php
// pages/roles.php — Role Management (admin only)

require_once '../auth.php';
requirePermission($pdo, 'role.manage');

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = trim($_POST['role_name']);
    $role_desc = trim($_POST['role_desc']);
    $role_id   = $_POST['role_id'] ?? null;

    if ($role_id) {
        // Update existing
        $stmt = $pdo->prepare('UPDATE roles SET role_name = ?, role_desc = ? WHERE id = ?');
        $stmt->execute([$role_name, $role_desc, $role_id]);
        logAction($pdo, $_SESSION['user_id'], "Updated role ID $role_id");
    } else {
        // Create new
        $stmt = $pdo->prepare('INSERT INTO roles (role_name, role_desc) VALUES (?, ?)');
        $stmt->execute([$role_name, $role_desc]);
        logAction($pdo, $_SESSION['user_id'], "Created new role $role_name");
    }
    header('Location: roles.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM roles WHERE id = ?');
    $stmt->execute([$del_id]);
    logAction($pdo, $_SESSION['user_id'], "Deleted role ID $del_id");
    header('Location: roles.php');
    exit();
}

// Fetch all roles
$stmt = $pdo->query('SELECT * FROM roles ORDER BY created_at DESC');
$roles = $stmt->fetchAll();
$page_title = 'Role Management';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Role Management</h1></div>
        <div class="col-sm-6 text-right">
              <button class="btn btn-success" data-toggle="modal" data-target="#roleModal" onclick="openCreateModal()">
                  <i class="fas fa-user-plus"></i> Add New Role
               </button>
           </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">



      <!-- Roles Table -->
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Role Name</th>
            <th>Description</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $r): ?>
            <tr>
              <td><?php echo $r['id']; ?></td>
              <td><?php echo htmlspecialchars($r['role_name']); ?></td>
              <td><?php echo htmlspecialchars($r['role_desc']); ?></td>
              <td><?php echo $r['created_at']; ?></td>
              <td>
                <button
                  class="btn btn-sm btn-info"
                  onclick="openEditModal(<?php echo $r['id']; ?>, '<?php echo addslashes($r['role_name']); ?>', '<?php echo addslashes($r['role_desc']); ?>')">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <a
                  href="roles.php?delete_id=<?php echo $r['id']; ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Delete this role?');">
                  <i class="fas fa-trash"></i> Delete
                </a>
                  <?php if (hasPermission($pdo, 'role.assign')): ?>
                    <a href="role_permissions.php?role_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-key"></i> Permissions
                    </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </div>
  </section>
</div>

<!-- Add / Edit Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="roleForm">
        <div class="modal-header">
          <h5 class="modal-title" id="roleModalLabel">Add / Edit Role</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="role_id" id="role_id" value="">
          <div class="form-group">
            <label>Role Name</label>
            <input type="text" name="role_name" id="role_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Role Description</label>
            <textarea name="role_desc" id="role_desc" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
  function openCreateModal() {
    document.getElementById('roleModalLabel').innerText = 'Add New Role';
    document.getElementById('role_id').value = '';
    document.getElementById('role_name').value = '';
    document.getElementById('role_desc').value = '';
    $('#roleModal').modal('show');
  }

  function openEditModal(id, name, desc) {
    document.getElementById('roleModalLabel').innerText = 'Edit Role';
    document.getElementById('role_id').value = id;
    document.getElementById('role_name').value = name;
    document.getElementById('role_desc').value = desc;
    $('#roleModal').modal('show');
  }
</script>