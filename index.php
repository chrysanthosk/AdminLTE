<?php
// index.php - redirect to login or dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>