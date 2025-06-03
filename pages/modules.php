<?php
// pages/modules.php — manage the modules table (CRUD, with Active/Inactive in modal)

require_once '../auth.php';
requirePermission($pdo, 'module.manage');

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    $mod_id         = isset($_POST['mod_id'])       ? (int)$_POST['mod_id']       : null;
    $title          = trim($_POST['title']);
    $description    = trim($_POST['description']);
    $icon_class     = trim($_POST['icon_class']);
    $box_color      = trim($_POST['box_color']);
    $link           = trim($_POST['link']);
    $permission_key = trim($_POST['permission_key']);
    $sort_order     = (int)$_POST['sort_order'];
    $is_active      = isset($_POST['is_active'])   ? 1 : 0;

    if ($mod_id) {
        // Update existing module
        $stmt = $pdo->prepare('
            UPDATE modules
               SET title = ?, description = ?, icon_class = ?, box_color = ?,
                   link = ?, permission_key = ?, sort_order = ?, is_active = ?
             WHERE id = ?
        ');
        $stmt->execute([
            $title, $description, $icon_class, $box_color,
            $link, $permission_key, $sort_order, $is_active,
            $mod_id
        ]);
        logAction($pdo, $_SESSION['user_id'], "Updated module ID $mod_id");
    } else {
        // Insert new module
        $stmt = $pdo->prepare('
            INSERT INTO modules
                (title, description, icon_class, box_color, link, permission_key, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $title, $description, $icon_class, $box_color,
            $link, $permission_key, $sort_order, $is_active
        ]);
        $newId = $pdo->lastInsertId();
        logAction($pdo, $_SESSION['user_id'], "Created new module ID $newId");
    }

    header('Location: modules.php');
    exit();
}

// Handle Delete (via GET)
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM modules WHERE id = ?');
    $stmt->execute([$del_id]);
    logAction($pdo, $_SESSION['user_id'], "Deleted module ID $del_id");
    header('Location: modules.php');
    exit();
}

// Fetch all modules
$stmt = $pdo->query('SELECT * FROM modules ORDER BY sort_order, id');
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Modules';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Modules</h1></div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <!-- Button: Add New Module -->
      <div class="mb-3">
        <button class="btn btn-success" data-toggle="modal" data-target="#modModal" onclick="openCreateModal()">
          <i class="fas fa-plus"></i> Add Module
        </button>
      </div>

      <!-- Modules Table -->
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Description</th>
            <th>Icon</th>
            <th>Box Color</th>
            <th>Link</th>
            <th>Permission Key</th>
            <th>Sort Order</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_modules as $m): ?>
            <tr>
              <td><?php echo $m['id']; ?></td>
              <td><?php echo htmlspecialchars($m['title']); ?></td>
              <td><?php echo htmlspecialchars($m['description']); ?></td>
              <td><i class="<?php echo htmlspecialchars($m['icon_class']); ?>"></i></td>
              <td><?php echo htmlspecialchars($m['box_color']); ?></td>
              <td><?php echo htmlspecialchars($m['link']); ?></td>
              <td><?php echo htmlspecialchars($m['permission_key']); ?></td>
              <td><?php echo $m['sort_order']; ?></td>
              <td>
                <?php echo $m['is_active'] ? '<span class="badge badge-success">Active</span>'
                                           : '<span class="badge badge-secondary">Inactive</span>'; ?>
              </td>
              <td>
                <button
                  class="btn btn-sm btn-info"
                  onclick="openEditModal(
                    <?php echo $m['id']; ?>,
                    '<?php echo addslashes($m['title']); ?>',
                    '<?php echo addslashes($m['description']); ?>',
                    '<?php echo addslashes($m['icon_class']); ?>',
                    '<?php echo addslashes($m['box_color']); ?>',
                    '<?php echo addslashes($m['link']); ?>',
                    '<?php echo addslashes($m['permission_key']); ?>',
                    <?php echo $m['sort_order']; ?>,
                    <?php echo $m['is_active']; ?>
                  )">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <a
                  href="modules.php?delete_id=<?php echo $m['id']; ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Delete this module?');">
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

