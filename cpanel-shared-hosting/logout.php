<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::logout();
$login_url = SLEA_Datastore::get_login_url();
$login_url = preg_replace('/[^a-zA-Z0-9_.\-]/', '', basename((string)$login_url)) ?: 'login.php';
header('Location: ' . $login_url);
exit;
