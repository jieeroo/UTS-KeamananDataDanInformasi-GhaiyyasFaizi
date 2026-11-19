<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard Simulasi Ciphertext · GameVault</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#080808;color:#eee;font:15px/1.5 Arial,sans-serif}.layout{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:100vh}.side{background:#101010;border-right:1px solid #282828;padding:28px 18px}.brand{font-size:20px;font-weight:800;color:#f33}.brand small{display:block;color:#aaa;font-size:10px;letter-spacing:2px}.nav{margin-top:54px;color:#aaa}.nav div{padding:13px 14px;border-radius:9px;margin:6px 0}.nav .active{background:#331010;color:#ff5555;border-left:3px solid #e22}.status{position:absolute;bottom:18px;color:#aaa;font-size:12px}.status i{display:inline-block;width:8px;height:8px;background:#35b987;border-radius:50%;margin-right:8px}.main{min-width:0}.top{display:flex;justify-content:space-between;align-items:center;padding:28px 38px;border-bottom:1px solid #252525}.top small,.eyebrow{color:#999;letter-spacing:2px;font-size:10px}.top h1{margin:4px 0 0;font-size:24px}.guest{color:#ff6666}.content{padding:38px;max-width:1300px;margin:auto}.hero{border-bottom:1px solid #333;padding-bottom:26px;margin-bottom:26px}.cipher{overflow-wrap:anywhere;font:13px/1.7 Consolas,monospace;color:#aaa}.hero h2{font-size:26px;overflow-wrap:anywhere;margin:8px 0}.label{color:#aaa;font-size:12px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid #292929;border-radius:10px;overflow:hidden;margin:26px 0}.cell{padding:22px 24px;border-right:1px solid #292929}.cell:last-child{border:0}.cell strong{display:block;font:700 18px Consolas,monospace;overflow-wrap:anywhere;margin:8px 0}.card{border:1px solid #292929;border-radius:10px;padding:22px;margin-top:18px}.card h3{margin:3px 0 8px;overflow-wrap:anywhere}.muted{color:#999;font-size:12px}.notice{padding:12px 15px;background:#171717;border-left:3px solid #35b987;color:#bbb;margin-bottom:26px}.logout{color:#f66;text-decoration:none}@media(max-width:760px){.layout{grid-template-columns:1fr}.side{display:none}.content{padding:22px 18px}.top{padding:20px 18px}.grid{grid-template-columns:1fr}.cell{border-right:0;border-bottom:1px solid #292929}.cell:last-child{border-bottom:0}.top h1{font-size:20px}}
    </style>
    <style>
        body{background:#070c18;color:#e8eefb;font-family:Inter,system-ui,Arial,sans-serif}.side{background:#0b1427;border-color:#223553}.brand{color:#f1edff}.brand small,.top small,.eyebrow{color:#91a1bc}.nav{color:#b7c3d8}.nav .active{background:#211845;color:#c4a7ff;border-left-color:#8b5cf6}.status{color:#9daac0}.status i{background:#34d399}.top{border-color:#223553}.guest{color:#c4a7ff}.logout{color:#a78bfa}.content{color:#e8eefb}.hero{border-color:#223553}.cipher{color:#a9b8d1}.grid,.card{border-color:#263a5c;background:#0b1427}.cell{border-color:#263a5c}.muted{color:#93a1b8}.notice{background:#111d34;border-left-color:#8b5cf6;color:#c4cee0}
    </style>
</head>
<body>
<div class="layout">
    <aside class="side"><div class="brand">GAMEVAULT<small>DATA PROTECTION SYSTEM</small></div><div class="nav"><div class="active">▦ &nbsp; Dashboard</div><div>▤ &nbsp; Data Terenkripsi</div><div>◷ &nbsp; Riwayat Aktivitas</div></div><div class="status"><i></i>Mode simulasi aktif</div></aside>
    <main class="main">
        <header class="top"><div><small>GAMEVAULT / SIMULASI</small><h1>CIPHER DEMO</h1></div><div class="guest">Tamu · Akses terbatas &nbsp; <a class="logout" href="login.php">Keluar</a></div></header>
        <div class="content">
            <div class="notice">Mode simulasi. Data asli, riwayat, dekripsi, dan fitur pengelolaan admin tidak diakses.</div>
            <section class="hero"><div class="cipher">Q0lQSEVSVEVYVC1ERU1PLTAx</div><h2>SGFtYmFyYW4gZGFzaGJvYXJkIG1vZGUgc2ltdWxhc2k=</h2><div class="cipher">U2FtcGxlIGNpcGhlcnRleHQgR2FtZVZhdWx0</div></section>
            <div class="grid"><div class="cell"><div class="eyebrow">INFORMASI DATA</div><strong>R0FNRVZBVUxULURFTU8=</strong><div class="muted">Data asli tidak ditampilkan</div></div><div class="cell"><div class="eyebrow">ALGORITMA ENKRIPSI</div><strong>QUVTLTI1Ni1HQ00=</strong><div class="muted">Informasi dalam mode simulasi</div></div><div class="cell"><div class="eyebrow">PERLINDUNGAN KUNCI</div><strong>UlNBLTMwNzI=</strong><div class="muted">Akses administrator tidak diberikan</div></div></div>
            <section class="card"><div class="cipher">c2ltdWxhc2ktYWtzZXM=</div><h3>SIMULASI AKSES TERBATAS</h3><div class="muted">Data contoh ditampilkan sebagai ciphertext. Sesi admin tidak dibuat.</div></section>
            <section class="card"><div class="eyebrow">CIPHER MODE</div><h3>INFORMASI TERENKRIPSI</h3><div class="cipher">VGhpcyBpcyBhIGxvY2FsIGRlbW8gY2lwaGVydGV4dCB2aWV3Lg==</div><div class="muted">Mode simulasi. Data contoh saja.</div></section>
        </div>
    </main>
</div>
<script>
(() => {
    const encoder = new TextEncoder();
    const encode = value => {
        const bytes = encoder.encode(value);
        let binary = '';
        for (const byte of bytes) binary += String.fromCharCode(byte);
        return btoa(binary);
    };
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
        const value = node.nodeValue;
        const visible = value.trim();
        if (visible) node.nodeValue = value.replace(visible, encode(visible));
    }
})();
</script>
</body>
</html>
