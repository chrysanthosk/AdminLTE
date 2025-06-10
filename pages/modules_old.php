<?php
// pages/modules.php — manage the modules table (CRUD)

require_once '../auth.php';
requirePermission($pdo, 'module.manage');

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    $mod_id         = isset($_POST['mod_id'])       ? (int)$_POST['mod_id']       : null;
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $icon_class     = trim($_POST['icon_class'] ?? '');
    $box_color      = trim($_POST['box_color'] ?? '');
    $link           = trim($_POST['link'] ?? '');
    $permission_key = trim($_POST['permission_key'] ?? '');
    $sort_order     = (int)($_POST['sort_order'] ?? 0);
    $section_id     = (int)($_POST['section_id'] ?? 0);
    $is_active      = isset($_POST['is_active'])   ? 1 : 0;

    if ($title !== '' && $link !== '' && $permission_key !== '' && $section_id > 0) {
        if ($mod_id > 0) {
            $stmt = $pdo->prepare('
                UPDATE modules
                   SET title = ?, description = ?, icon_class = ?, box_color = ?,
                       link = ?, permission_key = ?, sort_order = ?, section_id = ?, is_active = ?
                 WHERE id = ?
            ');
            $stmt->execute([
                $title, $description, $icon_class, $box_color,
                $link, $permission_key, $sort_order, $section_id, $is_active,
                $mod_id
            ]);
            logAction($pdo, $_SESSION['user_id'], "Updated module ID $mod_id");
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO modules
                    (title, description, icon_class, box_color, link,
                     permission_key, sort_order, section_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $title, $description, $icon_class, $box_color,
                $link, $permission_key, $sort_order, $section_id, $is_active
            ]);
            $newId = $pdo->lastInsertId();
            logAction($pdo, $_SESSION['user_id'], "Created new module ID $newId");
        }
    }
    header('Location: modules.php');
    exit();
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM modules WHERE id = ?');
    $stmt->execute([$del_id]);
    logAction($pdo, $_SESSION['user_id'], "Deleted module ID $del_id");
    header('Location: modules.php');
    exit();
}

