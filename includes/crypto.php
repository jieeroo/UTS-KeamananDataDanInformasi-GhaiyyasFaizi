<?php
/**
 * Hybrid encryption:
 * 1) File dienkripsi dengan AES-256-GCM (simetris).
 * 2) Kunci AES 32-byte dienkripsi dengan RSA-OAEP-SHA1 (asimetris).
 * 3) GCM authentication tag memverifikasi integritas dan keaslian ciphertext.
 */
function ensure_rsa_keys(): void {
    with_rsa_key_generation_lock(function (): void {
        $private = KEY_PATH . '/private.pem';
        $public = KEY_PATH . '/public.pem';
        $privateExists = is_file($private);
        $publicExists = is_file($public);
        if ($privateExists && $publicExists) return;
        if ($privateExists || $publicExists) {
            throw new RuntimeException('Pasangan kunci RSA tidak lengkap; kunci yang tersisa tidak akan ditimpa.');
        }

        $keyMaterial = create_rsa_key_material();
        install_rsa_key_material($keyMaterial['private'], $keyMaterial['public']);
    });
}

function rotate_rsa_keys(): void {
    with_rsa_key_generation_lock(function (): void {
        $privatePath = KEY_PATH . '/private.pem';
        $publicPath = KEY_PATH . '/public.pem';
        $privateExists = is_file($privatePath);
        $publicExists = is_file($publicPath);
        if ($privateExists !== $publicExists) {
            throw new RuntimeException('Pasangan kunci RSA tidak lengkap; pemulihan kunci diperlukan sebelum rotasi.');
        }

        $keyMaterial = create_rsa_key_material();
        if ($privateExists) {
            archive_active_rsa_private_key($privatePath, $publicPath);
        }
        install_rsa_key_material($keyMaterial['private'], $keyMaterial['public']);
    });
}

function with_rsa_key_generation_lock(callable $callback): void {
    if (!is_dir(KEY_PATH) && !@mkdir(KEY_PATH, 0700, true) && !is_dir(KEY_PATH)) {
        throw new RuntimeException('Folder penyimpanan kunci RSA tidak dapat dibuat.');
    }

    $lockPath = KEY_PATH . '/.key-generation.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) throw new RuntimeException('File pengunci pembuatan kunci RSA tidak dapat dibuka.');
    if (PHP_OS_FAMILY !== 'Windows') @chmod($lockPath, 0600);

    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Proses pembuatan kunci RSA sedang terkunci.');
        try {
            $callback();
        } finally {
            flock($lock, LOCK_UN);
        }
    } finally {
        fclose($lock);
    }
}

function with_rsa_key_read_lock(callable $callback) {
    $lockPath = KEY_PATH . '/.key-generation.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) throw new RuntimeException('File pengunci kunci RSA tidak dapat dibuka.');

    try {
        if (!flock($lock, LOCK_SH)) throw new RuntimeException('Kunci RSA sedang digunakan untuk rotasi.');
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
        }
    } finally {
        fclose($lock);
    }
}

function active_rsa_public_key() {
    return with_rsa_key_read_lock(function () {
        $publicPem = @file_get_contents(KEY_PATH . '/public.pem');
        if ($publicPem === false) throw new RuntimeException('Public key RSA aktif tidak dapat dibaca.');
        $publicKey = openssl_pkey_get_public($publicPem);
        if (!$publicKey) throw new RuntimeException('Public key RSA aktif tidak valid.');
        return $publicKey;
    });
}

function create_rsa_key_material(): array {
    while (openssl_error_string() !== false) {}
    $opensslOptions = [];
    $opensslConfig = getenv('OPENSSL_CONF');
    if (!is_string($opensslConfig) || !is_file($opensslConfig)) {
        $phpIni = php_ini_loaded_file();
        $phpDir = $phpIni !== false ? dirname($phpIni) : dirname(PHP_BINARY);
        $bundledConfig = $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($bundledConfig)) $opensslConfig = $bundledConfig;
        else $opensslConfig = '';
    }
    if (is_string($opensslConfig) && is_file($opensslConfig)) $opensslOptions['config'] = $opensslConfig;
    $config = array_merge(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072], $opensslOptions);
    $res = openssl_pkey_new($config);
    if ($res === false) throw new RuntimeException('Gagal membuat pasangan kunci RSA: ' . openssl_key_error());
    if (!openssl_pkey_export($res, $privatePem, null, $opensslOptions)) {
        throw new RuntimeException('Gagal mengekspor private key RSA: ' . openssl_key_error());
    }
    $details = openssl_pkey_get_details($res);
    if (!$details || empty($details['key']) || (int)$details['bits'] < 3072) {
        throw new RuntimeException('OpenSSL tidak menghasilkan kunci RSA minimal 3072-bit.');
    }

    return ['private' => $privatePem, 'public' => $details['key']];
}

