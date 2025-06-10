<?php
// auth.php — session management, CSRF token, and helper functions

session_start();
require_once __DIR__ . '/db.php';

// ─── CSRF Token Generation ────────────────────────────────────────────────
/*if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}*/

// ─── Authentication Helpers ────────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

// ─── Authorization Helpers ─────────────────────────────────────────────────
function hasPermission($pdo, $permission_key) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Look up this user’s role_id
    $stmt = $pdo->prepare("
      SELECT u.role_id
        FROM users AS u
       WHERE u.id = ?
       LIMIT 1
    ");
    $stmt->execute([ $_SESSION['user_id'] ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['role_id'])) {
        return false;
    }
    $roleId = (int)$row['role_id'];

    // Check role_permissions → permissions for this key
    $stmt = $pdo->prepare("
      SELECT 1
        FROM role_permissions AS rp
        JOIN permissions      AS p
          ON rp.permission_id = p.id
       WHERE rp.role_id = ?
         AND p.permission_key = ?
       LIMIT 1
    ");
    $stmt->execute([ $roleId, $permission_key ]);
    return (bool) $stmt->fetch();
}

function requirePermission($pdo, $permission_key) {
    requireLogin();
    if (!hasPermission($pdo, $permission_key)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
        exit();
    }
}

// ─── Current User & Auditing ───────────────────────────────────────────────
function currentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("
      SELECT u.*, r.role_name
        FROM users AS u
   LEFT JOIN roles AS r
          ON u.role_id = r.id
       WHERE u.id = ?
       LIMIT 1
    ");
    $stmt->execute([ $_SESSION['user_id'] ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function logAction($pdo, $user_id, $action) {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
    $stmt->execute([ $user_id, $action ]);
}