<?php
// pages/therapists.php — Manage Therapists (requires permission: therapists.manage)

require_once '../auth.php';
requirePermission($pdo, 'therapists.manage');

// Initialize messages
$errorMsg   = '';
$successMsg = '';

// -----------------------------------------------------------------------------
// 1) Handle “Add Therapist” form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_therapist'])) {
    $username         = trim($_POST['username']);
    $password         = $_POST['password'];
    $first_name       = trim($_POST['first_name']);
    $last_name        = trim($_POST['last_name']);
    $mobile           = trim($_POST['mobile']);
    $dob              = $_POST['dob'];           // expect YYYY-MM-DD
    $level            = $_POST['level'];
    $color            = trim($_POST['color']);
    $show_in_calendar = isset($_POST['show_in_calendar']) ? 1 : 0;
    $position         = (int)$_POST['position'];

    // Basic validation
    if ($username === '' ||
        $password === '' ||
        $first_name === '' ||
        $last_name === '' ||
        !in_array($level, ['Therapist','Reception'], true) ||
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        // Check unique username
        $stmtCheck = $pdo->prepare('SELECT id FROM therapists WHERE username = ? LIMIT 1');
        $stmtCheck->execute([$username]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'That username is already taken.';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('
                INSERT INTO therapists
                  (username, password_hash, first_name, last_name, mobile, dob, level, color, show_in_calendar, position)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $username,
                $password_hash,
                $first_name,
                $last_name,
                $mobile,
                $dob,
                $level,
                $color,
                $show_in_calendar,
                $position
            ]);
            logAction($pdo, $_SESSION['user_id'], "Added therapist: {$username}");
            $successMsg = 'Therapist added successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 2) Handle “Edit Therapist” form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_therapist'])) {
    $id               = (int)$_POST['edit_id'];
    $username         = trim($_POST['edit_username']);
    $password         = $_POST['edit_password'];
    $first_name       = trim($_POST['edit_first_name']);
    $last_name        = trim($_POST['edit_last_name']);
    $mobile           = trim($_POST['edit_mobile']);
    $dob              = $_POST['edit_dob'];
    $level            = $_POST['edit_level'];
    $color            = trim($_POST['edit_color']);
    $show_in_calendar = isset($_POST['edit_show_in_calendar']) ? 1 : 0;
    $position         = (int)$_POST['edit_position'];

    if ($username === '' ||
        $first_name === '' ||
        $last_name === '' ||
        !in_array($level, ['Therapist','Reception'], true) ||
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)
    ) {
        $errorMsg = 'Please fill in all required fields correctly.';
    } else {
        // Check unique username (excluding current)
        $stmtCheck = $pdo->prepare('SELECT id FROM therapists WHERE username = ? AND id <> ? LIMIT 1');
        $stmtCheck->execute([$username, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $errorMsg = 'That username is already taken.';
        } else {
            if ($password !== '') {
                // Update with new password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    UPDATE therapists SET
                      username = ?, password_hash = ?, first_name = ?, last_name = ?, mobile = ?, dob = ?, level = ?, color = ?, show_in_calendar = ?, position = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $username,
                    $password_hash,
                    $first_name,
                    $last_name,
                    $mobile,
                    $dob,
                    $level,
                    $color,
                    $show_in_calendar,
                    $position,
                    $id
                ]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare('
                    UPDATE therapists SET
                      username = ?, first_name = ?, last_name = ?, mobile = ?, dob = ?, level = ?, color = ?, show_in_calendar = ?, position = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $username,
                    $first_name,
                    $last_name,
                    $mobile,
                    $dob,
                    $level,
                    $color,
                    $show_in_calendar,
                    $position,
                    $id
                ]);
            }
            logAction($pdo, $_SESSION['user_id'], "Edited therapist ID {$id}: {$username}");
            $successMsg = 'Therapist updated successfully.';
        }
    }
}

// -----------------------------------------------------------------------------
// 3) Handle deletion (via GET query, with JS confirmation)
if (isset($_GET['delete_id'])) {
    $delId   = (int)$_GET['delete_id'];
    $stmtLog = $pdo->prepare('SELECT username FROM therapists WHERE id = ?');
    $stmtLog->execute([$delId]);
    if ($row = $stmtLog->fetch()) {
        $stmtDel = $pdo->prepare('DELETE FROM therapists WHERE id = ?');
        $stmtDel->execute([$delId]);
        logAction($pdo, $_SESSION['user_id'], "Deleted therapist: {$row['username']} (ID: {$delId})");
        $successMsg = 'Therapist deleted successfully.';
    } else {
        $errorMsg = 'Therapist not found.';
    }
}

