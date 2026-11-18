<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$error = '';
$formTitle = '';
$formContent = '';
$editingId = 0;
$editQuery = $_GET['edit'] ?? '';
if ($editQuery !== '' && (!is_string($editQuery) || !ctype_digit($editQuery) || (int)$editQuery < 1)) {
    http_response_code(400);
    exit('Permintaan edit tidak valid.');
}
if (is_string($editQuery) && $editQuery !== '') $editingId = (int)$editQuery;

if ($editingId > 0) {
    $stmt = db()->prepare('SELECT * FROM encrypted_texts WHERE id = ?');
    $stmt->execute([$editingId]);
    $encryptedEntry = $stmt->fetch();
    if (!$encryptedEntry) {
        http_response_code(404);
        exit('Entri teks tidak ditemukan.');
    }
    try {
        $payload = json_decode(decrypt_text_hybrid($encryptedEntry), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !isset($payload['title'], $payload['content']) || !is_string($payload['title']) || !is_string($payload['content'])) {
            throw new RuntimeException('Format isi entri terenkripsi tidak valid.');
        }
        $formTitle = $payload['title'];
        $formContent = $payload['content'];
    } catch (Throwable $e) {
        error_log('GameVault encrypted text edit failed: ' . $e->getMessage());
        $error = 'Entri tidak dapat dibuka. Periksa private key dan data enkripsinya.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    if (!is_string($action)) $action = '';

    if ($action === 'delete') {
        $deleteId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$deleteId || $deleteId < 1) {
            $error = 'Entri yang akan dihapus tidak valid.';
        } else {
            $stmt = db()->prepare('DELETE FROM encrypted_texts WHERE id = ?');
            $stmt->execute([$deleteId]);
            flash($stmt->rowCount() > 0 ? 'Teks terenkripsi berhasil dihapus.' : 'Entri tidak ditemukan.', $stmt->rowCount() > 0 ? 'success' : 'danger');
            header('Location: texts.php');
            exit;
        }
    } elseif ($action === 'save') {
        $titleInput = $_POST['title'] ?? '';
        $contentInput = $_POST['content'] ?? '';
        $idInput = $_POST['id'] ?? '';
        $formTitle = is_string($titleInput) ? trim($titleInput) : '';
        $formContent = is_string($contentInput) ? $contentInput : '';
        if ($idInput !== '' && (!is_string($idInput) || !ctype_digit($idInput) || (int)$idInput < 1)) {
            $error = 'Entri yang akan diperbarui tidak valid.';
        } else {
            $editingId = is_string($idInput) && $idInput !== '' ? (int)$idInput : 0;
        }

        try {
            if ($error !== '') throw new RuntimeException($error);
            $titleLength = function_exists('mb_strlen') ? mb_strlen($formTitle, 'UTF-8') : strlen($formTitle);
            if ($formTitle === '') throw new RuntimeException('Judul wajib diisi.');
            if ($titleLength > 180) throw new RuntimeException('Judul maksimal 180 karakter.');
            if (trim($formContent) === '') throw new RuntimeException('Isi teks wajib diisi.');
            if (strlen($formContent) > 100000) throw new RuntimeException('Isi teks maksimal 100 KB.');

            $plainText = json_encode(
                ['title' => $formTitle, 'content' => $formContent],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $encrypted = encrypt_text_hybrid($plainText);
            if ($editingId > 0) {
                $stmt = db()->prepare('UPDATE encrypted_texts SET ciphertext = ?, wrapped_key = ?, iv = ?, auth_tag = ?, algorithm = ? WHERE id = ?');
                $stmt->execute([
                    $encrypted['ciphertext'],
                    $encrypted['wrapped_key'],
                    $encrypted['iv'],
                    $encrypted['auth_tag'],
                    $encrypted['algorithm'],
                    $editingId,
                ]);
                if ($stmt->rowCount() === 0) {
                    $check = db()->prepare('SELECT 1 FROM encrypted_texts WHERE id = ?');
                    $check->execute([$editingId]);
                    if (!$check->fetchColumn()) throw new RuntimeException('Entri teks tidak ditemukan.');
                }
                flash('Teks berhasil dienkripsi dan diperbarui.');
            } else {
                $stmt = db()->prepare('INSERT INTO encrypted_texts (ciphertext, wrapped_key, iv, auth_tag, algorithm, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $encrypted['ciphertext'],
                    $encrypted['wrapped_key'],
                    $encrypted['iv'],
                    $encrypted['auth_tag'],
                    $encrypted['algorithm'],
                    (int)$_SESSION['admin_id'],
                ]);
                flash('Teks berhasil dienkripsi dan disimpan.');
            }
            header('Location: texts.php');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Aksi tidak dikenali.';
    }
}

$entries = db()->query('SELECT * FROM encrypted_texts ORDER BY id DESC')->fetchAll();
foreach ($entries as &$entry) {
    try {
        $payload = json_decode(decrypt_text_hybrid($entry), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !isset($payload['title'], $payload['content']) || !is_string($payload['title']) || !is_string($payload['content'])) {
            throw new RuntimeException('Format isi entri terenkripsi tidak valid.');
        }
        $entry['title'] = $payload['title'];
        $entry['content'] = $payload['content'];
        $entry['decrypt_error'] = false;
    } catch (Throwable $e) {
        error_log('GameVault encrypted text list failed: ' . $e->getMessage());
        $entry['title'] = 'Entri tidak dapat dibuka';
        $entry['content'] = 'Periksa private key dan data enkripsinya.';
        $entry['decrypt_error'] = true;
    }
}
unset($entry);

$flash = get_flash();
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Enkripsi Teks · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="texts-page">
    <aside class="dashboard-sidebar">
        <a class="dashboard-brand" href="dashboard.php"><img class="gamepad-mark" src="../assets/gamevault-logo.jpg" alt=""><span>GameVault</span></a>
        <nav aria-label="Navigasi utama">
            <a href="dashboard.php"><span class="nav-icon">⌂</span><span class="nav-label">Dashboard</span></a>
            <a href="files.php"><span class="nav-icon">▤</span><span class="nav-label">Kelola File</span></a>
            <a class="active" href="texts.php"><span class="nav-icon">▧</span><span class="nav-label">Enkripsi Teks</span></a>
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

    <main class="texts-main">
        <header class="texts-header">
            <div>
                <p class="texts-eyebrow">ENCRYPTED NOTES</p>
                <h1>Enkripsi Teks</h1>
                <p>Simpan informasi pribadi dalam bentuk terenkripsi.</p>
            </div>
            <span class="texts-lock-badge">AES-256-GCM <span>+</span> RSA-3072</span>
        </header>

        <?php if ($flash): ?><div class="texts-alert success"><?=e($flash['message'])?></div><?php endif; ?>
        <?php if ($error): ?><div class="texts-alert danger" role="alert"><?=e($error)?></div><?php endif; ?>

        <div class="texts-layout">
            <section class="text-compose-card">
                <p class="texts-eyebrow">ENTRI BARU</p>
                <h2><?=$editingId > 0 ? 'Edit informasi' : 'Tambah informasi'?></h2>
                <p class="text-compose-description">Judul dan isi dienkripsi sebelum disimpan.</p>
                <form method="post" action="texts.php<?= $editingId > 0 ? '?edit=' . $editingId : '' ?>">
                    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="save">
                    <?php if ($editingId > 0): ?><input type="hidden" name="id" value="<?=$editingId?>"><?php endif; ?>
                    <label for="text-title">Judul</label>
                    <input id="text-title" name="title" maxlength="180" required value="<?=e($formTitle)?>" placeholder="Contoh: Ringkasan rapat">
                    <label for="text-content">Isi informasi</label>
                    <textarea id="text-content" name="content" rows="8" maxlength="25000" required placeholder="Tuliskan informasi yang ingin disimpan..."><?=e($formContent)?></textarea>
                    <button class="text-save-button" type="submit"><?=$editingId > 0 ? 'Enkripsi & perbarui' : 'Enkripsi & simpan'?><span aria-hidden="true">→</span></button>
                </form>
                <?php if ($editingId > 0): ?><a class="text-cancel-link" href="texts.php">Batalkan edit</a><?php endif; ?>
                <div class="text-security-note">
                    <span aria-hidden="true">▣</span>
                    <p>Judul dan isi dilindungi AES-256-GCM. Kunci AES dibungkus RSA-3072.</p>
                </div>
            </section>

            <section class="text-archive">
                <header class="text-archive-header">
                    <div><p class="texts-eyebrow">DATA TERSIMPAN</p><h2>Arsip Anda</h2></div>
                    <span class="text-entry-count"><?=count($entries)?> ENTRI</span>
                </header>
                <?php if (!$entries): ?>
                    <div class="text-archive-empty"><span aria-hidden="true">▧</span><strong>Belum ada teks terenkripsi</strong><p>Tambahkan informasi melalui formulir di sebelah kiri.</p></div>
                <?php else: ?>
                    <div class="text-entry-list">
                        <?php foreach ($entries as $entry): ?>
                            <article class="text-entry-card">
                                <header class="text-entry-header">
                                    <div class="text-entry-date">
                                        <strong>#<?=(int)$entry['id']?></strong>
                                        <span aria-hidden="true">·</span>
                                        <time datetime="<?=e(date('c', strtotime($entry['created_at'])))?>"><?=e(date('d M Y · H:i', strtotime($entry['created_at'])))?></time>
                                    </div>
                                    <span class="encrypted-result-status <?=$entry['decrypt_error'] ? 'failed' : 'success'?>">
                                        <?=$entry['decrypt_error'] ? 'Dekripsi gagal' : 'Dekripsi berhasil'?>
                                    </span>
                                </header>
                                <div class="encrypted-result-grid">
                                    <section class="encrypted-result-panel ciphertext-panel" aria-label="Ciphertext tersimpan">
                                        <h3>CIPHERTEXT (TERSIMPAN DI DATABASE)</h3>
                                        <div class="encrypted-result-field">
                                            <label>Ciphertext AES-256-GCM (judul + isi)</label>
                                            <code><?=e($entry['ciphertext'])?></code>
                                        </div>
                                        <div class="encrypted-result-field">
                                            <label>Kunci AES dibungkus RSA-3072/OAEP</label>
                                            <code><?=e($entry['wrapped_key'])?></code>
                                        </div>
                                        <div class="encrypted-result-meta">
                                            <span>IV: <code><?=e($entry['iv'])?></code></span>
                                            <span>Tag GCM: <code><?=e($entry['auth_tag'])?></code></span>
                                        </div>
                                        <p class="encrypted-result-hint">RSA membuka kunci AES; AES-256-GCM digunakan untuk mendekripsi data.</p>
                                    </section>
                                    <section class="encrypted-result-panel plaintext-panel" aria-label="Plaintext hasil dekripsi">
                                        <h3>PLAINTEXT (HASIL DEKRIPSI RSA + AES)</h3>
                                        <?php if ($entry['decrypt_error']): ?>
                                            <p class="plaintext-unavailable">Plaintext tidak dapat ditampilkan. Periksa private key RSA dan autentikasi AES-GCM.</p>
                                        <?php else: ?>
                                            <div class="plaintext-result-field">
                                                <strong>Judul:</strong>
                                                <p><?=e($entry['title'])?></p>
                                            </div>
                                            <div class="plaintext-result-field">
                                                <strong>Isi:</strong>
                                                <p><?=nl2br(e($entry['content']))?></p>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                </div>
                                <footer class="text-entry-actions">
                                    <a href="texts.php?edit=<?=(int)$entry['id']?>">Edit</a>
                                    <form method="post" action="texts.php" onsubmit="return confirm('Hapus teks ini secara permanen?')">
                                        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?=(int)$entry['id']?>">
                                        <button type="submit">Hapus</button>
                                    </form>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
