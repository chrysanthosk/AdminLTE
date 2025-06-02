<?php
// pages/user_dashboard.php — simple dashboard for non-admin users

require_once '../auth.php';
requireLogin();

// Fetch the current user again, in case session changed
$user = currentUser($pdo);

$page_title = 'User Dashboard';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>User Dashboard</h1>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <!-- A single “Welcome” box -->
      <div class="card">
        <div class="card-body">
          <h3>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h3>
          <p>
            You currently have a <strong><?php echo htmlspecialchars($user['role']); ?></strong> role.
          </p>
          <p>
            <a href="/pages/profile.php" class="btn btn-secondary">
              <i class="fas fa-user-edit"></i> Edit My Profile
            </a>
          </p>
          <hr>
          <p>No additional menu items are available for your role.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>