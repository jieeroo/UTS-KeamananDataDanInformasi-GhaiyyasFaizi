<?php
declare(strict_types=1);

define('APP_NAME', 'GameVault');
define('BASE_URL', ''); // Contoh: '/aplikasi-file-game-rahasia' jika di subfolder
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gamevault');
define('DB_USER', 'root');
define('DB_PASS', '');
define('STORAGE_PATH', dirname(__DIR__) . '/storage/encrypted');
define('KEY_PATH', dirname(__DIR__) . '/storage/keys');
define('MAX_UPLOAD_BYTES', 100 * 1024 * 1024); // 100 MB
date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
