<?php
// pages/dashboard.php — data‐driven, permission‐based dashboard

require_once '../auth.php';
requireLogin();

// Fetch current user (for greetings later)
$user = currentUser($pdo);

// 1) Query all active modules, sorted
$stmt = $pdo->prepare('
  SELECT id, title, description, icon_class, box_color, link, permission_key
  FROM modules
  WHERE is_active = TRUE
  ORDER BY sort_order
');
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Dashboard';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Dashboard</h1>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <?php
          $showedBox = false;

          foreach ($modules as $mod) {
            // 2) Check if user has permission for this module
            if (hasPermission($pdo, $mod['permission_key'])) {
              $showedBox = true;
              ?>
              <div class="col-lg-3 col-6">
                <div class="small-box <?php echo htmlspecialchars($mod['box_color']); ?>">
                  <div class="inner">
                    <h3><?php echo htmlspecialchars($mod['title']); ?></h3>
                    <p><?php echo htmlspecialchars($mod['description']); ?></p>
                  </div>
                  <div class="icon">
                    <i class="<?php echo htmlspecialchars($mod['icon_class']); ?>"></i>
                  </div>
                  <a href="<?php echo htmlspecialchars($mod['link']); ?>" class="small-box-footer">
                    Open <i class="fas fa-arrow-circle-right"></i>
                  </a>
                </div>
              </div>
              <?php
            }
          }

          // 3) If no boxes rendered, show a simple welcome card
          if (!$showedBox): ?>
            <div class="col-12">
              <div class="card card-outline card-info">
                <div class="card-header">
                  <h5 class="card-title">Welcome</h5>
                </div>
                <div class="card-body">
                  <p>Hello, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
                  <p>Your role is: <strong><?php echo htmlspecialchars($user['role']); ?></strong>.</p>
                  <p>You do not have any dashboard modules. You can <a href="profile.php">edit your profile</a> here.</p>
                </div>
              </div>
            </div>
        <?php endif; ?>
      </div> <!-- /.row -->
    </div> <!-- /.container-fluid -->
  </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php include '../includes/footer.php'; ?>