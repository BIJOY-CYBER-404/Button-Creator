<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-auth.php';

SLEA_Auth::logout();
header('Location: login.php');
exit;
