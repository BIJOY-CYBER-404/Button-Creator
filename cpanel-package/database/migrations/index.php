<?php
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php') {
    http_response_code(403);
    exit('Forbidden');
}
return function($pdo) {};
