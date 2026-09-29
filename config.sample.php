<?php
// Copy to config.php and set a long random admin_key.
// Generate one with: php -r 'echo bin2hex(random_bytes(16)), "\n";'
return [
    'admin_key' => 'change-me',
    // Where hits.jsonl lives. Keep it outside the web root.
    'data_dir'  => dirname(__DIR__) . '/muse-data',
];
