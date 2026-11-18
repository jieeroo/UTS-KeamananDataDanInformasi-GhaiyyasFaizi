<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit('Pratinjau tidak ditemukan.');
}

$stmt = db()->prepare('SELECT stored_name, mime_type FROM game_files WHERE id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file || !in_array($file['mime_type'], ['image/png', 'image/jpeg'], true)) {
    http_response_code(404);
    exit('Pratinjau tidak tersedia.');
}

$cipher = STORAGE_PATH . '/' . basename($file['stored_name']);
if (!is_file($cipher)) {
    http_response_code(404);
    exit('Data terenkripsi tidak ditemukan.');
}

$tmp = tempnam(sys_get_temp_dir(), 'gv_preview_');
if ($tmp === false) {
    http_response_code(500);
    exit('Gagal menyiapkan pratinjau.');
}

try {
    decrypt_file_hybrid($cipher, $tmp);
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: inline');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
} catch (Throwable $e) {
    error_log('GameVault image preview failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Gagal menampilkan pratinjau.';
} finally {
    if (is_file($tmp)) unlink($tmp);
}
