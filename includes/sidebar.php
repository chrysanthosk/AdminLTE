<?php
// includes/sidebar.php — side navigation menu

$user = currentUser($pdo);
?>
<!-- Main Sidebar Container -->
<?php if ($user && $user['theme'] === 'dark'): ?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
<?php else: ?>
  <aside class="main-sidebar sidebar-light-primary elevation-4">
<?php endif; ?>

 <!-- Brand Logo (dynamic based on role) -->
   <?php if ($user && $user['role'] === 'admin'): ?>
     <a href="/pages/dashboard.php" class="brand-link">
       <span class="brand-text font-weight-light"><b>Admin</b>Panel</span>
     </a>
   <?php else: ?>
     <a href="/pages/user_dashboard.php" class="brand-link">
       <span class="brand-text font-weight-light"><b>User</b> Home</span>
     </a>
   <?php endif; ?>

  <!-- Sidebar -->
  <div class="sidebar">
    <?php if ($user): ?>
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="info">
          <a href="/pages/profile.php" class="d-block">
            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
        <!-- Dashboard (visible to all logged-in users) -->
        <li class="nav-item">
          <a href="<?php echo hasPermission($pdo, 'dashboard.view') ? '/pages/dashboard.php' : '/pages/user_dashboard.php'; ?>" class="nav-link">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <?php if (hasPermission($pdo, 'user.manage') ||
                  hasPermission($pdo, 'role.manage') ||
                  hasPermission($pdo, 'role.assign') ||
                  hasPermission($pdo, 'email.manage') ||
                  hasPermission($pdo, 'audit.view') ||
                  hasPermission($pdo, 'permission.manage')): ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-cog"></i>
              <p>
                Admin
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <?php if (hasPermission($pdo, 'user.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/users.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>User Administration</p>
                  </a>
                </li>
              <?php endif; ?>

              <?php if (hasPermission($pdo, 'role.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/roles.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Role Management</p>
                  </a>
                </li>
              <?php endif; ?>

              <?php if (hasPermission($pdo, 'role.assign')): ?>
                <li class="nav-item">
                  <a href="/pages/role_permissions.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Role Permissions</p>
                  </a>
                </li>
              <?php endif; ?>

              <?php if (hasPermission($pdo, 'permission.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/permissions.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Permissions</p>
                  </a>
                </li>
              <?php endif; ?>

              <?php if (hasPermission($pdo, 'email.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/email_settings.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Email Settings</p>
                  </a>
                </li>
              <?php endif; ?>

              <?php if (hasPermission($pdo, 'audit.view')): ?>
                <li class="nav-item">
                  <a href="/pages/audit_log.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Audit Log</p>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>