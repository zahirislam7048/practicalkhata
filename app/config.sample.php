<?php
// Copy this file to config.php (install.php does it for you) and fill in your cPanel MySQL details.
// config.php is NOT in Git, so deploys never overwrite it.
return [
    'db_host'  => 'localhost',
    'db_name'  => 'cpaneluser_khata',
    'db_user'  => 'cpaneluser_khata',
    'db_pass'  => '',
    'base_url' => '',          // '' when the site is at the domain root, e.g. https://practicalkhata.com
    'debug'    => false,       // true shows PHP errors — keep false on the live site
];
