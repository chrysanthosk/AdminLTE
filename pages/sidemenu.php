<?php
// pages/sidemenu.php — Sidebar Menu & Section Management
require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'module.manage');
require_once __DIR__ . '/../includes/reusables.php';  // <— load $iconOptions

// ─── Handle Module Assign/Create ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action     = $_POST['action'];
  $module_id  = (int)($_POST['module_id']  ?? 0);
  $section_id = (int)($_POST['section_id'] ?? 0);
  $sort_order = (int)($_POST['sort_order'] ?? 0);
  $is_active  = isset($_POST['is_active']) ? 1 : 0;

  if ($module_id && $section_id) {
    $stmt = $pdo->prepare(
      'UPDATE modules
         SET section_id = ?, sort_order = ?, is_active = ?
       WHERE id = ?'
    );
    $stmt->execute([$section_id, $sort_order, $is_active, $module_id]);
    logAction($pdo, $_SESSION['user_id'], ucfirst($action) . " module #$module_id");
  }
  header('Location: sidemenu.php');
  exit();
}

// ─── Handle Section Create/Edit ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section_action'])) {
  $sa          = $_POST['section_action'];
  $sec_id      = (int)($_POST['sec_id'] ?? 0);
  $section_key = trim($_POST['section_key'] ?? '');
  $sec_label   = trim($_POST['section_label'] ?? '');
  $icon_class  = trim($_POST['section_icon'] ?? '');
  $sec_order   = (int)($_POST['section_sort'] ?? 0);
  $sec_active  = isset($_POST['section_active']) ? 1 : 0;

  if ($section_key && $sec_label) {
    if ($sa === 'edit' && $sec_id) {
      $u = $pdo->prepare(
        'UPDATE menu_sections
           SET section_key = ?, label = ?, icon_class = ?, sort_order = ?, is_active = ?
         WHERE id = ?'
      );
      $u->execute([$section_key,$sec_label,$icon_class,$sec_order,$sec_active,$sec_id]);
      logAction($pdo, $_SESSION['user_id'], "Updated section $sec_label");
    } else {
      $i = $pdo->prepare(
        'INSERT INTO menu_sections
          (section_key,label,icon_class,sort_order,is_active)
         VALUES (?,?,?,?,?)'
      );
      $i->execute([$section_key,$sec_label,$icon_class,$sec_order,$sec_active]);
      logAction($pdo, $_SESSION['user_id'], "Created section $sec_label");
    }
  }
  header('Location: sidemenu.php');
  exit();
}

// ─── Load Data ────────────────────────────────────────────
// Sections for dropdown & table
$dbSections = $pdo
  ->query('SELECT id, section_key, label, icon_class, sort_order, is_active FROM menu_sections ORDER BY sort_order')
  ->fetchAll(PDO::FETCH_ASSOC);

// Assigned modules for table
$menuItems = $pdo
  ->query('
    SELECT m.id, m.title, m.link, m.sort_order, m.is_active,
           s.id AS section_id, s.label AS section_label
    FROM modules m
    JOIN menu_sections s ON m.section_id = s.id
    WHERE m.is_active = 1
    ORDER BY s.sort_order, m.sort_order
  ')
  ->fetchAll(PDO::FETCH_ASSOC);

// Unassigned (“Other”) modules
$freeModules = [];
$otherId = (int)$pdo
  ->query("SELECT id FROM menu_sections WHERE section_key='Other' LIMIT 1")
  ->fetchColumn();
if ($otherId) {
  $stmt = $pdo->prepare(
    'SELECT id,title FROM modules WHERE section_id=? AND is_active=1 ORDER BY title'
  );
  $stmt->execute([$otherId]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($r['id'], $r['title'])) {
      $freeModules[] = $r;
    }
  }
}

