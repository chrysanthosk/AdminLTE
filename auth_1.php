<?php
// auth.php — session management and helper functions

session_start();
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// NEW: Check if current user has a given permission_key
function hasPermission($pdo, $permission_key) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    // Find the user’s role_id
    $stmt = $pdo->prepare("
        SELECT r.id AS role_id
        FROM users u
        JOIN roles r ON u.role = r.role_name
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $role_id = $row['role_id'];

    // Now check role_permissions → permissions.id for this key
    $stmt = $pdo->prepare("
        SELECT 1
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.permission_key = ?
        LIMIT 1
    ");
    $stmt->execute([$role_id, $permission_key]);
    return (bool) $stmt->fetch();
}

// NEW: Require that user is logged in; if not, redirect to login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

// NEW: Require that user has a specific permission; else 403
function requirePermission($pdo, $permission_key) {
    requireLogin();
    if (!hasPermission($pdo, $permission_key)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>";
        exit();
    }
}

// Helper to fetch full user record
function currentUser($pdo) {
  if (!isset($_SESSION['user_id'])) {
    return null;
  }
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  return $stmt->fetch();
}

function logAction($pdo, $user_id, $action) {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
    $stmt->execute([$user_id, $action]);
}