<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM game_files WHERE id=?'); $stmt->execute([$id]); $file = $stmt->fetch();
if (!$file) { http_response_code(404); exit('File tidak ditemukan.'); }
$cipher = STORAGE_PATH . '/' . basename($file['stored_name']);
if (!is_file($cipher)) { http_response_code(404); exit('Data terenkripsi tidak ditemukan.'); }
$tmp = tempnam(sys_get_temp_dir(), 'gv_');
try {
    decrypt_file_hybrid($cipher, $tmp);
    $name = str_replace(["\r","\n",'"'], '', basename($file['original_name']));
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
} catch (Throwable $e) {
    http_response_code(500); echo 'Gagal mendekripsi file: ' . e($e->getMessage());
} finally { if (is_file($tmp)) unlink($tmp); }
