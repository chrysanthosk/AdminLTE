<?php
// pages/clients.php — Manage Clients with Add, Import, Edit, and Delete in modals
require_once '../auth.php';
requirePermission($pdo, 'client.manage');

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// 1) Handle form submission: Add new client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $dob        = $_POST['dob'];
    $mobile     = trim($_POST['mobile']);
    $notes      = trim($_POST['notes']);
    $email      = trim($_POST['email']);
    $address    = trim($_POST['address']);
    $city       = trim($_POST['city']);
    $gender     = $_POST['gender'];
    $comments   = trim($_POST['comments']);

    if ($first_name === '' || $last_name === '' || $dob === '' || $mobile === '' || $email === '' || $gender === '') {
        $errorMsg = 'Please fill in all required fields for adding a client.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO clients
              (first_name, last_name, dob, mobile, notes, email, address, city, gender, comments)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $first_name,
            $last_name,
            $dob,
            $mobile,
            $notes,
            $email,
            $address,
            $city,
            $gender,
            $comments
        ]);
        logAction($pdo, $_SESSION['user_id'], "Added new client: {$first_name} {$last_name}");
        $successMsg = 'Client added successfully.';
    }
}

// 2) Handle form submission: Edit existing client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_client'])) {
    $id         = (int) $_POST['edit_id'];
    $first_name = trim($_POST['edit_first_name']);
    $last_name  = trim($_POST['edit_last_name']);
    $dob        = $_POST['edit_dob'];
    $mobile     = trim($_POST['edit_mobile']);
    $notes      = trim($_POST['edit_notes']);
    $email      = trim($_POST['edit_email']);
    $address    = trim($_POST['edit_address']);
    $city       = trim($_POST['edit_city']);
    $gender     = $_POST['edit_gender'];
    $comments   = trim($_POST['edit_comments']);

    if ($first_name === '' || $last_name === '' || $dob === '' || $mobile === '' || $email === '' || $gender === '') {
        $errorMsg = 'Please fill in all required fields when editing a client.';
    } else {
        $stmt = $pdo->prepare('
            UPDATE clients SET
                first_name = ?, last_name = ?, dob = ?, mobile = ?, notes = ?,
                email = ?, address = ?, city = ?, gender = ?, comments = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $first_name,
            $last_name,
            $dob,
            $mobile,
            $notes,
            $email,
            $address,
            $city,
            $gender,
            $comments,
            $id
        ]);
        logAction($pdo, $_SESSION['user_id'], "Edited client ID {$id}: {$first_name} {$last_name}");
        $successMsg = 'Client updated successfully.';
    }
}

// 3) Handle XLS Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_clients'])) {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Please upload a valid XLS/XLSX file.';
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

                $rawRegDate = trim($row['A']);
                $first_name = trim($row['B']);
                $last_name  = trim($row['C']);
                $rawDob     = trim($row['D']);
                $mobile     = trim($row['E']);
                $email      = trim($row['F']);
                $address    = trim($row['G']);
                $city       = trim($row['H']);
                $genderRaw  = strtoupper(trim($row['I']));
                $comment    = trim($row['J']);
                $note       = trim($row['K']);

                if ($first_name === '' && $last_name === '') {
                    $skipCount++;
                    continue;
                }

                // Parse DOB
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDob)) {
                    $dob = $rawDob;
                } elseif ($dt = DateTime::createFromFormat('m/d/Y', $rawDob)) {
                    $dob = $dt->format('Y-m-d');
                } elseif ($dt = DateTime::createFromFormat('d/m/Y', $rawDob)) {
                    $dob = $dt->format('Y-m-d');
                } else {
                    $dob = '1970-01-01';
                }

                // Parse registration_date
                $useRegDate = false;
                if ($rawRegDate !== '') {
                    if ($dt2 = DateTime::createFromFormat('m/d/Y h:i:s a', strtolower($rawRegDate))) {
                        $regDate   = $dt2->format('Y-m-d H:i:s');
                        $useRegDate = true;
                    }
                }

                // Map gender
                if ($genderRaw === 'M') {
                    $gender = 'Male';
                } elseif ($genderRaw === 'F') {
                    $gender = 'Female';
                } else {
                    $gender = 'Other';
                }

                // Skip invalid required fields
                if ($first_name === '' || $last_name === '' || $dob === '' || $mobile === '' || $email === '') {
                    $skipCount++;
                    continue;
                }

                // Duplicate check (first_name, last_name, dob, mobile)
                $stmtCheck = $pdo->prepare('
                  SELECT id
                    FROM clients
                   WHERE first_name = ?
                     AND last_name  = ?
                     AND dob        = ?
                     AND mobile     = ?
                   LIMIT 1
                ');
                $stmtCheck->execute([$first_name, $last_name, $dob, $mobile]);
                if ($stmtCheck->rowCount() > 0) {
                    $skipCount++;
                    continue;
                }

                if ($useRegDate) {
                    $stmt = $pdo->prepare('
                        INSERT INTO clients
                          (registration_date, first_name, last_name, dob, mobile, notes, email, address, city, gender, comments)
                        VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $regDate,
                        $first_name,
                        $last_name,
                        $dob,
                        $mobile,
                        $note,
                        $email,
                        $address,
                        $city,
                        $gender,
                        $comment
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO clients
                          (first_name, last_name, dob, mobile, notes, email, address, city, gender, comments)
                        VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $dob,
                        $mobile,
                        $note,
                        $email,
                        $address,
                        $city,
                        $gender,
                        $comment
                    ]);
                }

                $insertCount++;
            }

            logAction($pdo, $_SESSION['user_id'], "Imported {$insertCount} clients (skipped {$skipCount}) from XLS");
            $successMsg = "Import complete: {$insertCount} added, {$skipCount} skipped (total rows: {$totalRows}).";
        } catch (Exception $e) {
            $errorMsg = 'Error parsing spreadsheet: ' . $e->getMessage();
        }
    }
}

