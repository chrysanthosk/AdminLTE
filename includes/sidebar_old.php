<?php
// File: includes/sidebar.php
// Dynamic AdminLTE sidebar built from your modules table and grouped sections

require_once __DIR__ . '/../auth.php';  // ensures $pdo and hasPermission() are available

// Fetch Dashboard Name for the brand logo
try {
  $dsStmt = $pdo->query("SELECT dashboard_name FROM dashboard_settings LIMIT 1");
  $dsRow = $dsStmt->fetch(PDO::FETCH_ASSOC);
  $dashboardName = $dsRow['dashboard_name'] ?: 'Admin Panel';
} catch (Exception $e) {
  $dashboardName = 'Admin Panel';
}

// Define each sidebar section with icon, label, and its modules
$sections = [
  // Admin section
  [
    'icon'  => 'fas fa-cog',
    'label' => 'Admin',
    'items' => [
      ['perm'=>'user.manage',       'link'=>'users.php',            'text'=>'User Administration'],
      ['perm'=>'role.manage',       'link'=>'roles.php',            'text'=>'Role Management'],
      ['perm'=>'role.assign',       'link'=>'role_permissions.php', 'text'=>'Role Permissions'],
      ['perm'=>'permission.manage', 'link'=>'permissions.php',      'text'=>'Permissions'],
      ['perm'=>'module.manage',     'link'=>'modules.php',          'text'=>'Modules'],
      ['perm'=>'email.manage',      'link'=>'email_settings.php',   'text'=>'Email Settings'],
      ['perm'=>'audit.view',        'link'=>'audit_log.php',        'text'=>'Audit Log'],
    ]
  ],
  // CRM section
  [
    'icon'  => 'fas fa-users',
    'label' => 'CRM',
    'items' => [
      ['perm'=>'client.manage',        'link'=>'clients.php',        'text'=>'Clients'],
      ['perm'=>'therapists.manage',    'link'=>'therapists.php',     'text'=>'Therapists'],
      ['perm'=>'calendar_view.view',   'link'=>'calendar_view.php',  'text'=>'Calendar View'],
      ['perm'=>'appointment.manage',   'link'=>'appointments.php',   'text'=>'Appointments'],
    ]
  ],
  // Products & Pricing
  [
    'icon'  => 'fas fa-boxes',
    'label' => 'Products & Pricing',
    'items' => [
      ['perm'=>'vat.manage',                'link'=>'vat.php',                 'text'=>'VAT Types'],
      ['perm'=>'product_category.manage',   'link'=>'product_category.php',    'text'=>'Product Categories'],
      ['perm'=>'product.manage',            'link'=>'products.php',            'text'=>'Products'],
      ['perm'=>'service_category.manage',   'link'=>'service_category.php',    'text'=>'Service Categories'],
      ['perm'=>'services.manage',           'link'=>'services.php',            'text'=>'Services'],
      ['perm'=>'pricelist.manage',          'link'=>'pricelist.php',           'text'=>'Pricelist'],
      ['perm'=>'pricelist_category.manage', 'link'=>'pricelist_categories.php','text'=>'Pricelist Categories'],
    ]
  ],
  // Sales
  [
    'icon'  => 'fas fa-cash-register',
    'label' => 'Sales',
    'items' => [
      ['perm'=>'cashier.manage', 'link'=>'cashier.php', 'text'=>'Cashier'],
    ]
  ],
  // Reporting & Logs
  [
    'icon'  => 'fas fa-chart-bar',
    'label' => 'Reporting & Logs',
    'items' => [
      ['perm'=>'reports.view', 'link'=>'reports.php',     'text'=>'Reports'],
      // Audit Log already in Admin, so optional here
    ]
  ],
  // Settings & Config
  [
    'icon'  => 'fas fa-sliders-h',
    'label' => 'Settings & Config',
    'items' => [
      ['perm'=>'dash_settings.manage','link'=>'dashboard_settings.php','text'=>'Dashboard Settings'],
      ['perm'=>'email.manage',        'link'=>'email_settings.php',     'text'=>'Email Settings'],
      ['perm'=>'sms.manage',        'link'=>'sms_settings.php',     'text'=>'SMS Settings'],
    ]
  ],
];

// Helper to render a single treeview section
function renderSection($sec) {
  echo '<li class="nav-item has-treeview">';
  echo '<a href="#" class="nav-link">';
  echo '<i class="nav-icon ' . htmlspecialchars($sec['icon'], ENT_QUOTES) . '"></i>';
  echo '<p>' . htmlspecialchars($sec['label'], ENT_QUOTES) . '<i class="right fas fa-angle-left"></i></p>';
  echo '</a>';
  echo '<ul class="nav nav-treeview">';
  foreach ($sec['items'] as $it) {
    echo '<li class="nav-item">';
    echo '<a href="/pages/' . htmlspecialchars($it['link'], ENT_QUOTES) . '" class="nav-link">';
    echo '<i class="far fa-circle nav-icon"></i>';
    echo '<p>' . htmlspecialchars($it['text'], ENT_QUOTES) . '</p>';
    echo '</a></li>';
  }
  echo '</ul></li>';
}
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="/index.php" class="brand-link">
    <span class="brand-text font-weight-light"><b><?= htmlspecialchars($dashboardName, ENT_QUOTES) ?></b></span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="<?php echo hasPermission($pdo, 'dashboard.view') ? '/pages/dashboard.php' : '/pages/user_dashboard.php'; ?>"
             class="nav-link">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <?php
        // Loop through each defined section
        foreach ($sections as $sec) {
          // Filter items by permission
          $allowed = array_filter($sec['items'], function($it) use ($pdo) {
            return hasPermission($pdo, $it['perm']);
          });
          if (!empty($allowed)) {
            // Replace items with allowed subset
            $sec['items'] = $allowed;
            renderSection($sec);
          }
        }
        ?>
      </ul>
    </nav>
  </div>
</aside>