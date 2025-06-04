<?php
// pages/services.php — Manage Services (requires permission: services.manage)

require_once '../auth.php';
requirePermission($pdo, 'services.manage');

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// Fetch service categories and build a name→id map (lowercased)
$catStmt = $pdo->query('SELECT id, name FROM service_categories ORDER BY name');
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = [];
foreach ($categories as $c) {
    $categoryMap[strtolower($c['name'])] = $c['id'];
}

// Fetch VAT types and build two maps:
//  • percentMap: "19.00" → id
//  • nameMap: lowercase name → id
$vatStmt = $pdo->query('SELECT id, name, vat_percent FROM vat_types ORDER BY vat_percent');
$vatTypes = $vatStmt->fetchAll(PDO::FETCH_ASSOC);
$vatPercentMap = [];
$vatNameMap    = [];
foreach ($vatTypes as $v) {
    $vatPercentMap[number_format($v['vat_percent'], 2)] = $v['id'];
    $vatNameMap[strtolower($v['name'])]                  = $v['id'];
}

// -----------------------------------------------------------------------------
// 1) Handle form submission: Add new Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name         = trim($_POST['name']);
    $category_id  = (int) $_POST['category_id'];
    $price        = trim($_POST['price']);
    $vat_type_id  = (int) $_POST['vat_type_id'];
    $duration     = (int) $_POST['duration'];
    $waiting      = (int) $_POST['waiting'];
    $gender       = $_POST['gender'];
    $comment      = trim($_POST['comment']);

    // Validation
    if ($name === '' ||
        $category_id <= 0 ||
        !is_numeric($price) ||
        $vat_type_id <= 0 ||
        $duration < 0 ||
        $waiting < 0 ||
        !in_array($gender, ['Male','Female','Both'], true)
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        // Check unique service name
        $stmtCheck = $pdo->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
        $stmtCheck->execute([$name]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'A service with that name already exists.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO services
                  (name, category_id, price, vat_type_id, duration, waiting, gender, comment)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $name,
                $category_id,
                $price,
                $vat_type_id,
                $duration,
                $waiting,
                $gender,
                $comment
            ]);
            logAction($pdo, $_SESSION['user_id'], "Added service: {$name}");
            $successMsg = 'Service added successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 2) Handle form submission: Edit existing Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $id           = (int) $_POST['edit_id'];
    $name         = trim($_POST['edit_name']);
    $category_id  = (int) $_POST['edit_category_id'];
    $price        = trim($_POST['edit_price']);
    $vat_type_id  = (int) $_POST['edit_vat_type_id'];
    $duration     = (int) $_POST['edit_duration'];
    $waiting      = (int) $_POST['edit_waiting'];
    $gender       = $_POST['edit_gender'];
    $comment      = trim($_POST['edit_comment']);

    if ($name === '' ||
        $category_id <= 0 ||
        !is_numeric($price) ||
        $vat_type_id <= 0 ||
        $duration < 0 ||
        $waiting < 0 ||
        !in_array($gender, ['Male','Female','Both'], true)
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        // Check unique service name excluding current
        $stmtCheck = $pdo->prepare('SELECT id FROM services WHERE name = ? AND id <> ? LIMIT 1');
        $stmtCheck->execute([$name, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'Another service with that name already exists.';
        } else {
            $stmt = $pdo->prepare('
                UPDATE services SET
                  name = ?, category_id = ?, price = ?, vat_type_id = ?, duration = ?, waiting = ?, gender = ?, comment = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $name,
                $category_id,
                $price,
                $vat_type_id,
                $duration,
                $waiting,
                $gender,
                $comment,
                $id
            ]);
            logAction($pdo, $_SESSION['user_id'], "Edited service ID {$id}: {$name}");
            $successMsg = 'Service updated successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId   = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT name FROM services WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($svcToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted service: {$svcToDelete['name']} (ID: {$delId})");
        $successMsg = 'Service deleted successfully.';
    } else {
        $errorMsg = 'Service not found.';
    }
}