// 4) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT first_name, last_name FROM clients WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($clientToDelete = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted client: {$clientToDelete['first_name']} {$clientToDelete['last_name']} (ID: {$delId})");
        $successMsg = 'Client deleted successfully.';
    } else {
        $errorMsg = 'Client not found.';
    }
}

// Fetch all clients
$stmt    = $pdo->query('SELECT id, registration_date, first_name, last_name, dob, mobile, notes, email, address, city, gender, comments FROM clients ORDER BY registration_date DESC');
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Clients';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-4">
          <h1>Clients</h1>
        </div>
        <div class="col-sm-8 text-right">
          <button class="btn btn-success mr-2" data-toggle="modal" data-target="#addClientModal">
            <i class="fas fa-user-plus"></i> Add Client
          </button>
          <button class="btn btn-primary" data-toggle="modal" data-target="#importClientsModal">
            <i class="fas fa-file-import"></i> Import Clients
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <!-- Clients Table -->
      <div class="card">
        <div class="card-body">
          <table id="clientsTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Reg. Date</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>DOB</th>
                <th>Mobile</th>
                <th>Notes</th>
                <th>Email</th>
                <th>Address</th>
                <th>City</th>
                <th>Gender</th>
                <th>Comments</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clients as $c): ?>
                <tr>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($c['registration_date']))); ?></td>
                  <td><?php echo htmlspecialchars($c['first_name']); ?></td>
                  <td><?php echo htmlspecialchars($c['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($c['dob']); ?></td>
                  <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                  <td><?php echo htmlspecialchars($c['notes']); ?></td>
                  <td><?php echo htmlspecialchars($c['email']); ?></td>
                  <td><?php echo htmlspecialchars($c['address']); ?></td>
                  <td><?php echo htmlspecialchars($c['city']); ?></td>
                  <td><?php echo htmlspecialchars($c['gender']); ?></td>
                  <td><?php echo htmlspecialchars($c['comments']); ?></td>
                  <td>
                    <!-- Edit button (opens modal) -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $c['id']; ?>"
                      data-regdate="<?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($c['registration_date']))); ?>"
                      data-first="<?php echo htmlspecialchars($c['first_name']); ?>"
                      data-last="<?php echo htmlspecialchars($c['last_name']); ?>"
                      data-dob="<?php echo htmlspecialchars($c['dob']); ?>"
                      data-mobile="<?php echo htmlspecialchars($c['mobile']); ?>"
                      data-notes="<?php echo htmlspecialchars($c['notes']); ?>"
                      data-email="<?php echo htmlspecialchars($c['email']); ?>"
                      data-address="<?php echo htmlspecialchars($c['address']); ?>"
                      data-city="<?php echo htmlspecialchars($c['city']); ?>"
                      data-gender="<?php echo htmlspecialchars($c['gender']); ?>"
                      data-comments="<?php echo htmlspecialchars($c['comments']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button with JS confirmation -->
                    <a href="?delete_id=<?php echo $c['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this client?');">
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

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="addClientForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addClientModalLabel">Add New Client</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Registration Date (auto) -->
          <div class="form-group row">
            <label class="col-sm-3 col-form-label">Date of Registration</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i'); ?>" disabled>
            </div>
          </div>
          <!-- First Name & Last Name -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" class="form-control" required>
            </div>
          </div>
          <!-- Date of Birth & Mobile -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="dob" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Mobile <span class="text-danger">*</span></label>
              <input type="text" name="mobile" class="form-control" required>
            </div>
          </div>
          <!-- Notes & Email -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Notes (Cashier)</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group col-md-6">
              <label>Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>
          </div>
          <!-- Address & City -->
          <div class="form-row">
            <div class="form-group col-md-8">
              <label>Address</label>
              <input type="text" name="address" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>City</label>
              <input type="text" name="city" class="form-control">
            </div>
          </div>
          <!-- Gender & Comments -->
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Gender <span class="text-danger">*</span></label>
              <select name="gender" class="form-control" required>
                <option value="">-- Select --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group col-md-8">
              <label>Comments</label>
              <textarea name="comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client" class="btn btn-primary">Save Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Client Modal (fields named edit_*) -->
<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="editClientForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Hidden ID field -->
          <input type="hidden" name="edit_id" id="edit_id">

          <!-- Registration Date (display only, not editable) -->
          <div class="form-group row">
            <label class="col-sm-3 col-form-label">Date of Registration</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" id="edit_registration_date" disabled>
            </div>
          </div>

          <!-- First Name & Last Name -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>First Name <span class="text-danger">*</span></label>
              <input type="text" name="edit_first_name" id="edit_first_name" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Last Name <span class="text-danger">*</span></label>
              <input type="text" name="edit_last_name" id="edit_last_name" class="form-control" required>
            </div>
          </div>

          <!-- Date of Birth & Mobile -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="edit_dob" id="edit_dob" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Mobile <span class="text-danger">*</span></label>
              <input type="text" name="edit_mobile" id="edit_mobile" class="form-control" required>
            </div>
          </div>

          <!-- Notes & Email -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Notes (Cashier)</label>
              <textarea name="edit_notes" id="edit_notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group col-md-6">
              <label>Email <span class="text-danger">*</span></label>
              <input type="email" name="edit_email" id="edit_email" class="form-control" required>
            </div>
          </div>

          <!-- Address & City -->
          <div class="form-row">
            <div class="form-group col-md-8">
              <label>Address</label>
              <input type="text" name="edit_address" id="edit_address" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>City</label>
              <input type="text" name="edit_city" id="edit_city" class="form-control">
            </div>
          </div>

          <!-- Gender & Comments -->
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Gender <span class="text-danger">*</span></label>
              <select name="edit_gender" id="edit_gender" class="form-control" required>
                <option value="">-- Select --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group col-md-8">
              <label>Comments</label>
              <textarea name="edit_comments" id="edit_comments" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client" class="btn btn-primary">Update Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import Clients Modal -->
<div class="modal fade" id="importClientsModal" tabindex="-1" aria-labelledby="importClientsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data" id="importClientsForm">
        <div class="modal-header">
          <h5 class="modal-title" id="importClientsModalLabel">Import Clients</h5>
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
              <code>registration_date, first_name, last_name, dob, mobile, email, address, city, gender, comment, note</code><br>
              CSV files should use comma separators.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="import_clients" class="btn btn-primary">Import</button>
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
  $('#clientsTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, 200, -1], [25, 50, 100, 200, "All"]],
    "order": [[0, "desc"]],
    "columnDefs": [
      { "orderable": false, "targets": 11 } // Disable sorting on Actions column
    ]
  });

  // Delegated click handler for Edit buttons
  $('#clientsTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_registration_date').val(btn.data('regdate'));
    $('#edit_first_name').val(btn.data('first'));
    $('#edit_last_name').val(btn.data('last'));
    $('#edit_dob').val(btn.data('dob'));
    $('#edit_mobile').val(btn.data('mobile'));
    $('#edit_notes').val(btn.data('notes'));
    $('#edit_email').val(btn.data('email'));
    $('#edit_address').val(btn.data('address'));
    $('#edit_city').val(btn.data('city'));
    $('#edit_gender').val(btn.data('gender'));
    $('#edit_comments').val(btn.data('comments'));
    $('#editClientModal').modal('show');
  });
});
</script>