function openssl_key_error(): string {
    $errors = [];
    while (($message = openssl_error_string()) !== false) $errors[] = $message;
    return $errors ? implode(' | ', $errors) : 'OpenSSL tidak memberikan rincian kesalahan.';
}

function write_key_file_atomically(string $path, string $contents, int $mode): void {
    $temporaryPath = dirname($path) . DIRECTORY_SEPARATOR . '.' . basename($path) . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $handle = @fopen($temporaryPath, 'xb');
    if ($handle === false) throw new RuntimeException('File sementara kunci RSA tidak dapat dibuat.');

    try {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Penulisan file kunci RSA tidak lengkap.');
            $offset += $written;
        }
        if (!fflush($handle)) throw new RuntimeException('File kunci RSA gagal disimpan ke disk.');
    } catch (Throwable $e) {
        fclose($handle);
        @unlink($temporaryPath);
        throw $e;
    }
    fclose($handle);

    if (PHP_OS_FAMILY !== 'Windows' && !@chmod($temporaryPath, $mode)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Izin file kunci RSA tidak dapat diamankan.');
    }
    if (!@rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        throw new RuntimeException('File kunci RSA tidak dapat dipasang.');
    }
}

function archive_active_rsa_private_key(string $privatePath, string $publicPath): void {
    $privatePem = @file_get_contents($privatePath);
    $publicPem = @file_get_contents($publicPath);
    if ($privatePem === false || $publicPem === false) {
        throw new RuntimeException('Kunci RSA aktif tidak dapat dibaca untuk diarsipkan.');
    }

    $privateKey = openssl_pkey_get_private($privatePem);
    $publicKey = openssl_pkey_get_public($publicPem);
    $privateDetails = $privateKey ? openssl_pkey_get_details($privateKey) : false;
    $publicDetails = $publicKey ? openssl_pkey_get_details($publicKey) : false;
    if (!$privateDetails || !$publicDetails
        || !isset($privateDetails['rsa']['n'], $privateDetails['rsa']['e'], $publicDetails['rsa']['n'], $publicDetails['rsa']['e'])
        || !hash_equals($privateDetails['rsa']['n'], $publicDetails['rsa']['n'])
        || !hash_equals($privateDetails['rsa']['e'], $publicDetails['rsa']['e'])) {
        throw new RuntimeException('Kunci RSA aktif tidak cocok; rotasi dibatalkan untuk melindungi data.');
    }

    $archiveDirectory = KEY_PATH . '/archive';
    if (!is_dir($archiveDirectory) && !@mkdir($archiveDirectory, 0700, true) && !is_dir($archiveDirectory)) {
        throw new RuntimeException('Folder arsip kunci RSA tidak dapat dibuat.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && !@chmod($archiveDirectory, 0700)) {
        throw new RuntimeException('Izin folder arsip private key tidak dapat diamankan.');
    }
    $fingerprint = hash('sha256', $publicPem);
    $archivePath = $archiveDirectory . '/private-' . $fingerprint . '.pem';
    if (is_file($archivePath)) {
        $archivedPem = @file_get_contents($archivePath);
        if ($archivedPem === false || !hash_equals(hash('sha256', $privatePem), hash('sha256', $archivedPem))) {
            throw new RuntimeException('Arsip kunci RSA lama tidak cocok; rotasi dibatalkan.');
        }
        return;
    }

    write_key_file_atomically($archivePath, $privatePem, 0600);
    $archivedPem = @file_get_contents($archivePath);
    if ($archivedPem === false || !hash_equals(hash('sha256', $privatePem), hash('sha256', $archivedPem))) {
        @unlink($archivePath);
        throw new RuntimeException('Arsip private key RSA gagal diverifikasi.');
    }
}

function install_rsa_key_material(string $privatePem, string $publicPem): void {
    $privatePath = KEY_PATH . '/private.pem';
    $publicPath = KEY_PATH . '/public.pem';
    $suffix = '.rotation-' . bin2hex(random_bytes(8));
    $temporaryPrivate = KEY_PATH . '/.private' . $suffix . '.tmp';
    $temporaryPublic = KEY_PATH . '/.public' . $suffix . '.tmp';
    $backupPrivate = $privatePath . $suffix;
    $backupPublic = $publicPath . $suffix;
    $privateBackedUp = false;
    $publicBackedUp = false;
    $privateInstalled = false;
    $publicInstalled = false;

    try {
        write_key_file_atomically($temporaryPrivate, $privatePem, 0600);
        write_key_file_atomically($temporaryPublic, $publicPem, 0644);

        if (is_file($privatePath)) {
            if (!@rename($privatePath, $backupPrivate)) throw new RuntimeException('Private key lama tidak dapat diamankan sementara.');
            $privateBackedUp = true;
        }
        if (is_file($publicPath)) {
            if (!@rename($publicPath, $backupPublic)) throw new RuntimeException('Public key lama tidak dapat diamankan sementara.');
            $publicBackedUp = true;
        }
        if (!@rename($temporaryPrivate, $privatePath)) throw new RuntimeException('Private key baru tidak dapat dipasang.');
        $privateInstalled = true;
        if (!@rename($temporaryPublic, $publicPath)) throw new RuntimeException('Public key baru tidak dapat dipasang.');
        $publicInstalled = true;
    } catch (Throwable $e) {
        if ($privateInstalled && is_file($privatePath)) @unlink($privatePath);
        if ($publicInstalled && is_file($publicPath)) @unlink($publicPath);
        $restoreFailed = false;
        if ($privateBackedUp && !@rename($backupPrivate, $privatePath)) $restoreFailed = true;
        if ($publicBackedUp && !@rename($backupPublic, $publicPath)) $restoreFailed = true;
        @unlink($temporaryPrivate);
        @unlink($temporaryPublic);
        if ($restoreFailed) {
            throw new RuntimeException('Rotasi gagal dan pemulihan file kunci memerlukan pemeriksaan manual.', 0, $e);
        }
        throw $e;
    }

    if ($privateBackedUp && !@unlink($backupPrivate)) error_log('GameVault: temporary private key backup could not be removed.');
    if ($publicBackedUp && !@unlink($backupPublic)) error_log('GameVault: temporary public key backup could not be removed.');
}

function decrypt_wrapped_aes_key(string $wrappedKey): string {
    return with_rsa_key_read_lock(function () use ($wrappedKey): string {
        $privatePaths = [KEY_PATH . '/private.pem'];
        $archiveDirectory = KEY_PATH . '/archive';
        if (is_dir($archiveDirectory)) {
            try {
                $archivePaths = [];
                foreach (new DirectoryIterator($archiveDirectory) as $item) {
                    if ($item->isFile() && preg_match('/^private-[a-f0-9]{64}\.pem$/', $item->getFilename())) {
                        $archivePaths[] = $item->getPathname();
                    }
                }
                sort($archivePaths, SORT_STRING);
                $privatePaths = array_merge($privatePaths, $archivePaths);
            } catch (UnexpectedValueException $e) {
                throw new RuntimeException('Arsip kunci RSA tidak dapat dibaca.', 0, $e);
            }
        }

        foreach ($privatePaths as $privatePath) {
            if (!is_file($privatePath)) continue;
            $privatePem = @file_get_contents($privatePath);
            if ($privatePem === false) continue;
            $privateKey = openssl_pkey_get_private($privatePem);
            if (!$privateKey) continue;
            if (openssl_private_decrypt($wrappedKey, $aesKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING) && strlen($aesKey) === 32) {
                return $aesKey;
            }
        }

        throw new RuntimeException('Tidak dapat membuka kunci AES dengan kunci RSA aktif maupun arsip.');
    });
}

function encrypt_file_hybrid(string $sourcePath, string $cipherPath): array {
    ensure_rsa_keys();
    $plain = file_get_contents($sourcePath);
    if ($plain === false) throw new RuntimeException('Tidak dapat membaca file unggahan.');

    $aesKey = random_bytes(32);
    $iv = random_bytes(12); // IV 96-bit direkomendasikan untuk GCM
    $tag = '';
    $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false) throw new RuntimeException('Enkripsi AES-GCM gagal.');

    $publicKey = active_rsa_public_key();
    if (!openssl_public_encrypt($aesKey, $wrappedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('Enkripsi kunci AES dengan RSA gagal.');
    }

    // Format file internal: magic + panjang wrapped key + wrapped key + IV + tag + ciphertext.
    $payload = "GVLT1" . pack('N', strlen($wrappedKey)) . $wrappedKey . $iv . $tag . $ciphertext;
    if (file_put_contents($cipherPath, $payload, LOCK_EX) === false) throw new RuntimeException('Gagal menyimpan ciphertext.');
    if (function_exists('sodium_memzero')) sodium_memzero($aesKey);

    return [
        'wrapped_key' => base64_encode($wrappedKey),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'algorithm' => 'AES-256-GCM + RSA-3072-OAEP-SHA1',
    ];
}

function decrypt_file_hybrid(string $cipherPath, string $destinationPath): void {
    ensure_rsa_keys();
    $payload = file_get_contents($cipherPath);
    if ($payload === false || substr($payload, 0, 5) !== 'GVLT1' || strlen($payload) < 5 + 4 + 384 + 12 + 16) {
        throw new RuntimeException('Format file terenkripsi tidak valid.');
    }
    $offset = 5;
    $length = unpack('Nlength', substr($payload, $offset, 4))['length'];
    $offset += 4;
    if ($length < 256 || $length > 1024 || strlen($payload) < $offset + $length + 28) {
        throw new RuntimeException('Metadata kunci terenkripsi rusak.');
    }
    $wrappedKey = substr($payload, $offset, $length); $offset += $length;
    $iv = substr($payload, $offset, 12); $offset += 12;
    $tag = substr($payload, $offset, 16); $offset += 16;
    $ciphertext = substr($payload, $offset);

    $aesKey = decrypt_wrapped_aes_key($wrappedKey);
    try {
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) throw new RuntimeException('Autentikasi GCM gagal: file mungkin dimodifikasi atau kunci salah.');
        if (file_put_contents($destinationPath, $plain, LOCK_EX) === false) {
            throw new RuntimeException('File hasil dekripsi tidak dapat disimpan.');
        }
    } finally {
        if (function_exists('sodium_memzero')) sodium_memzero($aesKey);
    }
}

