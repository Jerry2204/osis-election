# Panduan Setup Suara OSIS dengan Laragon

Panduan ini memakai **Laragon GUI** untuk menyalakan Apache dan MySQL. Proyek menggunakan Laravel 12, PHP 8.2+, MySQL 8.0.16+, dan Composer. Node.js tidak diperlukan.

## 1. Pasang dependensi proyek

Buka terminal Laragon atau PowerShell di folder proyek, lalu jalankan:

```powershell
composer install
```

Perintah ini membuat folder `vendor`. Internet diperlukan saat mengunduh dependensi, tetapi tidak diperlukan saat pemilihan berlangsung.

## 2. Nyalakan Laragon dan siapkan database

Di aplikasi Laragon, klik **Start All**. Pastikan Apache dan MySQL berstatus berjalan. Buka menu **Database** di Laragon, lalu buat database kosong bernama `osis_election` dengan collation `utf8mb4_bin`.

## 3. Isi konfigurasi proyek

Jika `.env` belum ada, salin `.env.example` menjadi `.env`:

```powershell
Copy-Item .env.example .env
```

Isi bagian berikut sesuai MySQL Laragon dan alamat web yang akan dipakai. Contoh untuk Apache pada port 8080:

```dotenv
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osis_election
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` jika akun MySQL Laragon berbeda. Gunakan URL lengkap dengan `http://`, bukan hanya `localhost`. Jika `.env` sudah ada, cukup periksa nilainya; jangan menimpanya.

## 4. Siapkan Laravel dan tabel database

Jika `APP_KEY` di `.env` masih kosong, jalankan `php artisan key:generate` sekali. Setelah itu, jalankan di folder proyek:

```powershell
php artisan migrate --force
php artisan election:teacher-url --generate
```

Migrasi membuat tabel, sedangkan perintah terakhir membuat kode akses guru jika belum ada. Jangan ganti `APP_KEY` atau kode guru setelah pemilihan mulai digunakan.

## 5. Arahkan Apache Laragon ke folder `public`

Proyek ini berada di luar `C:\laragon\www`, sehingga Laragon tidak otomatis membuat alamat web untuknya. Buat file `C:\laragon\etc\apache2\sites-enabled\osis-election.conf` berisi:

```apache
Listen 8080
<VirtualHost *:8080>
    ServerName localhost
    DocumentRoot "D:/vibe-coding/osis-election/public"
    <Directory "D:/vibe-coding/osis-election/public">
        AllowOverride None
        Options -Indexes
        Require all granted
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

Sesuaikan path jika folder proyek dipindahkan. Di Laragon, klik **Stop All**, lalu **Start All** agar Apache membaca konfigurasi baru. Pastikan port 8080 belum dipakai aplikasi lain. Folder web harus menunjuk ke `public`, bukan ke folder utama proyek.

## 6. Buka aplikasi

- Halaman siswa di laptop server: `http://localhost:8080`.
- Untuk melihat alamat guru, jalankan `php artisan election:teacher-url`. Bagikan alamat yang muncul hanya kepada guru.

Di halaman guru, impor data siswa lewat **Data siswa** (template ada di `docs/template-siswa.csv`), tambahkan calon lewat **Kelola calon**, lalu atur pemilihan.

## 7. Jika diakses dari laptop siswa lain

Cari IP lokal laptop server dengan `ipconfig`. Ubah `APP_URL` di `.env` menjadi, misalnya, `http://192.168.1.10:8080`, lalu jalankan `php artisan config:clear`. Laptop siswa membuka alamat itu. Izinkan TCP 8080 di Windows Firewall untuk jaringan **Private**. MySQL tetap di laptop server; laptop siswa tidak perlu memasang Laragon.

## 8. Jalankan scheduler saat pemilihan

Laragon GUI menyalakan Apache dan MySQL, tetapi tidak menjalankan scheduler Laravel. Untuk pemeriksaan jadwal dan backup otomatis, jalankan:

```powershell
.\scripts\start-scheduler.ps1
```

Skrip ini memakai PHP di `C:\laragon\bin\php\php-8.3.14` secara bawaan. Jika lokasinya berbeda, tambahkan `-PhpBase "PATH-FOLDER-PHP"`. Setelah kegiatan selesai, jalankan `.\scripts\stop-scheduler.ps1`, lalu klik **Stop All** di Laragon.

Lihat [README utama](../README.md) untuk pengaturan backup, jaringan, dan operasional pemilihan yang lebih lengkap.
