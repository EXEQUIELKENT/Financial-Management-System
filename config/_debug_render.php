<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/config.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/modules/dashboard/index.php';
$_SERVER['SCRIPT_NAME'] = '/modules/dashboard/index.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['full_name'] = 'Admin';
$_SESSION['role_id'] = 1;
$_SESSION['role_name'] = 'Admin';
$_SESSION['permissions'] = [];
$_SESSION['last_activity'] = time();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/dashboard/index.php';
