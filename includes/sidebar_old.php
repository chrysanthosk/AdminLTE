<?php
// includes/sidebar.php — side navigation menu

require_once __DIR__ . '/../auth.php';
$user = currentUser($pdo);

try {
  $dsStmt = $pdo->query("SELECT dashboard_name FROM dashboard_settings LIMIT 1");
  $dsRow = $dsStmt->fetch(PDO::FETCH_ASSOC);
  $dashboardName = $dsRow['dashboard_name'] ?: 'Admin Panel';
} catch (Exception $e) {
  // Fallback if table/column missing
  $dashboardName = 'Admin Panel';
}

?>
<!-- Main Sidebar Container -->
<?php if ($user && $user['theme'] === 'dark'): ?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
<?php else: ?>
  <aside class="main-sidebar sidebar-light-primary elevation-4">
<?php endif; ?>

    <!-- Brand Logo -->
    <a href="/index.php" class="brand-link">
      <span class="brand-text font-weight-light"><b><?= htmlspecialchars($dashboardName, ENT_QUOTES, 'UTF-8') ?></b></span>
    </a>

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
        <!-- Dashboard link (visible to all logged‐in users) -->
        <li class="nav-item">
          <a href="<?php echo hasPermission($pdo, 'dashboard.view') ? '/pages/dashboard.php' : '/pages/user_dashboard.php'; ?>"
             class="nav-link">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <!-- Admin section (existing code) -->
        <?php if (
               hasPermission($pdo, 'user.manage') ||
               hasPermission($pdo, 'role.manage') ||
               hasPermission($pdo, 'role.assign') ||
               hasPermission($pdo, 'permission.manage') ||
               hasPermission($pdo, 'module.manage') ||
               hasPermission($pdo, 'email.manage') ||
               hasPermission($pdo, 'audit.view')
           ): ?>
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

              <?php if (hasPermission($pdo, 'module.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/modules.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Modules</p>
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
              <?php if (hasPermission($pdo, 'therapists.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/therapists.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Therapists</p>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
        <!--Therapists Settings-->

        <!-- Accounting Section-->
        <?php if (hasPermission($pdo, 'vat.manage')): ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-boxes"></i>
              <p>
                Products
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <?php if (hasPermission($pdo, 'vat.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/vat.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>VAT Types</p>
                  </a>
                </li>
              <?php endif; ?>
              <?php if (hasPermission($pdo, 'product_category.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/product_category.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Categories</p>
                  </a>
                </li>
              <?php endif; ?>
              <?php if (hasPermission($pdo, 'product.manage')): ?>
                <li class="nav-item">
                  <a href="/pages/products.php" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Products</p>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
        <!-- Service Section-->
                <?php if (hasPermission($pdo, 'service_category.manage')): ?>
                  <li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                      <i class="nav-icon fas fa-boxes"></i>
                      <p>
                        Services
                        <i class="right fas fa-angle-left"></i>
                      </p>
                    </a>
                    <ul class="nav nav-treeview">
                      <?php if (hasPermission($pdo, 'service_category.manage')): ?>
                        <li class="nav-item">
                          <a href="/pages/service_category.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Services Category</p>
                          </a>
                        </li>
                      <?php endif; ?>
                      <?php if (hasPermission($pdo, 'services.manage')): ?>
                        <li class="nav-item">
                          <a href="/pages/services.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Services</p>
                          </a>
                        </li>
                      <?php endif; ?>
                      <?php if (hasPermission($pdo, 'pricelist.manage')): ?>
                        <li class="nav-item">
                          <a href="/pages/pricelist.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>PriceList</p>
                          </a>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </li>
                <?php endif; ?>
        <!-- Client Management section -->
        <?php if (hasPermission($pdo, 'client.manage')): ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-user-friends"></i>
              <p>
                Client Management
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="/pages/clients.php" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Clients</p>
                </a>
              </li>
            </ul>
          </li>
        <?php endif; ?>

        <!-- (other sidebar items…) -->
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>