// -----------------------------------------------------------------------------
// 4) Fetch all therapists for display
$stmt = $pdo->query('
  SELECT
    id,
    username,
    first_name,
    last_name,
    mobile,
    dob,
    level,
    color,
    show_in_calendar,
    position,
    created_at
  FROM therapists
  ORDER BY position ASC, first_name ASC, last_name ASC
');
$therapists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Therapists';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Page Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Therapists</h1>
        </div>
        <div class="col-sm-6 text-right">
          <!-- Add Therapist Button -->
          <button class="btn btn-success" data-toggle="modal" data-target="#addTherapistModal">
            <i class="fas fa-plus-circle"></i> Add Therapist
          </button>
          <!-- Link to Calendar View -->
          <?php if (hasPermission($pdo, 'calendar_view.view')): ?>
            <a href="/pages/calendar_view.php" class="btn btn-primary ml-2">
              <i class="fas fa-calendar-alt"></i> Calendar View
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Messages + Table -->
  <section class="content">
    <div class="container-fluid">
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-body">
          <table id="therapistsTable" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Mobile</th>
                <th>DOB</th>
                <th>Level</th>
                <th>Color</th>
                <th>Show in Calendar</th>
                <th>Position</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($therapists as $t): ?>
                <tr>
                  <td><?php echo htmlspecialchars($t['id']); ?></td>
                  <td><?php echo htmlspecialchars($t['username']); ?></td>
                  <td><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($t['mobile']); ?></td>
                  <td><?php echo htmlspecialchars($t['dob']); ?></td>
                  <td><?php echo htmlspecialchars($t['level']); ?></td>
                  <td>
                    <span style="display:inline-block;width:20px;height:20px;background:<?php echo htmlspecialchars($t['color']); ?>;border:1px solid #ccc;"></span>
                    <?php echo htmlspecialchars($t['color']); ?>
                  </td>
                  <td><?php echo $t['show_in_calendar'] ? 'Yes' : 'No'; ?></td>
                  <td><?php echo htmlspecialchars($t['position']); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($t['created_at']))); ?></td>
                  <td>
                    <!-- Edit button -->
                    <button
                      class="btn btn-sm btn-info edit-btn"
                      data-id="<?php echo $t['id']; ?>"
                      data-username="<?php echo htmlspecialchars($t['username']); ?>"
                      data-first_name="<?php echo htmlspecialchars($t['first_name']); ?>"
                      data-last_name="<?php echo htmlspecialchars($t['last_name']); ?>"
                      data-mobile="<?php echo htmlspecialchars($t['mobile']); ?>"
                      data-dob="<?php echo htmlspecialchars($t['dob']); ?>"
                      data-level="<?php echo htmlspecialchars($t['level']); ?>"
                      data-color="<?php echo htmlspecialchars($t['color']); ?>"
                      data-show_in_calendar="<?php echo $t['show_in_calendar']; ?>"
                      data-position="<?php echo htmlspecialchars($t['position']); ?>"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <!-- Delete button -->
                    <a href="?delete_id=<?php echo $t['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this therapist?');">
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

<!-- Add Therapist Modal -->
<div class="modal fade" id="addTherapistModal" tabindex="-1" aria-labelledby="addTherapistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="addTherapistForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addTherapistModalLabel">Add New Therapist</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Username -->
          <div class="form-group">
            <label for="username">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="username" class="form-control" required>
          </div>
          <!-- Password -->
          <div class="form-group">
            <label for="password">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control" required>
          </div>
          <div class="form-row">
            <!-- First Name -->
            <div class="form-group col-md-6">
              <label for="first_name">First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="first_name" class="form-control" required>
            </div>
            <!-- Last Name -->
            <div class="form-group col-md-6">
              <label for="last_name">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="last_name" class="form-control" required>
            </div>
          </div>
          <div class="form-row">
            <!-- Mobile -->
            <div class="form-group col-md-6">
              <label for="mobile">Mobile</label>
              <input type="text" name="mobile" id="mobile" class="form-control" placeholder="+123456789">
            </div>
            <!-- Date of Birth -->
            <div class="form-group col-md-6">
              <label for="dob">Date of Birth</label>
              <input type="date" name="dob" id="dob" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <!-- Level -->
            <div class="form-group col-md-4">
              <label for="level">Level <span class="text-danger">*</span></label>
              <select name="level" id="level" class="form-control" required>
                <option value="">-- Select Level --</option>
                <option value="Therapist">Therapist</option>
                <option value="Reception">Reception</option>
              </select>
            </div>
            <!-- Color -->
            <div class="form-group col-md-4">
              <label for="color">Color <span class="text-danger">*</span></label>
              <input type="color" name="color" id="color" class="form-control" value="#000000" required>
            </div>
            <!-- Show in Calendar -->
            <div class="form-group col-md-4 d-flex align-items-center">
              <div class="custom-control custom-switch mt-4">
                <input type="checkbox" class="custom-control-input" id="show_in_calendar" name="show_in_calendar" checked>
                <label class="custom-control-label" for="show_in_calendar">Show in Calendar</label>
              </div>
            </div>
          </div>
          <!-- Position -->
          <div class="form-group">
            <label for="position">Position (calendar order)</label>
            <input type="number" name="position" id="position" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_therapist" class="btn btn-primary">Save Therapist</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Therapist Modal -->