function encrypt_text_hybrid(string $plainText): array {
    ensure_rsa_keys();

    $aesKey = random_bytes(32);
    $iv = random_bytes(12);
    $tag = '';
    try {
        $ciphertext = openssl_encrypt($plainText, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) throw new RuntimeException('Enkripsi teks AES-GCM gagal.');

        $publicKey = active_rsa_public_key();
        if (!openssl_public_encrypt($aesKey, $wrappedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Enkripsi kunci AES dengan RSA gagal.');
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'wrapped_key' => base64_encode($wrappedKey),
            'iv' => base64_encode($iv),
            'auth_tag' => base64_encode($tag),
            'algorithm' => 'AES-256-GCM + RSA-3072-OAEP-SHA1',
        ];
    } finally {
        if (function_exists('sodium_memzero')) sodium_memzero($aesKey);
    }
}

function decrypt_text_hybrid(array $encrypted): string {
    foreach (['ciphertext', 'wrapped_key', 'iv', 'auth_tag'] as $field) {
        if (!isset($encrypted[$field]) || !is_string($encrypted[$field])) {
            throw new RuntimeException('Metadata teks terenkripsi tidak lengkap.');
        }
    }
    $ciphertext = base64_decode($encrypted['ciphertext'], true);
    $wrappedKey = base64_decode($encrypted['wrapped_key'], true);
    $iv = base64_decode($encrypted['iv'], true);
    $tag = base64_decode($encrypted['auth_tag'], true);
    if ($ciphertext === false || $wrappedKey === false || $iv === false || $tag === false || strlen($iv) !== 12 || strlen($tag) !== 16) {
        throw new RuntimeException('Format metadata teks terenkripsi tidak valid.');
    }

    $aesKey = decrypt_wrapped_aes_key($wrappedKey);
    try {
        $plainText = openssl_decrypt($ciphertext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plainText === false) throw new RuntimeException('Autentikasi GCM gagal: data mungkin diubah atau kunci salah.');
        return $plainText;
    } finally {
        if (function_exists('sodium_memzero')) sodium_memzero($aesKey);
    }
}
