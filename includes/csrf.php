<?php
// includes/csrf.php — call at top of any page that accepts POSTs

// Make sure session is started before this include
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// If this is a form POST, enforce the token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1) Token must be present
  if (empty($_POST['csrf_token'])) {
    http_response_code(400);
    die('Missing CSRF token');
  }

  // 2) Token must match exactly
  if (!hash_equals(
        ($_SESSION['csrf_token'] ?? ''),
        $_POST['csrf_token']
      ))
  {
    http_response_code(400);
    die('Invalid CSRF token');
  }
}