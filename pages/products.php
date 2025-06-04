<?php
// pages/products.php — Manage Products with Add, Edit, Delete, Import, and Quantity Adjustment (requires permission: product.manage)

require_once '../auth.php';
requirePermission($pdo, 'product.manage');

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// Fetch categories and VAT types for dropdowns and import lookups
$catsStmt = $pdo->query('SELECT id, name FROM product_category ORDER BY name');
$categories = $catsStmt->fetchAll(PDO::FETCH_ASSOC);

// Map category names (lowercase) → ID
$categoryMap = [];
foreach ($categories as $c) {
    $categoryMap[strtolower($c['name'])] = $c['id'];
}

// Fetch VAT types and build vat_percent → ID map
$vatStmt = $pdo->query('SELECT id, name, vat_percent FROM vat_types ORDER BY vat_percent');
$vatTypes = $vatStmt->fetchAll(PDO::FETCH_ASSOC);
$vatMap = [];
foreach ($vatTypes as $v) {
    $vatMap[number_format($v['vat_percent'], 2)] = $v['id'];
}

// Determine Standard Rate VAT ID (19.00)
$stdVatId = $vatMap['19.00'] ?? null;
if (!$stdVatId) {
    $errorMsg = 'Standard VAT rate (19%) not found. Import will not work.';
}

// -----------------------------------------------------------------------------
// 1) Handle quantity increment/decrement via GET
if (isset($_GET['increment_id'])) {
    $id = (int)$_GET['increment_id'];
    $stmtCheck = $pdo->prepare('SELECT quantity_stock FROM products WHERE id = ?');
    $stmtCheck->execute([$id]);
    if ($row = $stmtCheck->fetch()) {
        $newQty = $row['quantity_stock'] + 1;
        $stmtUpd = $pdo->prepare('UPDATE products SET quantity_stock = ? WHERE id = ?');
        $stmtUpd->execute([$newQty, $id]);
        logAction($pdo, $_SESSION['user_id'], "Incremented stock for product ID {$id} to {$newQty}");
        $successMsg = "Quantity increased to {$newQty}.";
    } else {
        $errorMsg = 'Product not found.';
    }
    header('Location: /pages/products.php');
    exit();
}

if (isset($_GET['decrement_id'])) {
    $id = (int)$_GET['decrement_id'];
    $stmtCheck = $pdo->prepare('SELECT quantity_stock FROM products WHERE id = ?');
    $stmtCheck->execute([$id]);
    if ($row = $stmtCheck->fetch()) {
        $newQty = max(0, $row['quantity_stock'] - 1);
        $stmtUpd = $pdo->prepare('UPDATE products SET quantity_stock = ? WHERE id = ?');
        $stmtUpd->execute([$newQty, $id]);
        logAction($pdo, $_SESSION['user_id'], "Decremented stock for product ID {$id} to {$newQty}");
        $successMsg = "Quantity decreased to {$newQty}.";
    } else {
        $errorMsg = 'Product not found.';
    }
    header('Location: /pages/products.php');
    exit();
}

// -----------------------------------------------------------------------------
// 2) Handle form submission: Add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $category_id          = (int) $_POST['category_id'];
    $name                 = trim($_POST['name']);
    $purchase_price       = trim($_POST['purchase_price']);
    $purchase_vat_type_id = (int) $_POST['purchase_vat_type_id'];
    $sell_price           = trim($_POST['sell_price']);
    $sell_vat_type_id     = (int) $_POST['sell_vat_type_id'];
    $quantity_stock       = (int) $_POST['quantity_stock'];
    $quantity_in_box      = (int) $_POST['quantity_in_box'];
    $comment              = trim($_POST['comment']);

    if (
        $category_id <= 0 ||
        $name === '' ||
        !is_numeric($purchase_price) ||
        $purchase_vat_type_id <= 0 ||
        !is_numeric($sell_price) ||
        $sell_vat_type_id <= 0 ||
        $quantity_stock < 0 ||
        $quantity_in_box <= 0
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO products
              (category_id, name, purchase_price, purchase_vat_type_id, sell_price, sell_vat_type_id, quantity_stock, quantity_in_box, comment)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $category_id,
            $name,
            $purchase_price,
            $purchase_vat_type_id,
            $sell_price,
            $sell_vat_type_id,
            $quantity_stock,
            $quantity_in_box,
            $comment
        ]);
        logAction($pdo, $_SESSION['user_id'], "Added product: {$name}");
        $successMsg = 'Product added successfully.';
    }
}