// -----------------------------------------------------------------------------
// 4) Handle XLS/CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_services'])) {
    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please upload a valid XLS, XLSX, or CSV file.';
    } else {
        $uploadPath   = $_FILES['import_file']['tmp_name'];
        $originalName = $_FILES['import_file']['name'];
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $totalRows   = 0;
        $insertCount = 0;
        $skipCount   = 0;

        // ===== CSV Path: using fgetcsv() to guarantee header is dropped =====
        if ($extension === 'csv') {
            if (($handle = fopen($uploadPath, 'r')) !== false) {
                // 1) Read & discard the first line as header
                fgetcsv($handle);

                // 2) Process each subsequent line
                while (($data = fgetcsv($handle)) !== false) {
                    $totalRows++;

                    // Ensure we have at least 8 columns
                    for ($i = 0; $i < 8; $i++) {
                        if (!isset($data[$i])) {
                            $data[$i] = '';
                        }
                    }
                    list($rawCat, $name, $rawGender, $rawPrice, $rawVat, $rawDur, $rawWait, $comment) = array_map('trim', $data);

                    // 3) Skip if CATEGORY or NAME is empty
                    if ($rawCat === '' || $name === '') {
                        $skipCount++;
                        continue;
                    }

                    // 4) Lookup category_id (case-insensitive)
                    $catKey = strtolower($rawCat);
                    if (!isset($categoryMap[$catKey])) {
                        $skipCount++;
                        continue;
                    }
                    $category_id = $categoryMap[$catKey];

                    // 5) Normalize Gender → only 'Male','Female','Both'
                    //    • strip out any non-alpha characters (including BOM, NBSP, etc.)
                    //    • lowercase and compare
                    $lettersOnly = strtolower(preg_replace('/[^a-z]/i', '', $rawGender));
                    if ($lettersOnly === 'male') {
                        $g = 'Male';
                    } elseif ($lettersOnly === 'female') {
                        $g = 'Female';
                    } elseif ($lettersOnly === 'both') {
                        $g = 'Both';
                    } else {
                        $g = 'Both';
                    }

                    // 6) Parse price (default to 0.00 if invalid)
                    $price = is_numeric($rawPrice) ? (float)$rawPrice : 0.00;

                    // 7) Lookup vat_type_id by percent or name
                    $vat_type_id = 0;
                    if (is_numeric($rawVat)) {
                        $key = number_format((float)$rawVat, 2);
                        if (isset($vatPercentMap[$key])) {
                            $vat_type_id = $vatPercentMap[$key];
                        }
                    } else {
                        $vnKey = strtolower($rawVat);
                        if (isset($vatNameMap[$vnKey])) {
                            $vat_type_id = $vatNameMap[$vnKey];
                        }
                    }
                    if ($vat_type_id <= 0) {
                        $skipCount++;
                        continue;
                    }

                    // 8) Parse duration & waiting (default 0 if invalid)
                    $duration = is_numeric($rawDur) ? (int)$rawDur : 0;
                    $waiting  = is_numeric($rawWait) ? (int)$rawWait : 0;

                    // 9) Duplicate check: skip if lower(name) already exists
                    $stmtCheck = $pdo->prepare(
                        'SELECT id FROM services WHERE LOWER(name) = ? LIMIT 1'
                    );
                    $stmtCheck->execute([strtolower($name)]);
                    if ($stmtCheck->rowCount() > 0) {
                        $skipCount++;
                        continue;
                    }

                    // 10) Attempt insertion inside try/catch
                    try {
                        $stmt = $pdo->prepare('
                            INSERT INTO services
                              (name, category_id, price, vat_type_id, duration, waiting, gender, comment)
                            VALUES
                              (?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $name,
                            $category_id,
                            $price,
                            $vat_type_id,
                            $duration,
                            $waiting,
                            $g,
                            $comment
                        ]);
                        $insertCount++;
                    }
                    catch (PDOException $e) {
                        // If MySQL complains about truncated ENUM for gender, skip this row
                        // (we can also log $rawGender here for debugging)
                        $skipCount++;
                        continue;
                    }
                }
                fclose($handle);
            } else {
                $errorMsg = 'Unable to open the uploaded CSV file.';
            }
        }

        // ===== XLS/XLSX Path: fallback to PhpSpreadsheet, same logic but skip row index 1 =====
        else {
            $spreadsheet = IOFactory::load($uploadPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            foreach ($rows as $index => $row) {
                // 1) Skip the header row at index = 1
                if ($index === 1) {
                    continue;
                }

                $totalRows++;

                // 2) Read & trim columns A…H
                $rawCat    = trim((string)($row['A'] ?? ''));
                $name      = trim((string)($row['B'] ?? ''));
                $rawGender = trim((string)($row['C'] ?? ''));
                $rawPrice  = trim((string)($row['D'] ?? ''));
                $rawVat    = trim((string)($row['E'] ?? ''));
                $rawDur    = trim((string)($row['F'] ?? ''));
                $rawWait   = trim((string)($row['G'] ?? ''));
                $comment   = trim((string)($row['H'] ?? ''));

                // 3) Skip if CATEGORY or NAME is empty
                if ($rawCat === '' || $name === '') {
                    $skipCount++;
                    continue;
                }

                // 4) Lookup category_id (case-insensitive)
                $catKey = strtolower($rawCat);
                if (!isset($categoryMap[$catKey])) {
                    $skipCount++;
                    continue;
                }
                $category_id = $categoryMap[$catKey];

                // 5) Normalize Gender
                $lettersOnly = strtolower(preg_replace('/[^a-z]/i', '', $rawGender));
                if ($lettersOnly === 'male') {
                    $g = 'Male';
                } elseif ($lettersOnly === 'female') {
                    $g = 'Female';
                } elseif ($lettersOnly === 'both') {
                    $g = 'Both';
                } else {
                    $g = 'Both';
                }

                // 6) Parse price (default 0.00 if invalid)
                $price = is_numeric($rawPrice) ? (float)$rawPrice : 0.00;

                // 7) Lookup vat_type_id by percent or name
                $vat_type_id = 0;
                if (is_numeric($rawVat)) {
                    $key = number_format((float)$rawVat, 2);
                    if (isset($vatPercentMap[$key])) {
                        $vat_type_id = $vatPercentMap[$key];
                    }
                } else {
                    $vnKey = strtolower($rawVat);
                    if (isset($vatNameMap[$vnKey])) {
                        $vat_type_id = $vatNameMap[$vnKey];
                    }
                }
                if ($vat_type_id <= 0) {
                    $skipCount++;
                    continue;
                }

                // 8) Parse duration & waiting
                $duration = is_numeric($rawDur) ? (int)$rawDur : 0;
                $waiting  = is_numeric($rawWait) ? (int)$rawWait : 0;

                // 9) Duplicate check
                $stmtCheck = $pdo->prepare(
                    'SELECT id FROM services WHERE LOWER(name) = ? LIMIT 1'
                );
                $stmtCheck->execute([strtolower($name)]);
                if ($stmtCheck->rowCount() > 0) {
                    $skipCount++;
                    continue;
                }

                // 10) Insert inside try/catch
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO services
                          (name, category_id, price, vat_type_id, duration, waiting, gender, comment)
                        VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $name,
                        $category_id,
                        $price,
                        $vat_type_id,
                        $duration,
                        $waiting,
                        $g,
                        $comment
                    ]);
                    $insertCount++;
                }
                catch (PDOException $e) {
                    // Skip this row (likely due to a truncated ENUM warning on gender)
                    $skipCount++;
                    continue;
                }
            }
        }

        // 11) Log and report
        logAction(
            $pdo,
            $_SESSION['user_id'],
            "Imported {$insertCount} services (skipped {$skipCount}) from spreadsheet"
        );
        $successMsg = "Import complete: {$insertCount} added, {$skipCount} skipped (total rows: {$totalRows}).";
    }
}

