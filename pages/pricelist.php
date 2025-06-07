<?php
// pages/pricelist.php — Manage PriceList (requires permission: pricelist.manage)

require_once '../auth.php';
requirePermission($pdo, 'pricelist.manage');

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// Fetch all service categories to populate dropdown & build import map
$catStmt = $pdo->query('SELECT id, name FROM pricelist_categories ORDER BY name');
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = [];
foreach ($categories as $c) {
    $categoryMap[strtolower($c['name'])] = $c['id'];
}

// -----------------------------------------------------------------------------
// 1) Handle form submission: Add new PriceList entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pricelist'])) {
    $category_id = (int) $_POST['category_id'];
    $name        = trim($_POST['name']);
    $price       = trim($_POST['price']);

    if ($category_id <= 0 || $name === '' || !is_numeric($price)) {
        $errorMsg = 'Please fill in all fields correctly.';
    } else {
        // Check unique (category_id + name)
        $stmtCheck = $pdo->prepare(
            'SELECT id FROM pricelist WHERE category_id = ? AND name = ? LIMIT 1'
        );
        $stmtCheck->execute([$category_id, $name]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'This item already exists in the PriceList.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO pricelist (category_id, name, price)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$category_id, $name, (float)$price]);
            logAction($pdo, $_SESSION['user_id'], "Added PriceList: {$name} ({$price})");
            $successMsg = 'Pricelist entry added successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 2) Handle form submission: Edit existing PriceList entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pricelist'])) {
    $id          = (int) $_POST['edit_id'];
    $category_id = (int) $_POST['edit_category_id'];
    $name        = trim($_POST['edit_name']);
    $price       = trim($_POST['edit_price']);

    if ($category_id <= 0 || $name === '' || !is_numeric($price)) {
        $errorMsg = 'Please fill in all fields correctly when editing.';
    } else {
        // Check unique excluding current ID
        $stmtCheck = $pdo->prepare(
            'SELECT id FROM pricelist WHERE category_id = ? AND name = ? AND id <> ? LIMIT 1'
        );
        $stmtCheck->execute([$category_id, $name, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'Another entry with that name exists in this category.';
        } else {
            $stmt = $pdo->prepare('
                UPDATE pricelist
                   SET category_id = ?, name = ?, price = ?
                 WHERE id = ?
            ');
            $stmt->execute([$category_id, $name, (float)$price, $id]);
            logAction($pdo, $_SESSION['user_id'], "Edited PriceList ID {$id}: {$name} ({$price})");
            $successMsg = 'Pricelist entry updated successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId   = (int) $_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name, price FROM pricelist WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($row = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM pricelist WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted PriceList entry: {$row['name']} ({$row['price']}) ID {$delId}");
        $successMsg = 'Pricelist entry deleted successfully.';
    } else {
        $errorMsg = 'Pricelist entry not found.';
    }
}

// -----------------------------------------------------------------------------
// 4) Handle XLS/XLSX/CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_pricelist'])) {
    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please upload a valid XLS, XLSX, or CSV file.';
    } else {
        $uploadPath   = $_FILES['import_file']['tmp_name'];
        $originalName = $_FILES['import_file']['name'];
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $totalRows   = 0;
        $insertCount = 0;
        $skipCount   = 0;

        // --- CSV path using fgetcsv() ---
        if ($extension === 'csv') {
            if (($handle = fopen($uploadPath, 'r')) !== false) {
                // Discard header line
                fgetcsv($handle);

                while (($data = fgetcsv($handle)) !== false) {
                    $totalRows++;

                    // Ensure at least 3 columns: [0]=CATEGORY, [1]=NAME, [2]=PRICE
                    for ($i = 0; $i < 3; $i++) {
                        if (!isset($data[$i])) {
                            $data[$i] = '';
                        }
                    }
                    list($rawCat, $name, $rawPrice) = array_map('trim', $data);

                    // 1) Skip if CATEGORY or NAME empty
                    if ($rawCat === '' || $name === '') {
                        $skipCount++;
                        continue;
                    }

                    // 2) Lookup category_id
                    $catKey = strtolower($rawCat);
                    if (!isset($categoryMap[$catKey])) {
                        $skipCount++;
                        continue;
                    }
                    $category_id = $categoryMap[$catKey];

                    // 3) Parse price
                    $price = is_numeric($rawPrice) ? (float)$rawPrice : null;
                    if ($price === null) {
                        $skipCount++;
                        continue;
                    }

                    // 4) Duplicate check: skip if (category_id, name) exists
                    $stmtCheck = $pdo->prepare(
                        'SELECT id FROM pricelist
                           WHERE category_id = ?
                             AND LOWER(name) = ?
                           LIMIT 1'
                    );
                    $stmtCheck->execute([$category_id, strtolower($name)]);
                    if ($stmtCheck->rowCount() > 0) {
                        $skipCount++;
                        continue;
                    }

                    // 5) Insert inside try/catch
                    try {
                        $stmt = $pdo->prepare('
                            INSERT INTO pricelist (category_id, name, price)
                            VALUES (?, ?, ?)
                        ');
                        $stmt->execute([$category_id, $name, $price]);
                        $insertCount++;
                    }
                    catch (PDOException $e) {
                        $skipCount++;
                        continue;
                    }
                }
                fclose($handle);
            } else {
                $errorMsg = 'Unable to open the uploaded CSV file.';
            }
        }

        // --- .xls or .xlsx path via PhpSpreadsheet ---
        else {
            $spreadsheet = IOFactory::load($uploadPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            foreach ($rows as $index => $row) {
                // Skip header at index 1
                if ($index === 1) {
                    continue;
                }

                $totalRows++;
                $rawCat   = trim((string)($row['A'] ?? ''));
                $name     = trim((string)($row['B'] ?? ''));
                $rawPrice = trim((string)($row['C'] ?? ''));

                // 1) Skip if CATEGORY or NAME empty
                if ($rawCat === '' || $name === '') {
                    $skipCount++;
                    continue;
                }

                // 2) Lookup category_id
                $catKey = strtolower($rawCat);
                if (!isset($categoryMap[$catKey])) {
                    $skipCount++;
                    continue;
                }
                $category_id = $categoryMap[$catKey];

                // 3) Parse price
                $price = is_numeric($rawPrice) ? (float)$rawPrice : null;
                if ($price === null) {
                    $skipCount++;
                    continue;
                }

                // 4) Duplicate check
                $stmtCheck = $pdo->prepare(
                    'SELECT id FROM pricelist
                       WHERE category_id = ?
                         AND LOWER(name) = ?
                       LIMIT 1'
                );
                $stmtCheck->execute([$category_id, strtolower($name)]);
                if ($stmtCheck->rowCount() > 0) {
                    $skipCount++;
                    continue;
                }

                // 5) Insert inside try/catch
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO pricelist (category_id, name, price)
                        VALUES (?, ?, ?)
                    ');
                    $stmt->execute([$category_id, $name, $price]);
                    $insertCount++;
                }
                catch (PDOException $e) {
                    $skipCount++;
                    continue;
                }
            }
        }

        logAction(
            $pdo,
            $_SESSION['user_id'],
            "Imported {$insertCount} pricelist entries (skipped {$skipCount})"
        );
        $successMsg = "Import complete: {$insertCount} added, {$skipCount} skipped (total rows: {$totalRows}).";
    }
}

// -----------------------------------------------------------------------------
// 5) Fetch all pricelist entries for display
$stmt = $pdo->query('
  SELECT
    p.id,
    sc.name AS category_name,
    p.name,
    p.price,
    p.created_at
  FROM pricelist p
  JOIN pricelist_categories sc ON p.category_id = sc.id
  ORDER BY p.created_at DESC
');
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'PriceList';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>PriceList</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Add PriceList button -->
          <button class="btn btn-success" data-toggle="modal" data-target="#addPriceListModal">
            <i class="fas fa-plus-circle"></i> Add PriceList
          </button>
          <!-- Import button -->
          <button class="btn btn-primary ml-2" data-toggle="modal" data-target="#importPriceListModal">
            <i class="fas fa-file-import"></i> Import PriceList
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content area -->
  <section class="content">
    <div class="container-fluid">
      <!-- Show messages -->
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <!-- PriceList table -->
      <div class="card">
        <div class="card-body">
          <table id="pricelistTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Name</th>
                <th>Price (€)</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?php echo htmlspecialchars($it['id']); ?></td>
                  <td><?php echo htmlspecialchars($it['category_name']); ?></td>
                  <td><?php echo htmlspecialchars($it['name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($it['price'], 2)); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($it['created_at']))); ?></td>
                  <td>
                    <!-- Edit button -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $it['id']; ?>"
                      data-category="<?php echo htmlspecialchars($it['category_name']); ?>"
                      data-name="<?php echo htmlspecialchars($it['name']); ?>"
                      data-price="<?php echo htmlspecialchars($it['price']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button -->
                    <a href="?delete_id=<?php echo $it['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this entry?');">
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

<!-- Add PriceList Modal -->
<div class="modal fade" id="addPriceListModal" tabindex="-1" aria-labelledby="addPriceListModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="addPriceListForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addPriceListModalLabel">Add New PriceList Entry</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Category -->
          <div class="form-group">
            <label for="category_id">Category <span class="text-danger">*</span></label>
            <select name="category_id" id="category_id" class="form-control" required>
              <option value="">-- Select Category --</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Name -->
          <div class="form-group">
            <label for="name">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" required>
          </div>
          <!-- Price -->
          <div class="form-group">
            <label for="price">Price (€) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_pricelist" class="btn btn-primary">Save Entry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit PriceList Modal -->
<div class="modal fade" id="editPriceListModal" tabindex="-1" aria-labelledby="editPriceListModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="editPriceListForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editPriceListModalLabel">Edit PriceList Entry</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <!-- Category -->
          <div class="form-group">
            <label for="edit_category_id">Category <span class="text-danger">*</span></label>
            <select name="edit_category_id" id="edit_category_id" class="form-control" required>
              <option value="">-- Select Category --</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Name -->
          <div class="form-group">
            <label for="edit_name">Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <!-- Price -->
          <div class="form-group">
            <label for="edit_price">Price (€) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="edit_price" id="edit_price" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_pricelist" class="btn btn-primary">Update Entry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import PriceList Modal -->
<div class="modal fade" id="importPriceListModal" tabindex="-1" aria-labelledby="importPriceListModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="importPriceListForm">
        <div class="modal-header">
          <h5 class="modal-title" id="importPriceListModalLabel">Import PriceList</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Select File (XLS, XLSX, or CSV)</label>
            <input type="file"
                   name="import_file"
                   accept=".xls, .xlsx, .csv"
                   class="form-control-file"
                   required>
            <small class="form-text text-muted">
              File must have columns (in order):<br>
              <code>CATEGORY, NAME, PRICE</code><br>
              “CATEGORY” must exactly match a Service Category (case-insensitive).<br>
              PRICE must be a valid number (e.g., 19.99). Rows with missing/invalid data will be skipped.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="import_pricelist" class="btn btn-primary">Import</button>
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
  $('#pricelistTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 5 } // Actions column
    ]
  });

  // Handle “Edit” button click
  $('#pricelistTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    const categoryText = btn.data('category');
    $('#edit_category_id').val(
      $("select#edit_category_id option").filter(function() {
        return $(this).text() === categoryText;
      }).val()
    );
    $('#edit_name').val(btn.data('name'));
    $('#edit_price').val(btn.data('price'));
    $('#editPriceListModal').modal('show');
  });
});
</script>