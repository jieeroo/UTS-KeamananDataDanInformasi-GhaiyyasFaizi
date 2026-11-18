<?php
require_once __DIR__ . '/../includes/bootstrap.php'; require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title === '') throw new RuntimeException('Judul wajib diisi.');
        if (!isset($_FILES['game_file']) || $_FILES['game_file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Pilih file yang valid.');
        $up = $_FILES['game_file'];
        if ($up['size'] <= 0 || $up['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Ukuran file harus 1 byte sampai 100 MB.');
        $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($up['tmp_name']) ?: 'application/octet-stream';
        $allowed = ['application/octet-stream','application/zip','application/x-7z-compressed','application/x-rar','application/vnd.microsoft.portable-executable','application/x-msdownload','application/pdf','text/plain','image/png','image/jpeg'];
        if (!in_array($mime, $allowed, true)) throw new RuntimeException('Tipe file tidak diizinkan: ' . $mime);
        $stored = bin2hex(random_bytes(24)) . '.gvlt';
        $target = STORAGE_PATH . '/' . $stored;
        $meta = encrypt_file_hybrid($up['tmp_name'], $target);
        $stmt = db()->prepare('INSERT INTO game_files (title,description,original_name,stored_name,mime_type,file_size,wrapped_key,iv,auth_tag,algorithm,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$title,$description,basename($up['name']),$stored,$mime,(int)$up['size'],$meta['wrapped_key'],$meta['iv'],$meta['tag'],$meta['algorithm'],(int)$_SESSION['admin_id']]);
        $fileId = (int)db()->lastInsertId();
        flash('File berhasil dienkripsi dan disimpan.');
        header('Location: detail.php?id=' . $fileId);
        exit;
    } catch (Throwable $e) { if (isset($target) && is_file($target)) unlink($target); $error = $e->getMessage(); }
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Upload File · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="upload-modal-page">
    <div class="upload-modal-backdrop" aria-hidden="true"></div>
    <main class="upload-modal">
        <section class="upload-modal-card" aria-labelledby="upload-title">
            <a class="upload-modal-close" href="files.php" aria-label="Kembali ke Kelola File">×</a>
            <header class="upload-modal-header">
                <span class="upload-cloud-icon" aria-hidden="true">
                    <img class="upload-hacker-image upload-hacker-image-header" src="../assets/peretas.jpg" alt="">
                </span>
                <h1 id="upload-title">Upload File</h1>
                <p>Enkripsi file sebelum disimpan ke server</p>
            </header>

            <?php if ($error): ?><div class="upload-error" role="alert"><?=e($error)?></div><?php endif; ?>

            <form class="upload-modal-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">

                <label class="upload-field-label" for="upload-title-input">Nama / judul file</label>
                <input class="upload-text-input" id="upload-title-input" name="title" maxlength="180" required value="<?=e($_POST['title'] ?? '')?>" placeholder="Masukkan nama file">

                <label class="upload-field-label" for="upload-description">Deskripsi</label>
                <textarea class="upload-textarea" id="upload-description" name="description" rows="2" placeholder="Deskripsi singkat (opsional)"><?=e($_POST['description'] ?? '')?></textarea>

                <label class="upload-field-label" for="game-file">Pilih file</label>
                <label class="upload-dropzone" id="upload-dropzone" for="game-file">
                    <img class="upload-hacker-image upload-hacker-image-drop" src="../assets/peretas.jpg" alt="">
                    <strong>Drag &amp; Drop File di sini</strong>
                    <span>atau pilih file dari perangkat</span>
                    <span class="upload-pick-button">Pilih File</span>
                    <input class="upload-file-input" id="game-file" type="file" name="game_file" required accept=".zip,.rar,.7z,.exe,.apk,.pdf,.txt,.png,.jpg,.jpeg">
                </label>
                <p class="upload-selected-file" id="upload-selected-file" aria-live="polite">Belum ada file dipilih</p>
                <p class="upload-format">Format: ZIP, RAR, 7Z, EXE, APK, PDF, TXT, PNG, JPG · Maksimal 100 MB</p>

                <div class="upload-security-cards">
                    <div class="upload-security-card">
                        <span class="upload-security-icon aes">✓</span>
                        <span><strong>AES-256-GCM</strong><small>Enkripsi isi file</small></span>
                    </div>
                    <div class="upload-security-card">
                        <span class="upload-security-icon rsa">⌑</span>
                        <span><strong>RSA-3072</strong><small>Enkripsi kunci AES</small></span>
                    </div>
                    <div class="upload-security-card">
                        <span class="upload-security-icon private">●</span>
                        <span><strong>Private Key</strong><small>Akses terlindungi</small></span>
                    </div>
                </div>

                <button class="upload-submit-button" type="submit">Enkripsi &amp; simpan <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
    <script>
        const uploadInput = document.getElementById('game-file');
        const uploadDropzone = document.getElementById('upload-dropzone');
        const selectedFile = document.getElementById('upload-selected-file');

        uploadInput.addEventListener('change', () => {
            const file = uploadInput.files[0];
            selectedFile.textContent = file
                ? `${file.name} · ${(file.size / (1024 * 1024)).toFixed(2)} MB`
                : 'Belum ada file dipilih';
        });

        uploadDropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            uploadDropzone.classList.add('is-dragging');
        });
        uploadDropzone.addEventListener('dragleave', (event) => {
            if (!uploadDropzone.contains(event.relatedTarget)) {
                uploadDropzone.classList.remove('is-dragging');
            }
        });
        uploadDropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            uploadDropzone.classList.remove('is-dragging');
            if (event.dataTransfer.files.length > 0) {
                uploadInput.files = event.dataTransfer.files;
                uploadInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    </script>
</body>
</html>
