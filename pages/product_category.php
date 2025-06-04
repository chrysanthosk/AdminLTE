<?php
// pages/product_category.php — Manage Product Categories (requires permission: product_category.manage)

require_once '../auth.php';
requirePermission($pdo, 'product_category.manage');

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// 1) Handle form submission: Add new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);

    if ($name === '') {
        $errorMsg = 'Category name cannot be empty.';
    } else {
        // Check uniqueness
        $stmtCheck = $pdo->prepare('SELECT id FROM product_category WHERE name = ? LIMIT 1');
        $stmtCheck->execute([$name]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'A category with this name already exists.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO product_category (name) VALUES (?)');
            $stmt->execute([$name]);
            logAction($pdo, $_SESSION['user_id'], "Added product category: {$name}");
            $successMsg = 'Category added successfully.';
        }
    }
}

// 2) Handle form submission: Edit existing category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id   = (int) $_POST['edit_id'];
    $name = trim($_POST['edit_name']);

    if ($name === '') {
        $errorMsg = 'Category name cannot be empty.';
    } else {
        // Check uniqueness (excluding this record)
        $stmtCheck = $pdo->prepare('SELECT id FROM product_category WHERE name = ? AND id <> ? LIMIT 1');
        $stmtCheck->execute([$name, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'Another category with this name already exists.';
        } else {
            $stmt = $pdo->prepare('UPDATE product_category SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            logAction($pdo, $_SESSION['user_id'], "Edited product category ID {$id}: {$name}");
            $successMsg = 'Category updated successfully.';
        }
    }
}

// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name FROM product_category WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($catToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM product_category WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted product category: {$catToDelete['name']} (ID: {$delId})");
        $successMsg = 'Category deleted successfully.';
    } else {
        $errorMsg = 'Category not found.';
    }
}

// Fetch all categories for DataTable
$stmt = $pdo->query('SELECT id, name, created_at FROM product_category ORDER BY created_at DESC');
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Product Categories';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Product Categories</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Add Category button -->
          <button class="btn btn-success" data-toggle="modal" data-target="#addCategoryModal">
            <i class="fas fa-plus-circle"></i> Add Category
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

      <!-- Categories Table -->
      <div class="card">
        <div class="card-body">
          <table id="categoriesTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cats as $c): ?>
                <tr>
                  <td><?php echo htmlspecialchars($c['id']); ?></td>
                  <td><?php echo htmlspecialchars($c['name']); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($c['created_at']))); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $c['id']; ?>"
                      data-name="<?php echo htmlspecialchars($c['name']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $c['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this category?');">
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="addCategoryForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="cat_name">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="cat_name" class="form-control" placeholder="e.g. Electronics" required>
          </div>
          <small class="form-text text-muted">
            Category name must be unique.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_category" class="btn btn-primary">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="editCategoryForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Hidden ID field -->
          <input type="hidden" name="edit_id" id="edit_id">

          <div class="form-group">
            <label for="edit_name">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <small class="form-text text-muted">
            Category name must be unique.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
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
  $('#categoriesTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 3 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#categoriesTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_name').val(btn.data('name'));
    $('#editCategoryModal').modal('show');
  });
});
</script>