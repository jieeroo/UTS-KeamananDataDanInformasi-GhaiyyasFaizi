<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$tabInput = $_GET['tab'] ?? 'security';
$allowedTabs = ['security', 'database', 'about'];
if (!is_string($tabInput) || !in_array($tabInput, $allowedTabs, true)) {
    http_response_code(400);
    exit('Tab pengaturan tidak valid.');
}
$tab = $tabInput;
$error = '';
$databaseError = '';
$databaseVersion = '';
$fileCount = 0;
$textCount = 0;
$recordCountsAvailable = false;

try {
    $pdo = db();
    $pdo->query('SELECT 1')->fetchColumn();
    $databaseVersion = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $fileCount = (int)$pdo->query('SELECT COUNT(*) FROM game_files')->fetchColumn();
    $textCount = (int)$pdo->query('SELECT COUNT(*) FROM encrypted_texts')->fetchColumn();
    $recordCountsAvailable = true;
} catch (Throwable $e) {
    error_log('GameVault settings database check failed: ' . $e->getMessage());
    $databaseError = 'Koneksi atau skema database tidak dapat diverifikasi.';
}

$privatePath = KEY_PATH . '/private.pem';
$publicPath = KEY_PATH . '/public.pem';
$privateExists = is_file($privatePath);
$publicExists = is_file($publicPath);
$keyStatus = 'missing';
$keyBits = 0;

if ($privateExists && $publicExists) {
    $privatePem = file_get_contents($privatePath);
    $publicPem = file_get_contents($publicPath);
    $privateKey = $privatePem !== false ? openssl_pkey_get_private($privatePem) : false;
    $publicKey = $publicPem !== false ? openssl_pkey_get_public($publicPem) : false;
    $privateDetails = $privateKey ? openssl_pkey_get_details($privateKey) : false;
    $publicDetails = $publicKey ? openssl_pkey_get_details($publicKey) : false;

    if ($privateDetails && $publicDetails && isset($privateDetails['rsa']['n'], $privateDetails['rsa']['e'], $publicDetails['rsa']['n'], $publicDetails['rsa']['e'])) {
        $keyBits = (int)$privateDetails['bits'];
        $keysMatch = hash_equals($privateDetails['rsa']['n'], $publicDetails['rsa']['n'])
            && hash_equals($privateDetails['rsa']['e'], $publicDetails['rsa']['e']);
        $keyStatus = $keysMatch && $keyBits >= 3072 ? 'active' : 'invalid';
    } else {
        $keyStatus = 'invalid';
    }
} elseif ($privateExists || $publicExists) {
    $keyStatus = 'incomplete';
}

$storageHasEncryptedFiles = false;
$storageScanFailed = false;
if (is_dir(STORAGE_PATH)) {
    try {
        foreach (new DirectoryIterator(STORAGE_PATH) as $storageItem) {
            if ($storageItem->isFile() && strtolower($storageItem->getExtension()) === 'gvlt') {
                $storageHasEncryptedFiles = true;
                break;
            }
        }
    } catch (UnexpectedValueException $e) {
        error_log('GameVault settings storage check failed: ' . $e->getMessage());
        $storageScanFailed = true;
    }
}

$canGenerateKeys = $keyStatus === 'active'
    || ($keyStatus === 'missing'
        && $recordCountsAvailable
        && $fileCount === 0
        && $textCount === 0
        && !$storageHasEncryptedFiles
        && !$storageScanFailed);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action !== 'generate_keys') {
        $error = 'Aksi pengaturan tidak dikenali.';
    } elseif (!$canGenerateKeys) {
        $error = 'Kunci tidak dapat dibuat atau dirotasi saat ini. Status pasangan kunci dan penyimpanan perlu diperiksa.';
    } else {
        try {
            rotate_rsa_keys();
            if (!is_file($privatePath) || !is_file($publicPath)) {
                throw new RuntimeException('Pasangan kunci tidak tersimpan dengan lengkap.');
            }
            flash('Pasangan kunci RSA baru berhasil dibuat. Kunci sebelumnya diarsipkan agar data lama tetap dapat dibuka.');
            header('Location: settings.php?tab=security');
            exit;
        } catch (Throwable $e) {
            error_log('GameVault settings RSA key generation failed: ' . $e->getMessage());
            $error = 'Kunci RSA gagal dibuat atau dirotasi. Periksa konfigurasi OpenSSL, izin folder storage, dan log PHP.';
        }
    }
}

