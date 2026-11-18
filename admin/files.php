<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$categorySql = "CASE
    WHEN LOWER(original_name) LIKE '%.apk' OR mime_type = 'application/vnd.android.package-archive' THEN 'Android'
    WHEN LOWER(original_name) LIKE '%.exe' OR mime_type IN ('application/vnd.microsoft.portable-executable', 'application/x-msdownload') THEN 'PC'
    WHEN LOWER(original_name) REGEXP '\\.(zip|rar|7z)$' OR mime_type IN ('application/zip', 'application/x-7z-compressed', 'application/x-rar') THEN 'Arsip'
    WHEN mime_type = 'application/pdf' THEN 'Dokumen'
    WHEN mime_type LIKE 'image/%' THEN 'Gambar'
    WHEN mime_type LIKE 'text/%' THEN 'Teks'
    ELSE 'File umum'
END";
$categoryLabels = ['PC', 'Android', 'Arsip', 'Dokumen', 'Gambar', 'Teks', 'File umum'];
$searchInput = $_GET['q'] ?? '';
$categoryInput = $_GET['category'] ?? '';
$sortInput = $_GET['sort'] ?? 'newest';
$search = is_string($searchInput) ? trim($searchInput) : '';
$category = is_string($categoryInput) ? $categoryInput : '';
$sort = is_string($sortInput) ? $sortInput : 'newest';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = max(1, $page ?: 1);
if (!in_array($category, $categoryLabels, true)) $category = '';
$sortSql = [
    'newest' => 'created_at DESC, id DESC',
    'oldest' => 'created_at ASC, id ASC',
    'name' => 'title ASC, id DESC',
    'size' => 'file_size DESC, id DESC',
];
if (!isset($sortSql[$sort])) $sort = 'newest';

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(title LIKE :title_search OR original_name LIKE :name_search)';
    $params['title_search'] = '%' . $search . '%';
    $params['name_search'] = '%' . $search . '%';
}
if ($category !== '') {
    $conditions[] = "($categorySql) = :category";
    $params['category'] = $category;
}
$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$countStmt = db()->prepare('SELECT COUNT(*) FROM game_files' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$perPage = 10;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT id, title, original_name, mime_type, file_size, created_at, ($categorySql) AS category
    FROM game_files$whereSql
    ORDER BY {$sortSql[$sort]}
    LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$files = $stmt->fetchAll();
$flash = get_flash();
$firstItem = $total > 0 ? $offset + 1 : 0;
$lastItem = min($offset + $perPage, $total);
$pageUrl = static function (int $number): string {
    $query = $_GET;
    $query['page'] = $number;
    return 'files.php?' . http_build_query($query);
};
$categoryClass = static function (string $label): string {
    return 'category-' . strtolower(str_replace(' ', '-', $label));
};
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kelola File · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="files-page">
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

    <main class="files-main">
        <header class="files-header">
            <div class="files-heading">
                <p class="files-eyebrow"><span aria-hidden="true">▤</span> SECURE LIBRARY</p>
                <h1>Kelola File</h1>
                <p>Manajemen file game rahasia Anda.</p>
            </div>
            <a class="files-upload-button" href="upload.php"><span aria-hidden="true">＋</span> Upload File</a>
        </header>

        <?php if ($flash): ?><div class="files-alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>

        <section class="files-panel" aria-label="Daftar file">
            <form class="files-toolbar" method="get" action="files.php">
                <label class="file-search">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="q" value="<?=e($search)?>" placeholder="Cari file game..." aria-label="Cari file">
                </label>
                <label class="file-select">
                    <span class="sr-only">Kategori</span>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categoryLabels as $label): ?>
                            <option value="<?=e($label)?>" <?=$category === $label ? 'selected' : ''?>><?=e($label)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="file-select sort-select">
                    <span class="sr-only">Urutan</span>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest" <?=$sort === 'newest' ? 'selected' : ''?>>Terbaru</option>
                        <option value="oldest" <?=$sort === 'oldest' ? 'selected' : ''?>>Terlama</option>
                        <option value="name" <?=$sort === 'name' ? 'selected' : ''?>>Nama A–Z</option>
                        <option value="size" <?=$sort === 'size' ? 'selected' : ''?>>Ukuran terbesar</option>
                    </select>
                </label>
                <button class="file-search-button" type="submit">Cari</button>
            </form>

            <div class="files-table-wrap">
                <table class="files-table">
                    <thead>
                        <tr><th>No</th><th>Preview</th><th>Nama File</th><th>Kategori</th><th>Ukuran</th><th>Tanggal Upload</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$files): ?>
                        <tr><td class="files-empty" colspan="7"><?=$total === 0 && $search === '' && $category === '' ? 'Belum ada file tersimpan.' : 'File tidak ditemukan. Coba ubah pencarian atau kategori.'?></td></tr>
                    <?php else: ?>
                        <?php foreach ($files as $index => $file):
                            $extension = strtoupper(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                            if ($extension === '') $extension = 'FILE';
                            $extension = substr($extension, 0, 5);
                        ?>
                            <tr>
                                <td class="file-row-number"><?=$offset + $index + 1?></td>
                                <td>
                                    <?php if (in_array($file['mime_type'], ['image/png', 'image/jpeg'], true)): ?>
                                        <img class="file-preview-image" src="preview.php?id=<?=(int)$file['id']?>" alt="Pratinjau <?=e($file['title'])?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="file-preview-icon <?=e($categoryClass($file['category']))?>" aria-label="<?=e($file['category'])?>"><?=e($extension)?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="file-name-cell"><a class="file-detail-link" href="detail.php?id=<?=(int)$file['id']?>"><strong><?=e($file['title'])?></strong></a><small><?=e($file['original_name'])?></small></td>
                                <td><span class="file-category <?=e($categoryClass($file['category']))?>"><?=e($file['category'])?></span></td>
                                <td class="file-size-cell"><?=e(format_bytes((int)$file['file_size']))?></td>
                                <td class="file-date-cell"><?=e(date('d M Y H:i', strtotime($file['created_at'])))?></td>
                                <td>
                                    <div class="file-actions">
                                        <a class="file-action detail-action" href="detail.php?id=<?=(int)$file['id']?>" title="Lihat detail file" aria-label="Lihat detail <?=e($file['title'])?>">
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 10s2.7-5 8-5 8 5 8 5-2.7 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg>
                                        </a>
                                        <a class="file-action download-action" href="download.php?id=<?=(int)$file['id']?>" title="Unduh dan dekripsi file" aria-label="Unduh <?=e($file['title'])?>">
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5v9m0 0 3.5-3.5M10 11.5 6.5 8M3.5 13.5v3h13v-3"/></svg>
                                        </a>
                                        <a class="file-action edit-action" href="edit.php?id=<?=(int)$file['id']?>" title="Edit metadata" aria-label="Edit <?=e($file['title'])?>">
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m3 13.8-.8 4 4-.8L17 6.2 13.8 3 3 13.8Z"/><path d="m11.9 4.9 3.2 3.2"/></svg>
                                        </a>
                                        <form method="post" action="delete.php" onsubmit="return confirm('Hapus file ini secara permanen?')">
                                            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                                            <input type="hidden" name="id" value="<?=(int)$file['id']?>">
                                            <button class="file-action delete-action" type="submit" title="Hapus permanen" aria-label="Hapus <?=e($file['title'])?>">
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 6h12M8 6V4h4v2m3 0-.7 11H5.7L5 6m3 3v5m4-5v5"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="files-pagination">
                <span>Menampilkan <?=$firstItem?>–<?=$lastItem?> dari <?=$total?> file</span>
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Halaman daftar file">
                        <?php if ($page > 1): ?><a href="<?=e($pageUrl($page - 1))?>" aria-label="Halaman sebelumnya">‹</a><?php endif; ?>
                        <?php
                        $visiblePages = range(max(1, $page - 1), min($totalPages, $page + 1));
                        if ($visiblePages[0] > 1):
                        ?>
                            <a href="<?=e($pageUrl(1))?>">1</a>
                            <?php if ($visiblePages[0] > 2): ?><span>…</span><?php endif; ?>
                        <?php endif; ?>
                        <?php foreach ($visiblePages as $number): ?>
                            <a class="<?=$number === $page ? 'current' : ''?>" href="<?=e($pageUrl($number))?>" <?=$number === $page ? 'aria-current="page"' : ''?>><?=$number?></a>
                        <?php endforeach; ?>
                        <?php if (end($visiblePages) < $totalPages):
                            if (end($visiblePages) < $totalPages - 1):
                        ?>
                            <span>…</span>
                        <?php endif; ?>
                            <a href="<?=e($pageUrl($totalPages))?>"><?=$totalPages?></a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?><a href="<?=e($pageUrl($page + 1))?>" aria-label="Halaman berikutnya">›</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            </footer>
        </section>
    </main>
</body>
</html>