// -----------------------------------------------------------------------------
// 5) Fetch all services with category and VAT names for display
$stmt = $pdo->query('
  SELECT
    s.id,
    s.name,
    sc.name AS category_name,
    s.price,
    vt.name AS vat_name,
    s.duration,
    s.waiting,
    s.gender,
    s.comment,
    s.created_at
  FROM services s
  JOIN service_categories sc ON s.category_id = sc.id
  JOIN vat_types vt ON s.vat_type_id = vt.id
  ORDER BY s.created_at DESC
');
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Services';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-4">
          <h1>Services</h1>
        </div>
        <div class="col-sm-8 text-right">
          <!-- Add Service button -->
          <button class="btn btn-success mr-2" data-toggle="modal" data-target="#addServiceModal">
            <i class="fas fa-plus-circle"></i> Add Service
          </button>
          <!-- Import Services button -->
          <button class="btn btn-primary" data-toggle="modal" data-target="#importServicesModal">
            <i class="fas fa-file-import"></i> Import Services
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

      <!-- Services Table -->
      <div class="card">
        <div class="card-body">
          <table id="servicesTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price (€)</th>
                <th>VAT</th>
                <th>Duration</th>
                <th>Waiting</th>
                <th>Gender</th>
                <th>Comment</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['id']); ?></td>
                  <td><?php echo htmlspecialchars($s['name']); ?></td>
                  <td><?php echo htmlspecialchars($s['category_name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($s['price'], 2)); ?></td>
                  <td><?php echo htmlspecialchars($s['vat_name']); ?></td>
                  <td><?php echo htmlspecialchars($s['duration']); ?>   min</td>
                  <td><?php echo htmlspecialchars($s['waiting']); ?>   min</td>
                  <td><?php echo htmlspecialchars($s['gender']); ?></td>
                  <td><?php echo htmlspecialchars($s['comment']); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($s['created_at']))); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $s['id']; ?>"
                      data-name="<?php echo htmlspecialchars($s['name']); ?>"
                      data-category="<?php echo htmlspecialchars($s['category_name']); ?>"
                      data-price="<?php echo htmlspecialchars($s['price']); ?>"
                      data-vat="<?php echo htmlspecialchars($s['vat_name']); ?>"
                      data-duration="<?php echo htmlspecialchars($s['duration']); ?>"
                      data-waiting="<?php echo htmlspecialchars($s['waiting']); ?>"
                      data-gender="<?php echo htmlspecialchars($s['gender']); ?>"
                      data-comment="<?php echo htmlspecialchars($s['comment']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $s['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this service?');">
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

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="addServiceForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addServiceModalLabel">Add New Service</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Service Name -->
          <div class="form-group">
            <label for="name">Service Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" placeholder="Unique service name" required>
          </div>
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
          <div class="form-row">
            <!-- Price -->
            <div class="form-group col-md-4">
              <label for="price">Price (€) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required>
            </div>
            <!-- VAT Type -->
            <div class="form-group col-md-4">
              <label for="vat_type_id">VAT Type <span class="text-danger">*</span></label>
              <select name="vat_type_id" id="vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Duration -->
            <div class="form-group col-md-2">
              <label for="duration">Duration (min) <span class="text-danger">*</span></label>
              <input type="number" min="0" name="duration" id="duration" class="form-control" required>
            </div>
            <!-- Waiting -->
            <div class="form-group col-md-2">
              <label for="waiting">Waiting (min) <span class="text-danger">*</span></label>
              <input type="number" min="0" name="waiting" id="waiting" class="form-control" required>
            </div>
          </div>
          <!-- Gender -->
          <div class="form-group">
            <label for="gender">Gender <span class="text-danger">*</span></label>
            <select name="gender" id="gender" class="form-control" required>
              <option value="">-- Select Gender --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Both">Both</option>
            </select>
          </div>
          <!-- Comment -->
          <div class="form-group">
            <label for="comment">Comment</label>
            <textarea name="comment" id="comment" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_service" class="btn btn-primary">Save Service</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Service Modal -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="editServiceForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editServiceModalLabel">Edit Service</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">

          <!-- Service Name -->
          <div class="form-group">
            <label for="edit_name">Service Name <span class="text-danger">*</span></label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
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
          <div class="form-row">
            <!-- Price -->
            <div class="form-group col-md-4">
              <label for="edit_price">Price (€) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="edit_price" id="edit_price" class="form-control" required>
            </div>
            <!-- VAT Type -->
            <div class="form-group col-md-4">
              <label for="edit_vat_type_id">VAT Type <span class="text-danger">*</span></label>
              <select name="edit_vat_type_id" id="edit_vat_type_id" class="form-control" required>
                <option value="">-- Select VAT --</option>
                <?php foreach ($vatTypes as $v): ?>
                  <option value="<?php echo $v['id']; ?>">
                    <?php echo htmlspecialchars($v['name'] . ' (' . number_format($v['vat_percent'], 2) . '%)'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Duration -->
            <div class="form-group col-md-2">
              <label for="edit_duration">Duration (min) <span class="text-danger">*</span></label>
              <input type="number" min="0" name="edit_duration" id="edit_duration" class="form-control" required>
            </div>
            <!-- Waiting -->
            <div class="form-group col-md-2">
              <label for="edit_waiting">Waiting (min) <span class="text-danger">*</span></label>
              <input type="number" min="0" name="edit_waiting" id="edit_waiting" class="form-control" required>
            </div>
          </div>
          <!-- Gender -->
          <div class="form-group">
            <label for="edit_gender">Gender <span class="text-danger">*</span></label>
            <select name="edit_gender" id="edit_gender" class="form-control" required>
              <option value="">-- Select Gender --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Both">Both</option>
            </select>
          </div>
          <!-- Comment -->
          <div class="form-group">
            <label for="edit_comment">Comment</label>
            <textarea name="edit_comment" id="edit_comment" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_service" class="btn btn-primary">Update Service</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import Services Modal -->
<div class="modal fade" id="importServicesModal" tabindex="-1" aria-labelledby="importServicesModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="importServicesForm">
        <div class="modal-header">
          <h5 class="modal-title" id="importServicesModalLabel">Import Services</h5>
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
              <code>category,name,price,vat,duration,waiting,comment</code><br>
              “category” and “vat” must match existing entries exactly (case‐insensitive).<br>
              Gender defaults to “Male” on import.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="import_services" class="btn btn-primary">Import</button>
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
  $('#servicesTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 10 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#servicesTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_name').val(btn.data('name'));
    // set category dropdown by matching text
    $('#edit_category_id').val(
      $("select#edit_category_id option").filter(function() {
        return $(this).text() === btn.data('category');
      }).val()
    );
    $('#edit_price').val(btn.data('price'));
    // set VAT dropdown by matching text
    $('#edit_vat_type_id').val(
      $("select#edit_vat_type_id option").filter(function() {
        return $(this).text().includes(btn.data('vat'));
      }).val()
    );
    $('#edit_duration').val(btn.data('duration'));
    $('#edit_waiting').val(btn.data('waiting'));
    $('#edit_gender').val(btn.data('gender'));
    $('#edit_comment').val(btn.data('comment'));
    $('#editServiceModal').modal('show');
  });
});
</script>