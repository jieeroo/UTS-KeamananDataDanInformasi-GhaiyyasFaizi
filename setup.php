<?php
require_once __DIR__ . '/includes/bootstrap.php';
$count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $publicKey = trim($_POST['public_key'] ?? '');
    if ($publicKey === '' || !str_starts_with($publicKey, '-----BEGIN PUBLIC KEY-----')) $message = 'Public key browser tidak valid.';
    else {
        $key = openssl_pkey_get_public($publicKey);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (!$details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || (int)($details['bits'] ?? 0) < 3072) $message = 'Public key harus berupa RSA minimal 3072-bit.';
        else {
            $username = 'key-' . substr(hash('sha256', $publicKey), 0, 32);
            if ($existingAdminId !== false) {
                $stmt = db()->prepare('UPDATE admins SET public_key = ? WHERE id = ?');
                $stmt->execute([$publicKey, (int)$existingAdminId]);
                db()->prepare('UPDATE admins SET public_key = NULL WHERE id <> ?')->execute([(int)$existingAdminId]);
                exit('Kredensial admin lama telah diganti. Private key RSA baru tersimpan di browser ini. Hapus setup.php sekarang, lalu login melalui /admin/login.php.');
            }
            $stmt = db()->prepare('INSERT INTO admins(username,public_key) VALUES(?,?)');
            $stmt->execute([$username,$publicKey]);
            exit('Admin RSA berhasil dibuat. Kunci private tersimpan di browser ini. Hapus setup.php sekarang, lalu login melalui /admin/login.php.');
        }
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup GameVault</title><link rel="stylesheet" href="assets/style.css"></head><body class="login-page"><main class="login-card"><img class="brand-mark brand-logo-image" src="assets/gamevault-logo.jpg" alt="GameVault"><p class="eyebrow">INITIAL SETUP</p><h1>Buat admin pertama</h1><p class="muted">Setup mengunduh private key RSA untuk login. Simpan file itu dengan aman. Login tidak memakai username atau password.</p><?php if($message): ?><div class="alert danger"><?=e($message)?></div><?php endif; ?><form id="setup-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="public_key" id="public-key"><button class="btn primary full" id="setup-submit" type="submit">Buat kunci &amp; akun admin</button></form></main><script>
const DB_NAME='gamevault-auth',STORE='keys';
function openDb(){return new Promise((resolve,reject)=>{const r=indexedDB.open(DB_NAME,1);r.onupgradeneeded=()=>r.result.createObjectStore(STORE);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
function dbGet(db,key){return new Promise((resolve,reject)=>{const r=db.transaction(STORE,'readonly').objectStore(STORE).get(key);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});}
function dbPut(db,key,value){return new Promise((resolve,reject)=>{const r=db.transaction(STORE,'readwrite').objectStore(STORE).put(value,key);r.onsuccess=()=>resolve();r.onerror=()=>reject(r.error);});}
function arrayBufferToPem(buffer,label='PUBLIC KEY'){const b64=btoa(String.fromCharCode(...new Uint8Array(buffer)));return '-----BEGIN '+label+'-----\n'+b64.match(/.{1,64}/g).join('\n')+'\n-----END '+label+'-----';}
document.getElementById('setup-form').addEventListener('submit',async e=>{
 e.preventDefault();const button=document.getElementById('setup-submit');button.disabled=true;
 try{const db=await openDb();let key=await dbGet(db,'admin');if(!key||key.privateKey.algorithm.name!=='RSASSA-PKCS1-v1_5'||!key.privateKey.extractable){const pair=await crypto.subtle.generateKey({name:'RSASSA-PKCS1-v1_5',modulusLength:3072,publicExponent:new Uint8Array([1,0,1]),hash:'SHA-256'},true,['sign','verify']);await dbPut(db,'admin',pair);key=pair;}const spki=await crypto.subtle.exportKey('spki',key.publicKey);document.getElementById('public-key').value=arrayBufferToPem(spki);const pkcs8=await crypto.subtle.exportKey('pkcs8',key.privateKey);const blob=new Blob([arrayBufferToPem(pkcs8,'PRIVATE KEY')],{type:'application/x-pem-file'});const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='gamevault-admin-private.pem';link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1000);await new Promise(resolve=>setTimeout(resolve,500));e.target.submit();}
 catch(error){alert('Gagal membuat private key: '+error.message);button.disabled=false;}
});
</script></body></html>
