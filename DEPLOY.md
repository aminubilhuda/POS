# Panduan Deploy Production — NusaPOS Retail

## 1. Persyaratan Server
- PHP 8.3 atau 8.4 (ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- MySQL 8.0+ atau MariaDB 10.6+
- Composer 2+ & Node.js 20+
- SSL/HTTPS aktif (Let's Encrypt / Cloudflare)

## 2. Instalasi & Konfigurasi `.env`
```bash
cp .env.production.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
```
Sesuaikan koneksi MySQL (`DB_*`) dan `APP_URL` di `.env`, lalu jalankan migrasi **tanpa seeder demo**:
```bash
php artisan migrate --force
```

## 3. Pendaftaran Owner Pertama (Satu Kali)
- Buka `https://domain-anda.com` di browser.
- Karena tabel `users` masih kosong, sistem otomatis membuka halaman `/setup`.
- Isi data cabang pertama, akun Owner, dan PIN Admin (4–8 digit).
- Setelah selesai, halaman `/setup` **tertutup permanen** dan akses berikutnya hanya menampilkan `/login` (tanpa tombol akun demo).

## 4. Optimasi Cache Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Sertifikat QZ Tray (Printer Thermal 58mm & Cash Drawer)
Buat pasangan kunci RSA (self-signed gratis) di server:
```bash
mkdir -p storage/app/private/qz
openssl req -x509 -newkey rsa:2048 -keyout storage/app/private/qz/private-key.pem \
  -out storage/app/private/qz/digital-certificate.txt -days 3650 -nodes \
  -subj "/CN=NusaPOS Retail"
chmod 600 storage/app/private/qz/private-key.pem
```
- Salin file `digital-certificate.txt` ke setiap PC kasir dan tambahkan ke *QZ Tray → Site Manager*.
- Pastikan `QZ_PRIVATE_KEY_PATH` dan `QZ_CERTIFICATE_PATH` di `.env` mengarah ke file tersebut.

## 6. Jadwal Backup Otomatis (Cron)
Tambahkan baris berikut ke `crontab -e` di server agar scheduler Laravel menjalankan `php artisan pos:backup` otomatis setiap pukul 02:00 WIB:
```cron
* * * * * cd /var/www/pos && php artisan schedule:run >> /dev/null 2>&1
```
Backup tersimpan di `storage/app/private/backups/` dan otomatis dibersihkan setelah 14 hari (`POS_BACKUP_RETENTION_DAYS`).
