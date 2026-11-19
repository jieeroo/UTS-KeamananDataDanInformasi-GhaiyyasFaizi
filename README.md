# GameVault — Aplikasi File Game Rahasia

Aplikasi PHP Native + MySQL untuk menyimpan file game dengan hybrid encryption:
- AES-256-GCM: mengenkripsi konten file dan memverifikasi integritas.
- RSA-3072 OAEP (SHA-1 default pada API OpenSSL PHP yang dipakai): mengenkripsi (wrap) kunci AES acak per file.
- Enkripsi teks: judul dan isi dienkripsi AES-256-GCM, sementara kunci AES dibungkus RSA-3072-OAEP. Database hanya menyimpan ciphertext dan metadata enkripsinya.
- Login admin: tanda tangan challenge dengan RSA-3072; private key disimpan di IndexedDB browser. AES-256-GCM dipakai untuk mengenkripsi file dan teks.

## Kebutuhan
- PHP 8.1+ dengan ekstensi `pdo_mysql`, `openssl`, `fileinfo`
- MySQL 5.7+ / MariaDB
- Apache (XAMPP/Laragon)

## Instalasi XAMPP
1. Ekstrak folder `aplikasi-file-game-rahasia` ke `C:/xampp/htdocs/`.
2. Jalankan Apache dan MySQL.
3. Buka phpMyAdmin, import `database.sql`.
4. Periksa `config/config.php` (host, database, username, password).
5. Pastikan `storage/encrypted` dan `storage/keys` dapat ditulis oleh PHP. Di Linux gunakan permission ketat; jangan beri akses publik.
6. Buka `http://localhost/aplikasi-file-game-rahasia/`.

## Membuat akun admin pertama
1. Buka `http://localhost/aplikasi-file-game-rahasia/setup.php`.
2. Buat username dan password kuat (minimal 12 karakter).
3. Setelah akun berhasil dibuat, **hapus `setup.php`** dari folder proyek.
4. Login melalui halaman admin.

## Alur kriptografi
Upload -> PHP membuat AES key random 256-bit dan IV random 96-bit -> AES-256-GCM menghasilkan ciphertext + tag -> public RSA mengenkripsi AES key dengan OAEP -> file ciphertext disimpan di `storage/encrypted`, metadata kriptografi disimpan di MySQL.
Download -> private RSA membuka AES key -> AES-GCM memverifikasi tag dan mendekripsi file -> file dikirim sebagai attachment -> temporary plaintext dihapus. Setelah rotasi RSA dari Pengaturan, private key lama disimpan di `storage/keys/archive/` dan tetap dicoba untuk membuka data yang dibuat dengan pasangan sebelumnya.

Enkripsi teks -> judul dan isi digabung sebagai JSON, dienkripsi dengan AES-256-GCM, lalu kunci AES dibungkus RSA-OAEP. Ciphertext, wrapped key, IV, dan tag disimpan di tabel `encrypted_texts`; plaintext hanya dibuka sementara saat arsip ditampilkan.

## Catatan penting
- Pasangan kunci RSA dibuat otomatis pada upload atau entri teks terenkripsi pertama. Kunci aktif berada di `storage/keys/private.pem`; rotasi dari Pengaturan mengarsipkan private key sebelumnya di `storage/keys/archive/` agar data lama tetap dapat dibuka. Lindungi dan backup seluruh folder `storage/keys` secara offline/aman. Kehilangan semua private key aktif dan arsip berarti data terenkripsi terkait tidak dapat didekripsi.
- Jangan simpan `storage/` di dalam document root pada produksi. Contoh: pindahkan ke direktori di luar `public_html`, lalu sesuaikan `STORAGE_PATH` dan `KEY_PATH`.
- `MAX_UPLOAD_BYTES` saat ini 100 MB. Sesuaikan juga `upload_max_filesize` dan `post_max_size` pada php.ini.
- Untuk produksi, aktifkan HTTPS, gunakan password admin kuat, nonaktifkan directory listing, batasi akses admin, tambahkan rate limiting dan audit log.
- Demo ini mengenkripsi file biner penuh di memori; batas ukuran perlu disesuaikan dengan RAM. Untuk file sangat besar implementasikan streaming/chunked authenticated encryption.
- Metadata file game (judul, deskripsi, nama asli, ukuran, MIME, waktu) belum dienkripsi. Konten file dan kunci AES terlindungi. Judul dan isi pada fitur Enkripsi Teks disimpan terenkripsi.
- RSA-OAEP menggunakan padding OAEP default OpenSSL. Implementasi PHP openssl_public_encrypt/openssl_private_decrypt dengan OPENSSL_PKCS1_OAEP_PADDING memakai OAEP-SHA1 pada API ini. Untuk standar OAEP-SHA256, gunakan API/library yang memungkinkan parameter digest OAEP ditetapkan eksplisit.
