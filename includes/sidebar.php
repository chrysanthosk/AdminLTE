<?php
// includes/sidebar.php — Dynamic AdminLTE sidebar from DB

require_once __DIR__ . '/../auth.php';  // gives $pdo and hasPermission()

// Brand / Dashboard name
try {
  $dsRow = $pdo
    ->query("SELECT dashboard_name FROM dashboard_settings LIMIT 1")
    ->fetch(PDO::FETCH_ASSOC);
  $dashboardName = $dsRow['dashboard_name'] ?: 'Admin Panel';
} catch (Exception $e) {
  $dashboardName = 'Admin Panel';
}

// 1) Fetch all active sections
$secs = $pdo
  ->query("
    SELECT id, section_key, label, icon_class
      FROM menu_sections
     WHERE is_active = 1
     ORDER BY sort_order
  ")
  ->fetchAll(PDO::FETCH_ASSOC);
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="/index.php" class="brand-link">
    <span class="brand-text font-weight-light">
      <b><?= htmlspecialchars($dashboardName, ENT_QUOTES) ?></b>
    </span>
  </a>

  <div class="sidebar">
    <nav class="mt-2">
      <ul
        class="nav nav-pills nav-sidebar flex-column"
        data-widget="treeview"
        role="menu"
        data-accordion="false"
      >
        <!-- Dashboard Link -->
        <li class="nav-item">
          <a
            href="<?= hasPermission($pdo, 'dashboard.view') ? '/pages/dashboard.php' : '/pages/user_dashboard.php' ?>"
            class="nav-link"
          >
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <?php foreach ($secs as $sec): ?>
          <?php
            // 2) Fetch modules in this section
            $mods = $pdo->prepare("
              SELECT title, link, icon_class, box_color, permission_key
                FROM modules
               WHERE is_active = 1
                 AND section_id   = :sid
               ORDER BY sort_order
            ");
            $mods->execute(['sid' => $sec['id']]);
            $all = $mods->fetchAll(PDO::FETCH_ASSOC);

            // 3) Filter by permission
            $allowed = array_filter($all, fn($m) =>
              hasPermission($pdo, $m['permission_key'])
            );
            if (empty($allowed)) continue;
          ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon <?= htmlspecialchars($sec['icon_class'], ENT_QUOTES) ?>"></i>
              <p>
                <?= htmlspecialchars($sec['label'], ENT_QUOTES) ?>
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <?php foreach ($allowed as $m): ?>
                <li class="nav-item">
                  <a href="/pages/<?= htmlspecialchars($m['link'], ENT_QUOTES) ?>" class="nav-link">
                    <i class="nav-icon <?= htmlspecialchars($m['icon_class'], ENT_QUOTES) ?>
                                   <?= htmlspecialchars($m['box_color'],  ENT_QUOTES) ?>"></i>
                    <p><?= htmlspecialchars($m['title'], ENT_QUOTES) ?></p>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</aside>