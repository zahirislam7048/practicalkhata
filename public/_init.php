<?php
define('PUBLIC_DIR', __DIR__);

// Finds the app folder: on cPanel it is ~/practicalkhata_app (outside public_html),
// in the repository it is ../app.
foreach ([dirname(__DIR__) . '/practicalkhata_app', dirname(__DIR__) . '/app'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        return;
    }
}
http_response_code(500);
exit('App folder not found. Check the .cpanel.yml deployment.');
