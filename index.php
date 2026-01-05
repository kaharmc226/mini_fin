<?php
require_once __DIR__ . '/helpers.php';
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
header('Location: login.php');
exit;
