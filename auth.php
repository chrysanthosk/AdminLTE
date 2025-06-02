<?php
// auth.php - session management and helper functions
session_start();
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: login.php');
        exit();
    }
}

function currentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function logAction($pdo, $user_id, $action) {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action) VALUES (?, ?)');
    $stmt->execute([$user_id, $action]);
}
?>