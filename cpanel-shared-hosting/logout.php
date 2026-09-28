<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::logout();
$login_url = SLEA_Datastore::get_login_url();
header('Location: ' . $login_url);
exit;
