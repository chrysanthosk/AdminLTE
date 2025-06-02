<?php
// logout.php
require_once 'auth.php';
logAction($pdo, $_SESSION['user_id'], 'Logged out');
session_unset();
session_destroy();
header('Location: login.php');
exit();
?>