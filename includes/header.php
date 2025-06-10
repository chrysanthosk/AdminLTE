<?php
// includes/header.php — top section including CSS and Navbar
require_once __DIR__ . '/../auth.php';
//require_once __DIR__ . '/csrf.php';

$user      = currentUser($pdo);
$theme     = $user ? $user['theme'] : 'light';
$page_title = isset($page_title) ? $page_title : '';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
  <!-- AdminLTE (light) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">

  <?php if ($theme === 'dark'): ?>
    <!-- AdminLTE (dark) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <?php endif; ?>

    <!-- ─── NOW INJECT “blinking-event” CSS ─── -->
    <style>
      @keyframes blink-flash {
        0%   { opacity: 1; }
        50%  { opacity: 0.3; }
        100% { opacity: 1; }
      }
      .fc-event.blinking-event {
        animation: blink-flash 1s infinite;
      }
    .select2-container .select2-selection--single {
      background-color: #343a40 !important;
      border: 1px solid #6c757d !important;
      color: #fff !important;
      height: calc(1.5em + .75rem + 2px) !important;
    }
    .select2-container .select2-selection--single .select2-selection__rendered {
      color: #e0e0e0 !important;
      line-height: calc(1.5em + .75rem) !important;
    }
    .select2-container .select2-selection--single .select2-selection__arrow b {
      border-color: #e0e0e0 transparent transparent transparent !important;
    }
    .select2-container .select2-dropdown {
      background-color: #343a40 !important;
      border: 1px solid #6c757d !important;
      z-index: 9999 !important;  /* float above modal */
    }
    .select2-container .select2-search--dropdown .select2-search__field {
      background-color: #495057 !important;
      color: #fff !important;
      border: 1px solid #6c757d !important;
      padding: .375rem .75rem !important;
    }
    .select2-container .select2-results__option {
      color: #f8f9fa !important;
    }
    .select2-container .select2-results__option--highlighted {
      background-color: #6c757d !important;
      color: #fff !important;
    }
    .select2-container .select2-selection__placeholder {
      color: #adb5bd !important;
    }

    /* Add a small colored square for each category button */
    .category-color-box {
      display: inline-block;
      width: 12px;
      height: 12px;
      margin-right: 4px;
      vertical-align: middle;
      border: 1px solid #888;
    }
    </style>

</head>

<body class="hold-transition sidebar-mini <?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
<div class="wrapper">
  <!-- Navbar -->
  <?php if ($theme === 'dark'): ?>
    <nav class="main-header navbar navbar-expand navbar-dark">
  <?php else: ?>
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <?php endif; ?>
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <!-- Here is the role check: -->
         <?php if ($user && ($user['role_name'] ?? '') === 'admin'): ?>
          <a href="/pages/dashboard.php" class="nav-link">Admin Panel</a>
        <?php else: ?>
          <a href="/pages/dashboard.php" class="nav-link">Home</a>
        <?php endif; ?>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
    <!-- Profile Link -->
      <li class="nav-item">
        <a class="nav-link" href="/pages/profile.php" title="My Profile">
          <i class="fas fa-user"></i>
        </a>
      </li>
      <!-- Logout -->
      <li class="nav-item">
        <a class="nav-link" href="/logout.php">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->