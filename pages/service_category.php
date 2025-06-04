<?php
// pages/service_categories.php — Manage Service Categories (requires permission: service_category.manage)

require_once '../auth.php';
requirePermission($pdo, 'service_category.manage');

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// 1) Handle form submission: Add new service category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service_category'])) {
    $name  = trim($_POST['name']);
    $color = trim($_POST['color']);

    if ($name === '' || $color === '') {
        $errorMsg = 'Please fill in both fields.';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $errorMsg = 'Color must be a valid HEX code (e.g. #ff0000).';
    } else {
        $stmtCheck = $pdo->prepare('SELECT id FROM service_categories WHERE name = ? LIMIT 1');
        $stmtCheck->execute([$name]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'A service category with this name already exists.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO service_categories (name, color)
                VALUES (?, ?)
            ');
            $stmt->execute([$name, $color]);
            logAction($pdo, $_SESSION['user_id'], "Added service category: {$name} ({$color})");
            $successMsg = 'Service category added successfully.';
        }
    }
}

// 2) Handle form submission: Edit existing service category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service_category'])) {
    $id    = (int) $_POST['edit_id'];
    $name  = trim($_POST['edit_name']);
    $color = trim($_POST['edit_color']);

    if ($name === '' || $color === '') {
        $errorMsg = 'Please fill in both fields when editing.';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $errorMsg = 'Color must be a valid HEX code (e.g. #00ff00).';
    } else {
        $stmtCheck = $pdo->prepare('SELECT id FROM service_categories WHERE name = ? AND id <> ? LIMIT 1');
        $stmtCheck->execute([$name, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'Another service category with this name already exists.';
        } else {
            $stmt = $pdo->prepare('
                UPDATE service_categories
                SET name = ?, color = ?
                WHERE id = ?
            ');
            $stmt->execute([$name, $color, $id]);
            logAction($pdo, $_SESSION['user_id'], "Edited service category ID {$id}: {$name} ({$color})");
            $successMsg = 'Service category updated successfully.';
        }
    }
}

// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name, color FROM service_categories WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($catToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM service_categories WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted service category: {$catToDelete['name']} ({$catToDelete['color']}) (ID: {$delId})");
        $successMsg = 'Service category deleted successfully.';
    } else {
        $errorMsg = 'Service category not found.';
    }
}

// Fetch all service categories for DataTable
$stmt = $pdo->query('SELECT id, name, color, created_at FROM service_categories ORDER BY created_at DESC');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Service Categories';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Service Categories</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Add Service Category button -->
          <button class="btn btn-success" data-toggle="modal" data-target="#addCategoryModal">
            <i class="fas fa-plus-circle"></i> Add Service Category
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <!-- Messages -->
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <!-- Service Categories Table -->
      <div class="card">
        <div class="card-body">
          <table id="serviceCatTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Color</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $c): ?>
                <tr>
                  <td><?php echo htmlspecialchars($c['id']); ?></td>
                  <td><?php echo htmlspecialchars($c['name']); ?></td>
                  <td>
                    <span style="display:inline-block;width:20px;height:20px;background-color:<?php echo htmlspecialchars($c['color']); ?>;border:1px solid #ccc;"></span>
                    <?php echo htmlspecialchars($c['color']); ?>
                  </td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($c['created_at']))); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $c['id']; ?>"
                      data-name="<?php echo htmlspecialchars($c['name']); ?>"
                      data-color="<?php echo htmlspecialchars($c['color']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $c['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this service category?');">
                      <i class="fas fa-trash"></i>
                    </a>
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

<!-- Add Service Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="addCategoryForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addCategoryModalLabel">Add New Service Category</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Name -->
          <div class="form-group">
            <label for="name">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" placeholder="Category name" required>
          </div>
          <!-- Color -->
          <div class="form-group">
            <label for="color">Color <span class="text-danger">*</span></label>
            <input type="color" name="color" id="color" class="form-control" required>
            <small class="form-text text-muted">Select a HEX color.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_service_category" class="btn btn-primary">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Service Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="editCategoryForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editCategoryModalLabel">Edit Service Category</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <!-- Name -->
          <div class="form-group">
            <label for="edit_name">Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <!-- Color -->
          <div class="form-group">
            <label for="edit_color">Color <span class="text-danger">*</span></label>
            <input type="color" name="edit_color" id="edit_color" class="form-control" required>
            <small class="form-text text-muted">Select a HEX color.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_service_category" class="btn btn-primary">Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- DataTables CSS/JS (CDN) -->
<link
  rel="stylesheet"
  href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css"
/>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
  // Initialize DataTable
  $('#serviceCatTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 4 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#serviceCatTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_name').val(btn.data('name'));
    $('#edit_color').val(btn.data('color'));
    $('#editCategoryModal').modal('show');
  });
});
</script>