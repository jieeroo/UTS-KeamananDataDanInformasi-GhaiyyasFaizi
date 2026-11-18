<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$stmt = db()->prepare('SELECT * FROM game_files WHERE id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
$mime = $file['mime_type'];
if ($extension === 'apk' || $mime === 'application/vnd.android.package-archive') {
    $category = 'Android';
} elseif ($extension === 'exe' || in_array($mime, ['application/vnd.microsoft.portable-executable', 'application/x-msdownload'], true)) {
    $category = 'PC';
} elseif (in_array($extension, ['zip', 'rar', '7z'], true) || in_array($mime, ['application/zip', 'application/x-7z-compressed', 'application/x-rar'], true)) {
    $category = 'Arsip';
} elseif ($mime === 'application/pdf') {
    $category = 'Dokumen';
} elseif (str_starts_with($mime, 'image/')) {
    $category = 'Gambar';
} elseif (str_starts_with($mime, 'text/')) {
    $category = 'Teks';
} else {
    $category = 'File umum';
}

$encryptedPath = STORAGE_PATH . '/' . basename($file['stored_name']);
$fileAvailable = is_file($encryptedPath);
$ciphertextPreview = '';
$ciphertextPreviewError = '';
if ($fileAvailable) {
    $encryptedHandle = fopen($encryptedPath, 'rb');
    if ($encryptedHandle === false) {
        $ciphertextPreviewError = 'Tidak dapat membaca data terenkripsi untuk pratinjau.';
        error_log('GameVault encrypted file result: unable to open encrypted file.');
    } else {
        $header = fread($encryptedHandle, 9);
        if ($header === false || strlen($header) !== 9 || substr($header, 0, 5) !== 'GVLT1') {
            $ciphertextPreviewError = 'Format data terenkripsi tidak valid.';
        } else {
            $wrappedKeyLength = unpack('Nlength', substr($header, 5, 4))['length'];
            $wrappedKey = base64_decode($file['wrapped_key'], true);
            if ($wrappedKeyLength < 256 || $wrappedKeyLength > 1024 || $wrappedKey === false || strlen($wrappedKey) !== $wrappedKeyLength) {
                $ciphertextPreviewError = 'Metadata kunci RSA tidak valid.';
            } elseif (fseek($encryptedHandle, 9 + $wrappedKeyLength + 28) !== 0) {
                $ciphertextPreviewError = 'Tidak dapat membaca ciphertext file.';
            } else {
                $ciphertextBytes = fread($encryptedHandle, 48);
                if ($ciphertextBytes === false || $ciphertextBytes === '') {
                    $ciphertextPreviewError = 'Ciphertext file tidak tersedia.';
                } else {
                    $ciphertextPreview = base64_encode($ciphertextBytes);
                }
            }
        }
        fclose($encryptedHandle);
        if ($ciphertextPreviewError !== '') {
            error_log('GameVault encrypted file result: ' . $ciphertextPreviewError);
        }
    }
}
$previewable = in_array($mime, ['image/png', 'image/jpeg'], true);
$extensionLabel = $extension !== '' ? strtoupper(substr($extension, 0, 6)) : 'FILE';
$flash = get_flash();
$categoryClass = 'category-' . strtolower(str_replace(' ', '-', $category));
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail File · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="files-page file-detail-page">
    <aside class="dashboard-sidebar">
        <a class="dashboard-brand" href="dashboard.php"><img class="gamepad-mark" src="../assets/gamevault-logo.jpg" alt=""><span>GameVault</span></a>
        <nav aria-label="Navigasi utama">
            <a href="dashboard.php"><span class="nav-icon">⌂</span><span class="nav-label">Dashboard</span></a>
            <a class="active" href="files.php"><span class="nav-icon">▤</span><span class="nav-label">Kelola File</span></a>
            <a href="texts.php"><span class="nav-icon">▧</span><span class="nav-label">Enkripsi Teks</span></a>
            <a href="settings.php"><span class="nav-icon">⚙</span><span class="nav-label">Pengaturan</span></a>
        </nav>
        <div class="dashboard-sidebar-bottom">
            <div class="sidebar-user">
                <span class="dashboard-avatar"><?=e(strtoupper(substr($_SESSION['admin_username'], 0, 1)))?></span>
                <span class="sidebar-user-copy"><?=e($_SESSION['admin_username'])?><small>Administrator</small></span>
            </div>
            <a class="dashboard-logout" href="logout.php"><span>↪</span><span class="nav-label">Keluar</span></a>
        </div>
    </aside>

    <main class="file-detail-main">
        <header class="files-header">
            <div class="files-heading">
                <p class="files-eyebrow"><span aria-hidden="true">▤</span> SECURE LIBRARY</p>
                <h1>Detail File</h1>
                <p>Informasi lengkap file game.</p>
            </div>
            <a class="files-upload-button file-detail-back" href="files.php">← Kembali ke Kelola File</a>
        </header>

        <?php if ($flash): ?><div class="files-alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>

        <section class="file-detail-grid" aria-label="Informasi file">
            <div class="file-detail-preview">
                <?php if ($previewable && $fileAvailable): ?>
                    <img src="preview.php?id=<?=(int)$file['id']?>" alt="Pratinjau <?=e($file['title'])?>">
                <?php else: ?>
                    <div class="file-detail-placeholder <?=e($categoryClass)?>">
                        <span aria-hidden="true"><?=e($extensionLabel)?></span>
                        <small><?=e($category)?></small>
                    </div>
                <?php endif; ?>
            </div>

            <div class="file-detail-metadata">
                <h2><?=e($file['title'])?></h2>
                <dl>
                    <div><dt>Nama File</dt><dd><?=e($file['original_name'])?></dd></div>
                    <div><dt>Kategori</dt><dd><span class="file-category <?=e($categoryClass)?>"><?=e($category)?></span></dd></div>
                    <div><dt>Ukuran</dt><dd><?=e(format_bytes((int)$file['file_size']))?></dd></div>
                    <div><dt>Tanggal Upload</dt><dd><?=e(date('d M Y, H:i', strtotime($file['created_at'])))?></dd></div>
                    <div><dt>Algoritma</dt><dd><?=e($file['algorithm'])?></dd></div>
                    <div>
                        <dt>Status</dt>
                        <dd class="<?=$fileAvailable ? 'file-detail-status available' : 'file-detail-status missing'?>">
                            <span aria-hidden="true"><?=$fileAvailable ? '●' : '!'?></span>
                            <?=$fileAvailable ? 'Tersimpan terenkripsi' : 'Data terenkripsi tidak ditemukan'?>
                        </dd>
                    </div>
                </dl>
                <?php if (trim((string)$file['description']) !== ''): ?>
                    <div class="file-detail-description">
                        <h3>Deskripsi</h3>
                        <p><?=nl2br(e($file['description']))?></p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="file-detail-actions" aria-label="Aksi file">
                <h2>Aksi</h2>
                <a class="file-detail-button primary" href="download.php?id=<?=(int)$file['id']?>">
                    <span aria-hidden="true">↓</span> Unduh File
                </a>
                <a class="file-detail-button secondary" href="edit.php?id=<?=(int)$file['id']?>">
                    <span aria-hidden="true">✎</span> Edit Metadata
                </a>
                <form method="post" action="delete.php" onsubmit="return confirm('Hapus file ini secara permanen?')">
                    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="id" value="<?=(int)$file['id']?>">
                    <button class="file-detail-button danger" type="submit"><span aria-hidden="true">▣</span> Hapus File</button>
                </form>
            </aside>
        </section>

        <section class="file-encryption-results" aria-label="Hasil enkripsi file">
            <header class="file-encryption-results-header">
                <div>
                    <p class="files-eyebrow">HASIL ENKRIPSI</p>
                    <h2>Enkripsi simetris &amp; asimetris</h2>
                    <p>Isi file dilindungi AES-256-GCM dan kunci AES dibungkus RSA-3072-OAEP.</p>
                </div>
            </header>
            <div class="file-encryption-results-grid">
                <section class="file-encryption-result-panel" aria-label="Hasil enkripsi simetris">
                    <h3>AES-256-GCM · ENKRIPSI SIMETRIS</h3>
                    <div class="file-encryption-result-field">
                        <label>Ciphertext file (pratinjau 48 byte pertama, Base64)</label>
                        <?php if ($ciphertextPreview !== ''): ?>
                            <code><?=e($ciphertextPreview)?></code>
                        <?php else: ?>
                            <p class="file-encryption-result-error"><?=e($ciphertextPreviewError !== '' ? $ciphertextPreviewError : 'File terenkripsi tidak tersedia.')?></p>
                        <?php endif; ?>
                    </div>
                    <div class="file-encryption-result-field">
                        <label>IV</label>
                        <code><?=e($file['iv'])?></code>
                    </div>
                    <div class="file-encryption-result-field">
                        <label>Tag autentikasi GCM</label>
                        <code><?=e($file['auth_tag'])?></code>
                    </div>
                </section>
                <section class="file-encryption-result-panel" aria-label="Hasil enkripsi asimetris">
                    <h3>RSA-3072-OAEP · ENKRIPSI ASIMETRIS</h3>
                    <div class="file-encryption-result-field">
                        <label>Kunci AES yang dibungkus public key RSA (Base64)</label>
                        <code><?=e($file['wrapped_key'])?></code>
                    </div>
                    <p class="file-encryption-result-note">Private key RSA membuka kunci AES. Kunci AES tersebut kemudian digunakan untuk mendekripsi ciphertext dan memverifikasi tag GCM.</p>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