<div class="modal fade" id="editTherapistModal" tabindex="-1" aria-labelledby="editTherapistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="editTherapistForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editTherapistModalLabel">Edit Therapist</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <!-- Username -->
          <div class="form-group">
            <label for="edit_username">Username <span class="text-danger">*</span></label>
            <input type="text" name="edit_username" id="edit_username" class="form-control" required>
          </div>
          <!-- Password (leave blank to keep existing) -->
          <div class="form-group">
            <label for="edit_password">Password (leave blank to keep existing)</label>
            <input type="password" name="edit_password" id="edit_password" class="form-control">
          </div>
          <div class="form-row">
            <!-- First Name -->
            <div class="form-group col-md-6">
              <label for="edit_first_name">First Name <span class="text-danger">*</span></label>
              <input type="text" name="edit_first_name" id="edit_first_name" class="form-control" required>
            </div>
            <!-- Last Name -->
            <div class="form-group col-md-6">
              <label for="edit_last_name">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="edit_last_name" id="edit_last_name" class="form-control" required>
            </div>
          </div>
          <div class="form-row">
            <!-- Mobile -->
            <div class="form-group col-md-6">
              <label for="edit_mobile">Mobile</label>
              <input type="text" name="edit_mobile" id="edit_mobile" class="form-control">
            </div>
            <!-- Date of Birth -->
            <div class="form-group col-md-6">
              <label for="edit_dob">Date of Birth</label>
              <input type="date" name="edit_dob" id="edit_dob" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <!-- Level -->
            <div class="form-group col-md-4">
              <label for="edit_level">Level <span class="text-danger">*</span></label>
              <select name="edit_level" id="edit_level" class="form-control" required>
                <option value="">-- Select Level --</option>
                <option value="Therapist">Therapist</option>
                <option value="Reception">Reception</option>
              </select>
            </div>
            <!-- Color -->
            <div class="form-group col-md-4">
              <label for="edit_color">Color <span class="text-danger">*</span></label>
              <input type="color" name="edit_color" id="edit_color" class="form-control" value="#000000" required>
            </div>
            <!-- Show in Calendar -->
            <div class="form-group col-md-4 d-flex align-items-center">
              <div class="custom-control custom-switch mt-4">
                <input type="checkbox" class="custom-control-input" id="edit_show_in_calendar" name="edit_show_in_calendar">
                <label class="custom-control-label" for="edit_show_in_calendar">Show in Calendar</label>
              </div>
            </div>
          </div>
          <!-- Position -->
          <div class="form-group">
            <label for="edit_position">Position (calendar order)</label>
            <input type="number" name="edit_position" id="edit_position" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_therapist" class="btn btn-primary">Update Therapist</button>
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
  $('#therapistsTable').DataTable({
    "pageLength": 25,
    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
    "order": [[8, "asc"]], // order by position
    "columnDefs": [
      { "orderable": false, "targets": 10 } // disable ordering on Actions column
    ]
  });

  // When “Edit” is clicked, populate and show modal
  $('#therapistsTable').on('click', '.edit-btn', function() {
    const btn = $(this);
    $('#edit_id').val(btn.data('id'));
    $('#edit_username').val(btn.data('username'));
    $('#edit_first_name').val(btn.data('first_name'));
    $('#edit_last_name').val(btn.data('last_name'));
    $('#edit_mobile').val(btn.data('mobile'));
    $('#edit_dob').val(btn.data('dob'));
    $('#edit_level').val(btn.data('level'));
    $('#edit_color').val(btn.data('color'));
    $('#edit_show_in_calendar').prop('checked', btn.data('show_in_calendar') == 1);
    $('#edit_position').val(btn.data('position'));
    $('#editTherapistModal').modal('show');
  });
});
</script>