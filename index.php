<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /faculty/dashboard.php');
    }
} else {
    header('Location: /login.php');
}
exit;
