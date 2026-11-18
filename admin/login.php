<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]); $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: dashboard.php'); exit;
    }
    $error = 'Username atau password tidak sesuai.';
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page auth-page">
    <main class="auth-layout">
        <section class="auth-showcase" aria-label="GameVault">
            <div class="auth-background-grid"></div>
            <div class="auth-brand">
                <img src="../assets/gamevault-logo.jpg" alt="Logo GameVault">
                <div>
                    <h1>GameVault</h1>
                    <p>Private Game File Storage</p>
                </div>
            </div>
            <p class="auth-tagline">Secure. Encrypted. Yours.</p>
            <p class="auth-description">Simpan file game favorit Anda<br> dengan keamanan tingkat tinggi.</p>
        </section>

        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-lock" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <rect x="5" y="13" width="22" height="16" rx="3" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M10 13V9a6 6 0 0 1 12 0v4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="16" cy="20" r="2" fill="currentColor"/>
                    <path d="M16 22v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h2 id="login-title">Login Admin</h2>
            <p class="auth-card-subtitle">Masuk untuk mengakses panel admin</p>

            <?php if ($error): ?><div class="auth-error" role="alert"><?=e($error)?></div><?php endif; ?>

            <form class="auth-form" method="post">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <label class="auth-field">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="6" r="3"/><path d="M4.5 17v-1.5a5.5 5.5 0 0 1 11 0V17Z"/></svg>
                    <span class="sr-only">Username</span>
                    <input name="username" required autocomplete="username" placeholder="Username">
                </label>
                <label class="auth-field">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M7 8V5a3 3 0 0 1 6 0v3m-3 4v2"/></svg>
                    <span class="sr-only">Password</span>
                    <input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Password">
                    <button class="password-toggle" type="button" aria-label="Tampilkan password" aria-controls="login-password" aria-pressed="false" data-password-toggle>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 10s2.7-5 8-5 8 5 8 5-2.7 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/><path d="m3 3 14 14"/></svg>
                    </button>
                </label>
                <button class="auth-submit" type="submit">Masuk</button>
            </form>

            <div class="auth-security-divider"><span>Keamanan dengan</span></div>
            <div class="auth-security-items">
                <div class="auth-security-item">
                    <span class="auth-security-icon aes-icon" aria-hidden="true">▣</span>
                    <span><strong>AES-256</strong><small>Simetrik</small></span>
                </div>
                <div class="auth-security-item">
                    <span class="auth-security-icon rsa-icon" aria-hidden="true">◆</span>
                    <span><strong>RSA-3072</strong><small>Asimetrik</small></span>
                </div>
            </div>
        </section>
    </main>
    <script>
        const passwordToggle = document.querySelector('[data-password-toggle]');
        const passwordInput = document.getElementById('login-password');
        passwordToggle.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.classList.toggle('is-visible', showPassword);
            passwordToggle.setAttribute('aria-pressed', String(showPassword));
            passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');
        });
    </script>
</body>
</html>
