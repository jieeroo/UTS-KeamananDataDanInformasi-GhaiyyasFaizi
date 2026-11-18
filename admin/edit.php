<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM game_files WHERE id=?'); $stmt->execute([$id]); $file = $stmt->fetch();
if (!$file) { http_response_code(404); exit('File tidak ditemukan.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? '');
    if ($title === '') $error = 'Judul wajib diisi.';
    else { $u = db()->prepare('UPDATE game_files SET title=?, description=? WHERE id=?'); $u->execute([$title,$description,$id]); flash('Metadata file berhasil diperbarui.'); header('Location: files.php'); exit; }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit · GameVault</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="form-page"><a class="text-link" href="files.php">← Kembali ke file</a><section class="panel form-panel"><p class="eyebrow">EDIT METADATA</p><h1>Edit informasi file</h1><p class="muted">Isi file terenkripsi tidak berubah; yang diperbarui hanya metadata.</p><?php if($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Judul</label><input name="title" required value="<?=e($_POST['title']??$file['title'])?>"><label>Deskripsi</label><textarea name="description" rows="4"><?=e($_POST['description']??$file['description'])?></textarea><button class="btn primary full">Simpan perubahan</button></form></section></main></body></html>
