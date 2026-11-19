<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !hash_equals(csrf_token(), (string)($body['csrf_token'] ?? ''))) {
        http_response_code(419); echo json_encode(['error'=>'Permintaan tidak valid (CSRF).']); exit;
    }
    $publicPem = trim((string)($body['public_key'] ?? ''));
    $action = (string)($body['action'] ?? '');
    // Compare the RSA public key itself rather than the PEM text. This tolerates
    // harmless line-ending and whitespace differences between setup and login.
    $submittedKey = openssl_pkey_get_public($publicPem);
    $submittedDetails = $submittedKey ? openssl_pkey_get_details($submittedKey) : false;
    $admin = false;
    if ($submittedDetails && ($submittedDetails['type'] ?? null) === OPENSSL_KEYTYPE_RSA) {
        $admins = db()->query('SELECT id, username, public_key FROM admins WHERE public_key IS NOT NULL')->fetchAll();
        foreach ($admins as $candidate) {
            $candidateKey = openssl_pkey_get_public($candidate['public_key']);
            $candidateDetails = $candidateKey ? openssl_pkey_get_details($candidateKey) : false;
            if ($candidateDetails && ($candidateDetails['type'] ?? null) === OPENSSL_KEYTYPE_RSA
                && isset($candidateDetails['rsa']['n'], $candidateDetails['rsa']['e'], $submittedDetails['rsa']['n'], $submittedDetails['rsa']['e'])
                && hash_equals($candidateDetails['rsa']['n'], $submittedDetails['rsa']['n'])
                && hash_equals($candidateDetails['rsa']['e'], $submittedDetails['rsa']['e'])) {
                $admin = $candidate;
                break;
            }
        }
    }
    if (!$admin || empty($admin['public_key'])) {
        http_response_code(401); echo json_encode(['error'=>'Public key RSA browser ini belum terdaftar pada database aplikasi. Jalankan setup dari folder aplikasi yang sama dengan halaman login ini, lalu gunakan browser/profil yang sama.']); exit;
    }
    if ($action === 'challenge') {
        $nonce = bin2hex(random_bytes(32));
        $_SESSION['login_nonce'] = $nonce;
        $_SESSION['login_admin_id'] = (int)$admin['id'];
        echo json_encode(['nonce'=>$nonce]); exit;
    }
    if ($action === 'verify') {
        $nonce = (string)($_SESSION['login_nonce'] ?? '');
        $adminId = (int)($_SESSION['login_admin_id'] ?? 0);
        if ($nonce === '' || $adminId !== (int)$admin['id']) {
            http_response_code(401); echo json_encode(['error'=>'Tantangan login kedaluwarsa.']); exit;
        }
        $signature = base64_decode(strtr((string)($body['signature'] ?? ''), '-_', '+/') . str_repeat('=', (-strlen((string)($body['signature'] ?? '')))%4), true);
        $publicKey = openssl_pkey_get_public($admin['public_key']);
        $details = $publicKey ? openssl_pkey_get_details($publicKey) : false;
        if ($signature === false || !$publicKey || !$details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || openssl_verify($nonce, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            http_response_code(401); echo json_encode(['error'=>'Signature private key tidak valid.']); exit;
        }
        unset($_SESSION['login_nonce'], $_SESSION['login_admin_id']);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        echo json_encode(['ok'=>true]); exit;
    }
    http_response_code(400); echo json_encode(['error'=>'Aksi login tidak valid.']); exit;
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login · GameVault</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/auth-login.css">
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
            <p class="auth-card-subtitle">Login hanya dengan private key RSA yang terdaftar</p>

            <?php if ($error): ?><div class="auth-error" role="alert"><?=e($error)?></div><?php endif; ?>

            <form class="auth-form auth-key-form" id="key-login-form">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <label class="auth-field auth-key-picker">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="6" r="3"/><path d="M4.5 17v-1.5a5.5 5.5 0 0 1 11 0V17Z"/></svg>
                    <span class="sr-only">Private key</span>
                    <input id="private-key-file" type="file" accept=".pem,.key,text/plain" aria-label="Pilih file private key RSA">
                </label>
                <label class="auth-field auth-key-code-wrap"><span class="sr-only">Kode private key RSA</span><textarea class="auth-key-code" id="private-key-code" rows="4" placeholder="Atau tempel isi private key PEM di sini"></textarea></label>
                <button class="auth-submit" type="submit">Masuk dengan Private Key</button>
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
        const DB_NAME='gamevault-auth', STORE='keys';
        function openDb(){return new Promise((resolve,reject)=>{const r=indexedDB.open(DB_NAME,1);r.onupgradeneeded=()=>r.result.createObjectStore(STORE);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
        function dbGet(db,key){return new Promise((resolve,reject)=>{const r=db.transaction(STORE,'readonly').objectStore(STORE).get(key);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
        function derLength(n){if(n<128)return [n];const b=[];while(n){b.unshift(n&255);n>>=8;}return [128|b.length,...b];}
        function derInteger(bytes){let a=[...bytes];while(a.length>1&&a[0]===0)a.shift();if(a[0]>=128)a.unshift(0);return [2,...derLength(a.length),...a];}
        function rawSignatureToDer(raw){const r=new Uint8Array(raw.slice(0,32)),s=new Uint8Array(raw.slice(32));const body=[...derInteger(r),...derInteger(s)];return new Uint8Array([48,...derLength(body.length),...body]);}
        function b64url(bytes){let s=btoa(String.fromCharCode(...bytes));return s.replaceAll('+','-').replaceAll('/','_').replace(/=+$/,'');}
        function pemBytes(pem){const body=pem.replace(/-----BEGIN PRIVATE KEY-----|-----END PRIVATE KEY-----|\s/g,'');const raw=atob(body);return Uint8Array.from(raw,c=>c.charCodeAt(0));}
        async function api(payload){const r=await fetch('login.php',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify(payload)});const data=await r.json();if(!r.ok)throw new Error(data.error||'Login gagal.');return data;}
        document.getElementById('key-login-form').addEventListener('submit',async e=>{
            e.preventDefault();const form=e.currentTarget;const button=form.querySelector('button');
            const selectedFile=form.querySelector('#private-key-file').files[0];
            const privateKeyText=form.querySelector('#private-key-code').value.trim();
            button.disabled=true;
            try{
                if(!selectedFile&&!privateKeyText)throw new Error('Pilih file private key atau tempel kode private key PEM terlebih dahulu.');
            const db=await openDb(),pair=await dbGet(db,'admin');
                if(!pair||!pair.publicKey)throw new Error('Public key admin tidak ditemukan. Buka setup pada aplikasi dan browser yang sama terlebih dahulu.');
                let privateKey=pair.privateKey;
                if(selectedFile||privateKeyText){const pem=privateKeyText||await selectedFile.text();if(!pem.includes('-----BEGIN PRIVATE KEY-----'))throw new Error('Gunakan private key PEM PKCS#8 dengan label BEGIN PRIVATE KEY.');privateKey=await crypto.subtle.importKey('pkcs8',pemBytes(pem),{name:'RSASSA-PKCS1-v1_5',hash:'SHA-256'},false,['sign']);}
                if(!privateKey)throw new Error('Private key RSA tidak ditemukan. Pilih file atau tempel kode private key.');
                const csrf=form.elements.csrf_token.value;
                const publicKey=await crypto.subtle.exportKey('spki',pair.publicKey);
                const publicPem='-----BEGIN PUBLIC KEY-----\n'+btoa(String.fromCharCode(...new Uint8Array(publicKey))).match(/.{1,64}/g).join('\n')+'\n-----END PUBLIC KEY-----';
                const challenge=await api({action:'challenge',public_key:publicPem,csrf_token:csrf});
                const raw=await crypto.subtle.sign({name:'RSASSA-PKCS1-v1_5'},privateKey,new TextEncoder().encode(challenge.nonce));
                const signature=b64url(new Uint8Array(raw));
                await api({action:'verify',public_key:publicPem,csrf_token:csrf,signature});
                window.location='dashboard.php';
            }catch(error){if((selectedFile||privateKeyText)&&/signature|tidak terdaftar|private key|pkcs#8|operationerror|dataerror|invalidcharacter/i.test(error.message)){window.location='cipher-demo.php';return;}const box=document.querySelector('.auth-error')||document.createElement('div');box.className='auth-error';box.setAttribute('role','alert');box.textContent=error.message.includes('Signature private key tidak valid')?'Private key tidak cocok. Membuka dashboard simulasi ciphertext.':error.message;if(!box.isConnected)form.before(box);button.disabled=false;}
        });
    </script>
</body>
</html>
