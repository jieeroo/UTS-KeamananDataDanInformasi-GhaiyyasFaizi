<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$total = (int)db()->query('SELECT COUNT(*) FROM game_files')->fetchColumn();
$bytes = (int)db()->query('SELECT COALESCE(SUM(file_size),0) FROM game_files')->fetchColumn();
$recent = db()->query('SELECT id,title,original_name,file_size,created_at FROM game_files ORDER BY id DESC LIMIT 6')->fetchAll();
$monthly = db()->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total
    FROM game_files
    WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY period
    ORDER BY period
")->fetchAll();
$flash = get_flash();

$monthlyCounts = array_column($monthly, 'total', 'period');
$monthStart = new DateTimeImmutable('first day of this month');
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = $monthStart->modify("-{$i} months");
    $key = $month->format('Y-m');
    $chartData[] = [
        'label' => $month->format('M'),
        'count' => (int)($monthlyCounts[$key] ?? 0),
    ];
}
$chartMax = max(5, (int)(ceil(max(array_column($chartData, 'count')) / 5) * 5));
$chartLine = '';
$chartPoints = [];
foreach ($chartData as $index => $point) {
    $x = 52 + $index * 110;
    $y = 170 - ($point['count'] / $chartMax) * 126;
    $chartLine .= ($index === 0 ? 'M ' : ' L ') . $x . ' ' . number_format($y, 1, '.', '');
    $chartPoints[] = ['x' => $x, 'y' => number_format($y, 1, '.', ''), 'label' => $point['label']];
}
$chartArea = $chartLine . ' L 602 178 L 52 178 Z';
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-page">
    <aside class="dashboard-sidebar">
        <a class="dashboard-brand" href="dashboard.php"><img class="gamepad-mark" src="../assets/gamevault-logo.jpg" alt=""><span>GameVault</span></a>
        <nav aria-label="Navigasi utama">
            <a class="active" href="dashboard.php"><span class="nav-icon">⌂</span><span class="nav-label">Dashboard</span></a>
            <a href="files.php"><span class="nav-icon">▤</span><span class="nav-label">Kelola File</span></a>
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

    <main class="dashboard-main">
        <header class="dashboard-hero">
            <div class="hero-copy">
                <p class="dashboard-eyebrow">GAMEVAULT · PRIVATE STORAGE</p>
                <h1>Dashboard</h1>
                <p>Selamat datang, <?=e($_SESSION['admin_username'])?>! <span aria-hidden="true">👋</span></p>
            </div>
            <a class="hero-admin" href="logout.php" title="Keluar">
                <span class="dashboard-avatar"><?=e(strtoupper(substr($_SESSION['admin_username'], 0, 1)))?></span>
                <span>Admin<small><?=e($_SESSION['admin_username'])?></small></span>
                <span class="hero-chevron">⌄</span>
            </a>
        </header>

        <?php if ($flash): ?><div class="alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>

        <section class="dashboard-stats" aria-label="Ringkasan penyimpanan">
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon files-icon">▣</span>
                <div><span class="dashboard-stat-label">Total File</span><strong><?=$total?></strong><small>Tersimpan terenkripsi</small></div>
            </article>
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon storage-icon">↓</span>
                <div><span class="dashboard-stat-label">Total Penyimpanan</span><strong><?=e(format_bytes($bytes))?></strong><small><?=$total?> file tersimpan</small></div>
            </article>
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon shield-icon">◆</span>
                <div><span class="dashboard-stat-label">Keamanan Aktif</span><strong><?=$total > 0 ? '100%' : 'Siap'?></strong><small>AES + RSA</small></div>
            </article>
        </section>

        <section class="dashboard-content-grid">
            <article class="dashboard-card chart-card">
                <div class="dashboard-card-heading">
                    <div><h2>Statistik File Game</h2><p>File yang diunggah selama 6 bulan terakhir</p></div>
                    <span class="chart-period">6 BULAN</span>
                </div>
                <div class="chart-wrap">
                    <svg class="upload-chart" viewBox="0 0 650 220" role="img" aria-labelledby="chart-title chart-desc">
                        <title id="chart-title">Statistik unggahan file</title>
                        <desc id="chart-desc">Jumlah file yang diunggah per bulan selama enam bulan terakhir.</desc>
                        <defs>
                            <linearGradient id="chart-fill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#805cff" stop-opacity=".42"/>
                                <stop offset="100%" stop-color="#805cff" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <?php for ($grid = 0; $grid <= 4; $grid++):
                            $gridY = 44 + $grid * 33;
                            $gridValue = (int)round($chartMax * (4 - $grid) / 4);
                        ?>
                            <line class="chart-gridline" x1="42" y1="<?=$gridY?>" x2="618" y2="<?=$gridY?>"/>
                            <text class="chart-axis-label" x="30" y="<?=$gridY + 4?>" text-anchor="end"><?=$gridValue?></text>
                        <?php endfor; ?>
                        <path class="chart-area" d="<?=e($chartArea)?>"/>
                        <path class="chart-line" d="<?=e($chartLine)?>"/>
                        <?php foreach ($chartPoints as $point): ?>
                            <circle class="chart-dot" cx="<?=$point['x']?>" cy="<?=$point['y']?>" r="4"/>
                            <text class="chart-month-label" x="<?=$point['x']?>" y="202" text-anchor="middle"><?=e($point['label'])?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </article>

            <article class="dashboard-card security-card">
                <div class="dashboard-card-heading">
                    <div><h2>Informasi Keamanan</h2><p>Perlindungan penyimpanan</p></div>
                </div>
                <div class="security-status-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <path d="M24 4 40 10v12c0 10-6.8 17.4-16 22C14.8 39.4 8 32 8 22V10L24 4Z" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
                        <path d="m17 23 5 5 10-11" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <strong class="security-status-title"><?=$total > 0 ? 'Sistem Aman' : 'Sistem Siap'?></strong>
                <p>Isi file dilindungi AES-256-GCM dan kunci AES dibungkus RSA-3072.</p>
                <a class="security-link" href="files.php">Lihat file <span aria-hidden="true">→</span></a>
            </article>
        </section>

        <section class="dashboard-card recent-card">
            <div class="dashboard-card-heading">
                <div><h2>File Terbaru</h2><p>Aktivitas penyimpanan terakhir</p></div>
                <a class="dashboard-link" href="files.php">Kelola semua <span aria-hidden="true">→</span></a>
            </div>
            <?php if (!$recent): ?>
                <div class="dashboard-empty">Belum ada file. <a href="upload.php">Unggah file pertama</a> untuk memulai.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Nama file</th><th>Ukuran</th><th>Tanggal</th><th>Status</th></tr></thead>
                        <tbody><?php foreach ($recent as $file): ?>
                            <tr>
                                <td><strong><?=e($file['title'])?></strong><small><?=e($file['original_name'])?></small></td>
                                <td><?=e(format_bytes((int)$file['file_size']))?></td>
                                <td><?=e($file['created_at'])?></td>
                                <td><span class="dashboard-pill">● Terenkripsi</span></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