// -----------------------------------------------------------------------------
// 3) Handle form submission: Edit existing product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id                   = (int) $_POST['edit_id'];
    $category_id          = (int) $_POST['edit_category_id'];
    $name                 = trim($_POST['edit_name']);
    $purchase_price       = trim($_POST['edit_purchase_price']);
    $purchase_vat_type_id = (int) $_POST['edit_purchase_vat_type_id'];
    $sell_price           = trim($_POST['edit_sell_price']);
    $sell_vat_type_id     = (int) $_POST['edit_sell_vat_type_id'];
    $quantity_stock       = (int) $_POST['edit_quantity_stock'];
    $quantity_in_box      = (int) $_POST['edit_quantity_in_box'];
    $comment              = trim($_POST['edit_comment']);

    if (
        $category_id <= 0 ||
        $name === '' ||
        !is_numeric($purchase_price) ||
        $purchase_vat_type_id <= 0 ||
        !is_numeric($sell_price) ||
        $sell_vat_type_id <= 0 ||
        $quantity_stock < 0 ||
        $quantity_in_box <= 0
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        $stmt = $pdo->prepare('
            UPDATE products SET
                category_id = ?, name = ?, purchase_price = ?, purchase_vat_type_id = ?,
                sell_price = ?, sell_vat_type_id = ?, quantity_stock = ?, quantity_in_box = ?, comment = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $category_id,
            $name,
            $purchase_price,
            $purchase_vat_type_id,
            $sell_price,
            $sell_vat_type_id,
            $quantity_stock,
            $quantity_in_box,
            $comment,
            $id
        ]);
        logAction($pdo, $_SESSION['user_id'], "Edited product ID {$id}: {$name}");
        $successMsg = 'Product updated successfully.';
    }
}

// -----------------------------------------------------------------------------
// 4) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name FROM products WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($prodToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted product: {$prodToDelete['name']} (ID: {$delId})");
        $successMsg = 'Product deleted successfully.';
    } else {
        $errorMsg = 'Product not found.';
    }
}