$page_title = 'Sidebar Menu & Sections';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <h1><?= htmlspecialchars($page_title,ENT_QUOTES) ?></h1>
      <div>
        <button id="addMenuBtn"   class="btn btn-success"><i class="fas fa-plus"></i> Add Menu Item</button>
        <button id="addSectionBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add Section</button>
      </div>
    </div>
  </section>

  <!-- Sections Table -->
  <section class="content">
    <div class="container-fluid mb-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Menu Sections</h3></div>
        <div class="card-body p-0">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>ID</th><th>Key</th><th>Label</th><th>Icon</th><th>Order</th><th>Active</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dbSections as $sec): ?>
              <tr>
                <td><?= (int)$sec['id'] ?></td>
                <td><?= htmlspecialchars($sec['section_key'],ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($sec['label'],ENT_QUOTES) ?></td>
                <td><i class="<?= htmlspecialchars($sec['icon_class'],ENT_QUOTES) ?>"></i></td>
                <td><?= (int)$sec['sort_order'] ?></td>
                <td><?= $sec['is_active']?'Yes':'No' ?></td>
                <td>
                  <button class="btn btn-sm btn-info edit-section-btn"
                    data-id="<?= (int)$sec['id'] ?>"
                    data-key="<?= htmlspecialchars($sec['section_key'],ENT_QUOTES) ?>"
                    data-label="<?= htmlspecialchars($sec['label'],ENT_QUOTES) ?>"
                    data-icon="<?= htmlspecialchars($sec['icon_class'],ENT_QUOTES) ?>"
                    data-sort="<?= (int)$sec['sort_order'] ?>"
                    data-active="<?= (int)$sec['is_active'] ?>"
                  ><i class="fas fa-edit"></i></button>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="section_action" value="delete">
                    <input type="hidden" name="sec_id" value="<?= (int)$sec['id'] ?>">
                    <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this section?')"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modules Table -->
    <div class="container-fluid">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Assigned Menu Items</h3></div>
        <div class="card-body p-0">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>ID</th><th>Title</th><th>Section</th><th>Link</th>
                <th>Order</th><th>Active</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($menuItems as $it): ?>
              <tr>
                <td><?= (int)$it['id'] ?></td>
                <td><?= htmlspecialchars($it['title'],ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($it['section_label'],ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($it['link'],ENT_QUOTES) ?></td>
                <td><?= (int)$it['sort_order'] ?></td>
                <td><?= $it['is_active']?'Yes':'No' ?></td>
                <td>
                  <button
                    class="btn btn-sm btn-info edit-menu-btn"
                    data-id="<?= (int)$it['id'] ?>"
                    data-title="<?= htmlspecialchars($it['title'],ENT_QUOTES) ?>"
                    data-sect="<?= (int)$it['section_id'] ?>"
                    data-sort="<?= (int)$it['sort_order'] ?>"
                    data-active="<?= (int)$it['is_active'] ?>"
                  ><i class="fas fa-edit"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal: Assign/Edit Menu Item -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" id="modal_action"   value="add">
      <input type="hidden" name="module_id" id="modal_mid"   value="">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Menu Item</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Module *</label>
          <select id="modal_module_select" name="module_id" class="form-control" required>
            <option value="">— Choose Module —</option>
            <?php foreach ($freeModules as $fm): ?>
              <option value="<?= (int)$fm['id'] ?>"><?= htmlspecialchars($fm['title'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section *</label>
          <select name="section_id" id="modal_section_id" class="form-control" required>
            <option value="">— Choose Section —</option>
            <?php foreach ($dbSections as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['label'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="sort_order" id="modal_sort_order" class="form-control" value="0">
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_active" id="modal_is_active" class="form-check-input" checked>
          <label class="form-check-label" for="modal_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal: Add/Edit Section -->
<div class="modal fade" id="sectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="section_action"   id="section_action" value="add">
      <input type="hidden" name="sec_id"            id="section_id"     value="">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Section</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Key *</label>
          <input type="text" name="section_key" id="section_key" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Label *</label>
          <input type="text" name="section_label" id="section_label" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Icon Class</label>
          <select name="section_icon" id="section_icon" class="form-control">
            <?php foreach ($iconOptions as $cls=>$txt): ?>
              <option value="<?= htmlspecialchars($cls,ENT_QUOTES) ?>"><?= htmlspecialchars($txt,ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="section_sort" id="section_sort" class="form-control" value="0">
        </div>
        <div class="form-check">
          <input type="checkbox" name="section_active" id="section_active" class="form-check-input" checked>
          <label class="form-check-label" for="section_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Section</button>
      </div>
    </form>
  </div></div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(function(){
  // Add Module
  $('#addMenuBtn').click(function(){
    $('#modal_action').val('add');
    $('#modal_mid, #modal_section_id').val('');
    $('#modal_module_select').val('').prop('disabled',false);
    $('#modal_sort_order').val(0);
    $('#modal_is_active').prop('checked',true);
    $('#assignModal').modal('show');
  });

  // Edit Module
  $('.edit-menu-btn').click(function(){
    var b = $(this);
    $('#modal_action').val('edit');
    $('#modal_mid').val(b.data('id'));
    var $ms = $('#modal_module_select');
    if (!$ms.find('option[value="'+b.data('id')+'"]').length) {
      $ms.append($('<option>').val(b.data('id')).text(b.data('title')));
    }
    $ms.val(b.data('id')).prop('disabled',true);
    $('#modal_section_id').val(b.data('sect'));
    $('#modal_sort_order').val(b.data('sort'));
    $('#modal_is_active').prop('checked',b.data('active')==1);
    $('#assignModal').modal('show');
  });

  // Add Section
  $('#addSectionBtn').click(function(){
    $('#section_action').val('add');
    $('#section_id, #section_key, #section_label, #section_sort').val('');
    $('#section_icon').val('fas fa-users');
    $('#section_active').prop('checked',true);
    $('#sectionModal').modal('show');
  });

  // Edit Section
  $('.edit-section-btn').click(function(){
    var b = $(this);
    $('#section_action').val('edit');
    $('#section_id').val(b.data('id'));
    $('#section_key').val(b.data('key'));
    $('#section_label').val(b.data('label'));
    $('#section_icon').val(b.data('icon'));
    $('#section_sort').val(b.data('sort'));
    $('#section_active').prop('checked',b.data('active')==1);
    $('#sectionModal').modal('show');
  });
});
</script>