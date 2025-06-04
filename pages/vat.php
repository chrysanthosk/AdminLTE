<?php
// pages/vat.php — Manage VAT Types with Add, Edit, and Delete in modals
require_once '../auth.php';
requirePermission($pdo, 'vat.manage');

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// 1) Handle form submission: Add new VAT type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vat'])) {
    $name        = trim($_POST['name']);
    $vat_percent = trim($_POST['vat_percent']);

    if ($name === '' || $vat_percent === '') {
        $errorMsg = 'Please fill in both fields.';
    } elseif (!is_numeric($vat_percent) || $vat_percent < 0) {
        $errorMsg = 'VAT % must be a non-negative number.';
    } else {
        // Check uniqueness
        $stmtCheck = $pdo->prepare('SELECT id FROM vat_types WHERE vat_percent = ? LIMIT 1');
        $stmtCheck->execute([$vat_percent]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'A VAT rate with this percentage already exists.';
        } else {
            // Insert new VAT type
            $stmt = $pdo->prepare('
                INSERT INTO vat_types (name, vat_percent)
                VALUES (?, ?)
            ');
            $stmt->execute([$name, $vat_percent]);
            logAction($pdo, $_SESSION['user_id'], "Added VAT type: {$name} ({$vat_percent}%)");
            $successMsg = 'VAT type added successfully.';
        }
    }
}

// 2) Handle form submission: Edit existing VAT type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vat'])) {
    $id          = (int) $_POST['edit_id'];
    $name        = trim($_POST['edit_name']);
    $vat_percent = trim($_POST['edit_vat_percent']);

    if ($name === '' || $vat_percent === '') {
        $errorMsg = 'Please fill in both fields when editing.';
    } elseif (!is_numeric($vat_percent) || $vat_percent < 0) {
        $errorMsg = 'VAT % must be a non-negative number.';
    } else {
        // Check uniqueness excluding current record
        $stmtCheck = $pdo->prepare('SELECT id FROM vat_types WHERE vat_percent = ? AND id <> ? LIMIT 1');
        $stmtCheck->execute([$vat_percent, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'Another VAT rate with this percentage already exists.';
        } else {
            // Update VAT type
            $stmt = $pdo->prepare('
                UPDATE vat_types
                SET name = ?, vat_percent = ?
                WHERE id = ?
            ');
            $stmt->execute([$name, $vat_percent, $id]);
            logAction($pdo, $_SESSION['user_id'], "Edited VAT type ID {$id}: {$name} ({$vat_percent}%)");
            $successMsg = 'VAT type updated successfully.';
        }
    }
}

// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name, vat_percent FROM vat_types WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($vatToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM vat_types WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted VAT type: {$vatToDelete['name']} ({$vatToDelete['vat_percent']}%) (ID: {$delId})");
        $successMsg = 'VAT type deleted successfully.';
    } else {
        $errorMsg = 'VAT type not found.';
    }
}

// Fetch all VAT types for DataTable
$stmt = $pdo->query('SELECT id, name, vat_percent, created_at FROM vat_types ORDER BY created_at DESC');
$vats  = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'VAT Types';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>VAT Types</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Add VAT button -->
          <button class="btn btn-success" data-toggle="modal" data-target="#addVatModal">
            <i class="fas fa-plus-circle"></i> Add VAT Type
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

      <!-- VAT Types Table -->
      <div class="card">
        <div class="card-body">
          <table id="vatTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>VAT %</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vats as $v): ?>
                <tr>
                  <td><?php echo htmlspecialchars($v['id']); ?></td>
                  <td><?php echo htmlspecialchars($v['name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($v['vat_percent'], 2)); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($v['created_at']))); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $v['id']; ?>"
                      data-name="<?php echo htmlspecialchars($v['name']); ?>"
                      data-vat="<?php echo htmlspecialchars(number_format($v['vat_percent'], 2)); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $v['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this VAT type?');">
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

<!-- Add VAT Modal -->
<div class="modal fade" id="addVatModal" tabindex="-1" aria-labelledby="addVatModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="addVatForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addVatModalLabel">Add New VAT Type</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Name -->
          <div class="form-group">
            <label for="vat_name">VAT Type Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="vat_name" class="form-control" placeholder="e.g. Standard Rate" required>
          </div>
          <!-- VAT % -->
          <div class="form-group">
            <label for="vat_percent">VAT % <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="vat_percent" id="vat_percent" class="form-control" placeholder="e.g. 19.00" required>
          </div>
          <small class="form-text text-muted">
            The VAT percentage must be unique.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vat" class="btn btn-primary">Save VAT Type</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit VAT Modal -->
<div class="modal fade" id="editVatModal" tabindex="-1" aria-labelledby="editVatModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="editVatForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editVatModalLabel">Edit VAT Type</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Hidden ID field -->
          <input type="hidden" name="edit_id" id="edit_id">

          <!-- Name -->
          <div class="form-group">
            <label for="edit_name">VAT Type Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <!-- VAT % -->
          <div class="form-group">
            <label for="edit_vat_percent">VAT % <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="edit_vat_percent" id="edit_vat_percent" class="form-control" required>
          </div>
          <small class="form-text text-muted">
            The VAT percentage must be unique.
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_vat" class="btn btn-primary">Update VAT Type</button>
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
  $('#vatTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 4 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#vatTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_name').val(btn.data('name'));
    $('#edit_vat_percent').val(btn.data('vat'));
    $('#editVatModal').modal('show');
  });
});
</script>