<?php
define('APP_TITLE', 'Intelligent Integrated Financial Management System');
define('APP_FULL_TITLE', 'Design and Development of an Intelligent Integrated Financial Management System with AI Financial Assistance, Predictive Analysis, and Decision Support for Travel and Tour Agencies');
define('APP_SHORT_NAME', 'TravelCore FMS');
define('APP_TAGLINE', 'Travel & Tours');

define('DB_HOST', 'localhost');
define('DB_NAME', 'travelcore_fms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('Asia/Manila');

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$root = str_replace('\\', '/', dirname(dirname(__FILE__)));
$docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$folder = trim(str_replace($docRoot, '', $root), '/');
define('BASE_URL', '/' . $folder);

define('CURRENCY_SYMBOL', '₱');
define('IDLE_TIMEOUT_SECONDS', 1800);
