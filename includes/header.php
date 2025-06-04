<?php
// includes/header.php — top section including CSS and Navbar
require_once __DIR__ . '/../auth.php';

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
        <?php if ($user && $user['role'] === 'admin'): ?>
          <a href="/pages/dashboard.php" class="nav-link">Admin Panel</a>
        <?php else: ?>
          <a href="/pages/dashboard.php" class="nav-link">Home</a>
        <?php endif; ?>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a class="nav-link" href="/logout.php">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->