// Fetch all modules with their section label
$stmt = $pdo->query('
    SELECT m.*, s.label AS section_label
      FROM modules m
      JOIN menu_sections s ON m.section_id = s.id
  ORDER BY m.sort_order, m.id
');
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch sections for dropdown
$sections = $pdo
  ->query('SELECT id, label FROM menu_sections WHERE is_active = 1 ORDER BY sort_order')
  ->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Modules';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1><?= htmlspecialchars($page_title, ENT_QUOTES) ?></h1></div>
        <div class="col-sm-6 text-right">
          <button class="btn btn-success" data-toggle="modal" data-target="#modModal" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Add Module
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <!-- Modules Table -->
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Title</th><th>Description</th><th>Icon</th>
            <th>Box Color</th><th>Link</th><th>Permission Key</th>
            <th>Sort Order</th><th>Section</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_modules as $m): ?>
          <tr>
            <td><?= htmlspecialchars((int) ($m['id'] ?? 0) ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['title'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['description'] ?? '', ENT_QUOTES) ?></td>
            <td>
              <i class="<?= htmlspecialchars($m['icon_class'] ?? '', ENT_QUOTES) ?>"></i>
              <?= htmlspecialchars($m['icon_class'] ?? '', ENT_QUOTES) ?>
            </td>
            <td><?= htmlspecialchars($m['box_color'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['link'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['permission_key'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['sort_order'] ?? '0', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['section_label'] ?? '', ENT_QUOTES) ?></td>
            <td>
              <?php echo !empty($m['is_active'])
                        ? '<span class="badge badge-success">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>'; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-info"
                onclick="openEditModal(
                  <?= (int)($m['id'] ?? 0) ?>,
                  <?= json_encode($m['title'] ?? '') ?>,
                  <?= json_encode($m['description'] ?? '') ?>,
                  <?= json_encode($m['icon_class'] ?? '') ?>,
                  <?= json_encode($m['box_color'] ?? '') ?>,
                  <?= json_encode($m['link'] ?? '') ?>,
                  <?= json_encode($m['permission_key'] ?? '') ?>,
                  <?= (int)($m['sort_order'] ?? 0) ?>,
                  <?= (int)($m['section_id'] ?? 0) ?>,
                  <?= !empty($m['is_active']) ? 'true' : 'false' ?>
                )">
                <i class="fas fa-edit"></i> Edit
              </button>
              <form method="post" style="display:inline-block">
                <input type="hidden" name="delete_id" value="<?= (int)($m['id'] ?? 0) ?>">
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this module?');">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </form>
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
        <input type="hidden" name="mod_id" id="mod_id" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="modModalLabel">Add / Edit Module</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Title</label>
              <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label>Sort Order</label>
              <input type="number" name="sort_order" id="sort_order" class="form-control" value="0" required>
            </div>
            <div class="form-group col-md-4">
              <label>Section</label>
              <select name="section_id" id="section_id" class="form-control" required>
                <option value="">— Choose Section —</option>
                <?php foreach ($sections as $sec): ?>
                  <option value="<?= (int)($sec['id'] ?? 0) ?>">"><?= htmlspecialchars($sec['label'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
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
                <option value="fas fa-users">Users</option>
                <option value="fas fa-cubes">Cubes</option>
                <option value="fas fa-user-md">User MD</option>
                <option value="fas fa-user-tag">Roles</option>
                <option value="fas fa-key">Role Perms</option>
                <option value="fas fa-lock">Permissions</option>
                <option value="fas fa-envelope">Email</option>
                <option value="fas fa-file-alt">Logs</option>
                <option value="fas fa-tachometer-alt">Dashboard</option>
                <option value="fas fa-chart-bar">Reports</option>
                <option value="fas fa-calendar">Calendar</option>
                <option value="fas fa-sms">SMS</option>
                <option value="fas fa-sliders-h">Sliders</option>
                <option value="fas fa-cash-register">Cash Register</option>
                <option value="fas fa-calendar-check">Calendar Check</option>
                <option value="fas fa-calendar-alt">Calendar Alt</option>
                <option value="fas fa-percent">Percent</option>
                <option value="fas fa-tags">Tags</option>
                <option value="fas fa-list">List</option>
                <option value="fas fa-box-open">BoxOpen</option>
                <option value="fas fa-concierge-bell">Bell</option>
                <option value="fas fa-hands-helping">Help</option>
                <option value="fas fa-list-alt">List Alt</option>
              </select>
            </div>

            <!-- Box Color dropdown -->
            <div class="form-group col-md-4">
              <label>Box Color</label>
              <select name="box_color" id="box_color" class="form-control" required>
                <option value="bg-info">bg-info</option>
                <option value="bg-primary">bg-primary</option>
                <option value="bg-secondary">bg-secondary</option>
                <option value="bg-success">bg-success</option>
                <option value="bg-warning">bg-warning</option>
                <option value="bg-danger">bg-danger</option>
                <option value="bg-dark">bg-dark</option>
                <option value="bg-light">bg-light</option>
              </select>
            </div>

            <div class="form-group col-md-4">
              <label>Link</label>
              <input type="text" name="link" id="link" class="form-control" placeholder="e.g. users.php" required>
            </div>
          </div>

          <div class="form-group">
            <label>Permission Key</label>
            <input type="text" name="permission_key" id="permission_key" class="form-control" placeholder="e.g. user.manage" required>
          </div>

          <div class="form-group form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active">
            <label class="form-check-label" for="is_active">Active</label>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="save_module" class="btn btn-primary">Save Module</button>
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
  document.getElementById('section_id').value = '';
  $('#modModal').modal('show');
}

// Open modal in “edit” mode
function openEditModal(id, title, desc, icon, color, link, permKey, sortOrder, isActive, sectionId) {
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
  document.getElementById('section_id').value = sectionId;
  $('#modModal').modal('show');
}
</script>