// -----------------------------------------------------------------------------
// 5) Handle XLS/CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_products'])) {
    if (!$stdVatId) {
        $errorMsg = 'Cannot import: Standard VAT rate (19%) missing.';
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please upload a valid XLS/XLSX/CSV file.';
    } else {
        $uploadPath = $_FILES['import_file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($uploadPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $totalRows   = count($rows) - 1;
            $insertCount = 0;
            $skipCount   = 0;

            foreach ($rows as $index => $row) {
                if ($index === 1) continue; // skip header

                // Columns: A=Category, B=Name, C=Purchase Price, D=Sell Price, E=Quantity/Stock, F=Quantity In Box
                $rawCat     = trim($row['A']);
                $name       = trim($row['B']);
                $rawPurchase= trim($row['C']);
                $rawSell    = trim($row['D']);
                $rawStock   = trim($row['E']);
                $rawInBox   = trim($row['F']);

                if ($rawCat === '' || $name === '') {
                    $skipCount++;
                    continue;
                }

                // Lookup category_id (case-insensitive)
                $catKey = strtolower($rawCat);
                if (!isset($categoryMap[$catKey])) {
                    $skipCount++;
                    continue;
                }
                $category_id = $categoryMap[$catKey];

                // Parse purchase price (default 0 if empty/invalid)
                $purchase_price = is_numeric($rawPurchase) ? (float)$rawPurchase : 0.00;

                // Parse sell price (skip if invalid/missing)
                if (!is_numeric($rawSell)) {
                    $skipCount++;
                    continue;
                }
                $sell_price = (float)$rawSell;

                // Parse quantity_stock (default 0 if empty/invalid)
                $quantity_stock = is_numeric($rawStock) ? (int)$rawStock : 0;

                // Parse quantity_in_box (default 1 if invalid)
                $quantity_in_box = is_numeric($rawInBox) && (int)$rawInBox > 0 ? (int)$rawInBox : 1;

                // Skip if duplicate (same category + lower(name))
                $stmtCheck = $pdo->prepare('
                  SELECT id
                    FROM products
                   WHERE LOWER(name) = ? AND category_id = ?
                   LIMIT 1
                ');
                $stmtCheck->execute([strtolower($name), $category_id]);
                if ($stmtCheck->rowCount() > 0) {
                    $skipCount++;
                    continue;
                }

                // Insert with standard VAT (19%) for both purchase and sell
                $stmt = $pdo->prepare('
                    INSERT INTO products
                      (category_id, name, purchase_price, purchase_vat_type_id, sell_price, sell_vat_type_id, quantity_stock, quantity_in_box, comment)
                    VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $category_id,
                    $name,
                    $purchase_price,
                    $stdVatId,
                    $sell_price,
                    $stdVatId,
                    $quantity_stock,
                    $quantity_in_box,
                    ''  // empty comment
                ]);

                $insertCount++;
            }

            logAction($pdo, $_SESSION['user_id'], "Imported {$insertCount} products (skipped {$skipCount}) from spreadsheet");
            $successMsg = "Import complete: {$insertCount} added, {$skipCount} skipped (total rows: {$totalRows}).";
        } catch (Exception $e) {
            $errorMsg = 'Error parsing spreadsheet: ' . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------------
// Fetch all products with category and VAT names for display
$stmt = $pdo->query('
  SELECT
    p.id,
    pc.name AS category_name,
    p.name,
    p.purchase_price,
    vt1.name AS purchase_vat_name,
    p.sell_price,
    vt2.name AS sell_vat_name,
    p.quantity_stock,
    p.quantity_in_box,
    p.comment,
    p.created_at
  FROM products p
  JOIN product_category pc ON p.category_id = pc.id
  JOIN vat_types vt1 ON p.purchase_vat_type_id = vt1.id
  JOIN vat_types vt2 ON p.sell_vat_type_id = vt2.id
  ORDER BY p.created_at DESC
');
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Products';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-4">
          <h1>Products</h1>
        </div>
        <div class="col-sm-8 text-right">
          <!-- Add Product button -->
          <button class="btn btn-success mr-2" data-toggle="modal" data-target="#addProductModal">
            <i class="fas fa-plus-circle"></i> Add Product
          </button>
          <!-- Import Products button -->
          <button class="btn btn-primary" data-toggle="modal" data-target="#importProductsModal">
            <i class="fas fa-file-import"></i> Import Products
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

      <!-- Products Table -->
      <div class="card">
        <div class="card-body">
          <table id="productsTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Name</th>
                <th>Purchase Price</th>
                <th>Purchase VAT</th>
                <th>Sell Price</th>
                <th>Sell VAT</th>
                <th>Quantity</th>
                <th>In Box</th>
                <th>Comment</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['id']); ?></td>
                  <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                  <td><?php echo htmlspecialchars($p['name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($p['purchase_price'], 2)); ?></td>
                  <td><?php echo htmlspecialchars($p['purchase_vat_name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($p['sell_price'], 2)); ?></td>
                  <td><?php echo htmlspecialchars($p['sell_vat_name']); ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <a href="?increment_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success mr-1">+</a>
                      <span><?php echo htmlspecialchars($p['quantity_stock']); ?></span>
                      <a href="?decrement_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger ml-1">−</a>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($p['quantity_in_box']); ?></td>
                  <td><?php echo htmlspecialchars($p['comment']); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($p['created_at']))); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $p['id']; ?>"
                      data-category_id="<?php echo htmlspecialchars($p['category_name']); ?>"
                      data-name="<?php echo htmlspecialchars($p['name']); ?>"
                      data-purchase_price="<?php echo htmlspecialchars($p['purchase_price']); ?>"
                      data-purchase_vat_id="<?php echo htmlspecialchars($p['purchase_vat_name']); ?>"
                      data-sell_price="<?php echo htmlspecialchars($p['sell_price']); ?>"
                      data-sell_vat_id="<?php echo htmlspecialchars($p['sell_vat_name']); ?>"
                      data-quantity_stock="<?php echo $p['quantity_stock']; ?>"
                      data-quantity_in_box="<?php echo $p['quantity_in_box']; ?>"
                      data-comment="<?php echo htmlspecialchars($p['comment']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $p['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this product?');">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="addProductForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
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
          <div class="form-row">
            <!-- Purchase Price & VAT -->
            <div class="form-group col-md-4">
              <label for="purchase_price">Purchase Price <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label for="purchase_vat_type_id">Purchase VAT Type <span class="text-danger">*</span></label>
              <select name="purchase_vat_type_id" id="purchase_vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Sell Price & VAT -->
            <div class="form-group col-md-4">
              <label for="sell_price">Sell Price <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="sell_price" id="sell_price" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label for="sell_vat_type_id">Sell VAT Type <span class="text-danger">*</span></label>
              <select name="sell_vat_type_id" id="sell_vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <!-- Quantity Stock & In Box -->
            <div class="form-group col-md-4">
              <label for="quantity_stock">Quantity <span class="text-danger">*</span></label>
              <input type="number" min="0" name="quantity_stock" id="quantity_stock" class="form-control" value="0" required>
            </div>
            <div class="form-group col-md-4">
              <label for="quantity_in_box">Quantity In Box <span class="text-danger">*</span></label>
              <input type="number" min="1" name="quantity_in_box" id="quantity_in_box" class="form-control" value="1" required>
            </div>
          </div>
          <!-- Comment -->
          <div class="form-group">
            <label for="comment">Comment</label>
            <textarea name="comment" id="comment" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="editProductForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Hidden ID field -->
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
          <div class="form-row">
            <!-- Purchase Price & VAT -->
            <div class="form-group col-md-4">
              <label for="edit_purchase_price">Purchase Price <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="edit_purchase_price" id="edit_purchase_price" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_purchase_vat_type_id">Purchase VAT Type <span class="text-danger">*</span></label>
              <select name="edit_purchase_vat_type_id" id="edit_purchase_vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Sell Price & VAT -->
            <div class="form-group col-md-4">
              <label for="edit_sell_price">Sell Price <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="edit_sell_price" id="edit_sell_price" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_sell_vat_type_id">Sell VAT Type <span class="text-danger">*</span></label>
              <select name="edit_sell_vat_type_id" id="edit_sell_vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <!-- Quantity Stock & In Box -->
            <div class="form-group col-md-4">
              <label for="edit_quantity_stock">Quantity <span class="text-danger">*</span></label>
              <input type="number" min="0" name="edit_quantity_stock" id="edit_quantity_stock" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label for="edit_quantity_in_box">Quantity In Box <span class="text-danger">*</span></label>
              <input type="number" min="1" name="edit_quantity_in_box" id="edit_quantity_in_box" class="form-control" required>
            </div>
          </div>
          <!-- Comment -->
          <div class="form-group">
            <label for="edit_comment">Comment</label>
            <textarea name="edit_comment" id="edit_comment" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_product" class="btn btn-primary">Update Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import Products Modal -->
<div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="importProductsForm">
        <div class="modal-header">
          <h5 class="modal-title" id="importProductsModalLabel">Import Products</h5>
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
              <code>Category, Name, Purchase Price, Sell Price, Quantity/Stock, Quantity In Box</code><br>
              Missing Purchase Price defaults to 0. Standard VAT (19%) will be applied automatically.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="import_products" class="btn btn-primary">Import</button>
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
  $('#productsTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 11 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#productsTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    // Set category dropdown by matching text to value
    $('#edit_category_id').val(
      $("select#edit_category_id option").filter(function() {
        return $(this).text() === btn.data('category_id');
      }).val()
    );
    $('#edit_name').val(btn.data('name'));
    $('#edit_purchase_price').val(btn.data('purchase_price'));
    $('#edit_purchase_vat_type_id').val(
      $("select#edit_purchase_vat_type_id option").filter(function() {
        return $(this).text().includes(btn.data('purchase_vat_id'));
      }).val()
    );
    $('#edit_sell_price').val(btn.data('sell_price'));
    $('#edit_sell_vat_type_id').val(
      $("select#edit_sell_vat_type_id option").filter(function() {
        return $(this).text().includes(btn.data('sell_vat_id'));
      }).val()
    );
    $('#edit_quantity_stock').val(btn.data('quantity_stock'));
    $('#edit_quantity_in_box').val(btn.data('quantity_in_box'));
    $('#edit_comment').val(btn.data('comment'));
    $('#editProductModal').modal('show');
  });
});
</script>