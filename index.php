<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
redirect(is_logged_in() ? 'modules/dashboard/index.php' : 'login.php');