<!-- Add / Edit Module Modal -->
<div class="modal fade" id="modModal" tabindex="-1" aria-labelledby="modModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="modForm">
        <div class="modal-header">
          <h5 class="modal-title" id="modModalLabel">Add / Edit Module</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="mod_id" id="mod_id" value="">

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Title</label>
              <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Sort Order</label>
              <input type="number" name="sort_order" id="sort_order" class="form-control" value="0" required>
              <small class="form-text text-muted">Lower numbers appear first.</small>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" id="description" class="form-control"></textarea>
          </div>

          <div class="form-row">
            <!-- Icon Class dropdown -->
            <div class="form-group col-md-4">
              <label>Icon</label>
              <select name="icon_class" id="icon_class" class="form-control" required>
                <option value="fas fa-users">Users (fas fa-users)</option>
                <option value="fas fa-user-tag">Roles (fas fa-user-tag)</option>
                <option value="fas fa-key">Role Perms (fas fa-key)</option>
                <option value="fas fa-lock">Permissions (fas fa-lock)</option>
                <option value="fas fa-envelope">Email (fas fa-envelope)</option>
                <option value="fas fa-file-alt">Logs (fas fa-file-alt)</option>
                <option value="fas fa-tachometer-alt">Dashboard (fas fa-tachometer-alt)</option>
                <option value="fas fa-chart-bar">Reports (fas fa-chart-bar)</option>
                <!-- Add more Font Awesome classes as needed -->
              </select>
              <small class="form-text text-muted">Pick a Font Awesome icon.</small>
            </div>

            <!-- Box Color dropdown -->
            <div class="form-group col-md-4">
              <label>Box Color</label>
              <select name="box_color" id="box_color" class="form-control" required>
                <option value="bg-info">bg-info (blue)</option>
                <option value="bg-primary">bg-primary (dark blue)</option>
                <option value="bg-secondary">bg-secondary (gray)</option>
                <option value="bg-success">bg-success (green)</option>
                <option value="bg-warning">bg-warning (yellow)</option>
                <option value="bg-danger">bg-danger (red)</option>
                <option value="bg-dark">bg-dark (black)</option>
                <option value="bg-light">bg-light (light gray)</option>
              </select>
              <small class="form-text text-muted">Choose an AdminLTE color class.</small>
            </div>

            <div class="form-group col-md-4">
              <label>Link</label>
              <input type="text" name="link" id="link" class="form-control" placeholder="e.g. users.php" required>
            </div>
          </div>

          <div class="form-group">
            <label>Permission Key</label>
            <input type="text" name="permission_key" id="permission_key" class="form-control" placeholder="e.g. user.manage" required>
            <small class="form-text text-muted">Must match a key in the <code>permissions</code> table.</small>
          </div>

          <div class="form-group form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active">
            <label class="form-check-label" for="is_active">Active</label>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Module</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Open modal in “create new” mode
function openCreateModal() {
  document.getElementById('modModalLabel').innerText = 'Add Module';
  document.getElementById('mod_id').value = '';
  document.getElementById('title').value = '';
  document.getElementById('description').value = '';
  document.getElementById('icon_class').value = 'fas fa-users';
  document.getElementById('box_color').value = 'bg-info';
  document.getElementById('link').value = '';
  document.getElementById('permission_key').value = '';
  document.getElementById('sort_order').value = 0;
  document.getElementById('is_active').checked = true;
  $('#modModal').modal('show');
}

// Open modal in “edit” mode
function openEditModal(id, title, desc, icon, color, link, permKey, sortOrder, isActive) {
  document.getElementById('modModalLabel').innerText = 'Edit Module';
  document.getElementById('mod_id').value = id;
  document.getElementById('title').value = title;
  document.getElementById('description').value = desc;
  document.getElementById('icon_class').value = icon;
  document.getElementById('box_color').value = color;
  document.getElementById('link').value = link;
  document.getElementById('permission_key').value = permKey;
  document.getElementById('sort_order').value = sortOrder;
  document.getElementById('is_active').checked = isActive ? true : false;
  $('#modModal').modal('show');
}
</script>