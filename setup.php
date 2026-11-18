<?php
require_once __DIR__ . '/includes/bootstrap.php';
$count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$message = '';
if ($count > 0) {
    http_response_code(403);
    exit('Setup ditutup: akun admin sudah ada. Hapus file setup.php sekarang.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_.-]{4,60}$/', $username)) $message = 'Username 4-60 karakter (huruf, angka, titik, _ atau -).';
    elseif (strlen($password) < 12) $message = 'Password minimal 12 karakter.';
    else {
        $stmt = db()->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?)');
        $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
        exit('Admin berhasil dibuat. Hapus setup.php sekarang, lalu login melalui /admin/login.php.');
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup GameVault</title><link rel="stylesheet" href="assets/style.css"></head><body class="login-page"><main class="login-card"><img class="brand-mark brand-logo-image" src="assets/gamevault-logo.jpg" alt="GameVault"><p class="eyebrow">INITIAL SETUP</p><h1>Buat admin pertama</h1><p class="muted">Halaman ini hanya aktif jika belum ada akun admin.</p><?php if($message): ?><div class="alert danger"><?=e($message)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Username</label><input name="username" required minlength="4"><label>Password (minimal 12 karakter)</label><input type="password" name="password" required minlength="12"><button class="btn primary full">Buat akun admin</button></form></main></body></html>