$flash = get_flash();
$tabLabels = [
    'security' => 'Keamanan',
    'database' => 'Database',
    'about' => 'Informasi Aplikasi',
];
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pengaturan Sistem · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="settings-page">
    <aside class="dashboard-sidebar">
        <a class="dashboard-brand" href="dashboard.php"><img class="gamepad-mark" src="../assets/gamevault-logo.jpg" alt=""><span>GameVault</span></a>
        <nav aria-label="Navigasi utama">
            <a href="dashboard.php"><span class="nav-icon">⌂</span><span class="nav-label">Dashboard</span></a>
            <a href="files.php"><span class="nav-icon">▤</span><span class="nav-label">Kelola File</span></a>
            <a href="texts.php"><span class="nav-icon">▧</span><span class="nav-label">Enkripsi Teks</span></a>
            <a class="active" href="settings.php"><span class="nav-icon">⚙</span><span class="nav-label">Pengaturan</span></a>
        </nav>
        <div class="dashboard-sidebar-bottom">
            <div class="sidebar-user">
                <span class="dashboard-avatar"><?=e(strtoupper(substr($_SESSION['admin_username'], 0, 1)))?></span>
                <span class="sidebar-user-copy"><?=e($_SESSION['admin_username'])?><small>Administrator</small></span>
            </div>
            <a class="dashboard-logout" href="logout.php"><span>↪</span><span class="nav-label">Keluar</span></a>
        </div>
    </aside>

    <main class="settings-main">
        <header class="settings-header">
            <div>
                <p class="settings-eyebrow">SYSTEM CONTROL</p>
                <h1>Pengaturan Sistem</h1>
                <p>Kelola keamanan dan konfigurasi aplikasi.</p>
            </div>
            <span class="settings-app-badge">GAMEVAULT · ADMIN</span>
        </header>

        <?php if ($flash): ?><div class="settings-alert success"><?=e($flash['message'])?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="settings-alert danger" role="alert"><?=e($error)?></div><?php endif; ?>

        <nav class="settings-tabs" aria-label="Kategori pengaturan">
            <?php foreach ($tabLabels as $tabKey => $label): ?>
                <a class="<?=$tab === $tabKey ? 'active' : ''?>" href="settings.php?tab=<?=e($tabKey)?>" <?=$tab === $tabKey ? 'aria-current="page"' : ''?>><?=e($label)?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tab === 'security'): ?>
            <section class="settings-security-grid" aria-label="Pengaturan keamanan">
                <article class="settings-card security-status-card">
                    <span class="settings-card-icon shield">✓</span>
                    <p class="settings-card-eyebrow">STATUS ENKRIPSI</p>
                    <h2><?=$keyStatus === 'active' ? 'Perlindungan Aktif' : ($keyStatus === 'missing' ? 'Kunci Belum Dibuat' : 'Perlu Pemeriksaan')?></h2>
                    <span class="settings-status-pill <?=$keyStatus === 'active' ? 'active' : 'warning'?>">
                        <?=$keyStatus === 'active' ? 'Aktif' : ($keyStatus === 'missing' ? 'Belum aktif' : 'Periksa kunci')?>
                    </span>
                    <p>File dilindungi AES-256-GCM. Kunci AES dibungkus RSA-3072-OAEP.</p>
                    <?php if ($keyStatus === 'active'): ?><small>Pasangan RSA-<?=$keyBits?> terpasang dan cocok.</small><?php endif; ?>
                </article>

                <article class="settings-card key-management-card">
                    <span class="settings-card-icon key">⌑</span>
                    <p class="settings-card-eyebrow">MANAJEMEN KUNCI</p>
                    <h2>Pasangan Kunci RSA</h2>
                    <p class="settings-card-description">Kunci privat diperlukan untuk membuka file dan teks terenkripsi.</p>
                    <?php if ($keyStatus === 'active'): ?>
                        <form method="post" action="settings.php?tab=security" onsubmit="return confirm('Buat pasangan kunci RSA baru? Kunci lama akan diarsipkan agar data terenkripsi lama tetap dapat dibuka.')">
                            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="generate_keys">
                            <button class="settings-key-button" type="submit">Generate Key Baru</button>
                        </form>
                        <p class="settings-safety-note">Kunci sebelumnya diarsipkan dan tetap digunakan untuk membuka data lama.</p>
                    <?php elseif ($canGenerateKeys): ?>
                        <form method="post" action="settings.php?tab=security" onsubmit="return confirm('Buat pasangan kunci RSA untuk instalasi ini?')">
                            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="action" value="generate_keys">
                            <button class="settings-key-button" type="submit">Generate Key Baru</button>
                        </form>
                        <p class="settings-safety-note">Hanya tersedia karena belum ada kunci atau data terenkripsi.</p>
                    <?php else: ?>
                        <button class="settings-key-button" type="button" disabled>Generate Key Baru</button>
                        <p class="settings-safety-note warning">Pembuatan dinonaktifkan sampai status kunci, database, dan storage aman diverifikasi.</p>
                    <?php endif; ?>
                </article>

                <article class="settings-card settings-crypto-card">
                    <div class="settings-crypto-copy">
                        <p class="settings-card-eyebrow">LAPISAN PERLINDUNGAN</p>
                        <h2>Enkripsi Hybrid</h2>
                        <p>AES mengenkripsi isi data; RSA melindungi kunci AES. Tag autentikasi GCM diperiksa saat dekripsi.</p>
                    </div>
                    <div class="settings-shield-illustration" aria-hidden="true">
                        <svg viewBox="0 0 100 112"><path d="M50 5 88 20v29c0 27-16 45-38 58C28 94 12 76 12 49V20L50 5Z"/><rect x="34" y="46" width="32" height="27" rx="5"/><path d="M41 46v-8a9 9 0 0 1 18 0v8m-9 10v7"/></svg>
                    </div>
                </article>
            </section>
        <?php elseif ($tab === 'database'): ?>
            <section class="settings-card settings-information-card" aria-label="Informasi database">
                <div class="settings-section-heading">
                    <div><p class="settings-card-eyebrow">KONEKSI PENYIMPANAN</p><h2>Status Database</h2></div>
                    <span class="settings-status-pill <?=$databaseError === '' ? 'active' : 'warning'?>"><?=$databaseError === '' ? 'Terhubung' : 'Periksa koneksi'?></span>
                </div>
                <?php if ($databaseError !== ''): ?><p class="settings-inline-error"><?=e($databaseError)?></p><?php endif; ?>
                <dl class="settings-details">
                    <div><dt>Engine</dt><dd>MySQL</dd></div>
                    <div><dt>Host</dt><dd><?=e(DB_HOST)?></dd></div>
                    <div><dt>Database</dt><dd><?=e(DB_NAME)?></dd></div>
                    <div><dt>Pengguna</dt><dd><?=e(DB_USER)?></dd></div>
                    <div><dt>Versi server</dt><dd><?=e($databaseVersion !== '' ? $databaseVersion : 'Tidak tersedia')?></dd></div>
                    <div><dt>Koneksi</dt><dd><?=e($databaseError === '' ? 'Berhasil diuji' : 'Gagal diverifikasi')?></dd></div>
                </dl>
                <p class="settings-safety-note">Kata sandi database tidak ditampilkan pada halaman ini.</p>
            </section>
        <?php else: ?>
            <section class="settings-card settings-information-card" aria-label="Informasi aplikasi">
                <div class="settings-section-heading">
                    <div><p class="settings-card-eyebrow">INFORMASI APLIKASI</p><h2>GameVault</h2></div>
                    <span class="settings-app-badge">ADMIN PANEL</span>
                </div>
                <dl class="settings-details">
                    <div><dt>Platform</dt><dd>PHP Native</dd></div>
                    <div><dt>Runtime PHP</dt><dd><?=e(PHP_VERSION)?></dd></div>
                    <div><dt>Database</dt><dd>MySQL <?=e($databaseVersion !== '' ? $databaseVersion : '(tidak terhubung)')?></dd></div>
                    <div><dt>Ukuran upload maksimum</dt><dd><?=e(format_bytes(MAX_UPLOAD_BYTES))?></dd></div>
                    <div><dt>Enkripsi file</dt><dd>AES-256-GCM + RSA-3072-OAEP-SHA1</dd></div>
                    <div><dt>Enkripsi teks</dt><dd>AES-256-GCM + RSA-3072-OAEP-SHA1</dd></div>
                </dl>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
