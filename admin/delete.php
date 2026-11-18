<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT stored_name FROM game_files WHERE id=?'); $stmt->execute([$id]); $file = $stmt->fetch();
if ($file) {
    $del = db()->prepare('DELETE FROM game_files WHERE id=?'); $del->execute([$id]);
    $path = STORAGE_PATH . '/' . basename($file['stored_name']); if (is_file($path)) unlink($path);
    flash('File berhasil dihapus permanen.');
}
header('Location: files.php'); exit;
