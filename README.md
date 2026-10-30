# Suara OSIS

Aplikasi pemilihan ketua OSIS untuk tiga laptop dalam jaringan lokal. Laravel 12, PHP 8.2+, MySQL 8.0.16+ dengan InnoDB, Apache 2.4. Semua CSS, JavaScript, font sistem, foto dan ilustrasi tersedia lokal. Tidak membutuhkan Node, build frontend, CDN atau internet saat pemilihan.

## Mulai pada laptop ini

PHP, Apache dan MySQL Laragon sudah tersedia. Database aplikasi telah disiapkan dalam tahap **persiapan**, tanpa siswa, calon atau suara contoh. Database pengujian terpisah.

Di PowerShell pada folder proyek:

```powershell
.\scripts\start-local.ps1
php artisan election:teacher-url
```

Buka `http://127.0.0.1:8080` untuk siswa pada server. Perintah kedua menampilkan URL guru yang tersimpan dalam `.env`. Salin alamat itu hanya kepada guru. Jika server sudah berjalan, tidak perlu menjalankan ulang skrip; gunakan browser langsung.

Skrip menggunakan PHP 8.3.14, MySQL 8.0.30 dan Apache 2.4.54 di Laragon yang terpasang. Untuk lokasi/versi lain, tentukan `-PhpBase`, `-MySqlBase` dan `-ApacheBase`. Untuk pratinjau tanpa akses LAN gunakan `-LoopbackOnly`. Untuk port lain gunakan `-Port 8081` dan sesuaikan `APP_URL`.

MySQL milik proyek memakai `127.0.0.1:3307`, data `.runtime/mysql`, dan akun database terpisah dengan password acak. Skrip tidak mengubah konfigurasi global Laragon. `.runtime`, `.env` dan `.env.testing` tidak masuk Git. Jangan menghapus `.runtime/mysql` karena berisi data pemilihan.

Berhenti secara bersih, setelah seluruh permintaan selesai:

```powershell
.\scripts\stop-local.ps1
```

## Instalasi manual pada server lain

1. Pasang PHP beserta ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `dom`, `xml`, `curl` dan `zip`; Apache; MySQL; serta Composer. `mysqldump` dan `mysql` harus tersedia di PATH untuk backup dan uji pemulihan.
2. Jalankan `composer install --no-dev --optimize-autoloader` ketika internet tersedia. Sertakan folder `vendor` jika memindahkan aplikasi siap pakai ke laptop offline.
3. Salin `.env.example` ke `.env`. Isi konfigurasi MySQL dan `APP_URL`. Gunakan database kosong khusus aplikasi dengan `utf8mb4_bin`, InnoDB dan akun khusus aplikasi, bukan akun root. Akun membutuhkan SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX dan REFERENCES pada database aplikasi untuk pemasangan/migrasi.
4. Jalankan perintah berikut:

```powershell
php artisan key:generate
php artisan migrate --force
php artisan election:teacher-url --generate
php artisan view:cache
```

5. Pastikan `storage` dan `bootstrap/cache` dapat ditulis akun yang menjalankan Apache. Atur `APP_DEBUG=false`, `APP_ENV=production`, sesi file, dan cache file seperti contoh `.env`.
6. Arahkan document root Apache **hanya** ke `public`, dengan fallback ke `public/index.php`. Contoh konfigurasi Apache mandiri dihasilkan `scripts/start-local.ps1` ke `.runtime/httpd.conf`. Konfigurasi itu juga menonaktifkan directory listing, membatasi unggahan, dan menghindari URL/NIS/pilihan dalam access log.

Perintah `election:teacher-url --generate` hanya membuat kode ketika belum tersedia. Kode disimpan tetap di `.env`; tidak berubah saat restart. Jangan menjalankan `key:generate` atau mengganti kode guru saat pemilihan berlangsung. Setelah perubahan `.env` pada konfigurasi yang di-cache, jalankan `php artisan config:clear` di luar jam pemilihan.

## Data siswa dan calon

### Impor siswa melalui Ruang Guru

Buka URL guru, lalu pilih menu **Data siswa** (`/ruang-guru/{teacherKey}/siswa`). Klik **Unduh template CSV**, isi data siswa di bawah header, pilih berkas tersebut, lalu klik **Periksa dan pratinjau**. Periksa jumlah data, 20 contoh baris pertama, dan pesan kesalahan. Jika seluruh data valid, klik **Simpan impor**. Unggahan belum menyimpan siswa sampai konfirmasi ini. Template kosong tersedia di `docs/template-siswa.csv`. Pratinjau terikat sesi guru, berlaku 30 menit, dan dapat dibatalkan. Konfirmasi selalu memeriksa ulang data serta kesiapan pemilihan.

Daftar menampilkan 50 siswa per halaman. Gunakan pencarian NIS/nama, filter kelas, dan status sudah/belum memilih secara bersamaan; filter tetap aktif saat berpindah halaman. Status partisipasi tidak menampilkan calon yang dipilih siswa.

Impor hanya tersedia saat persiapan. Jika ada kesalahan, halaman menampilkan nomor baris untuk diperbaiki dan tidak menyimpan bagian mana pun dari berkas. NIS duplikat dalam berkas maupun yang sudah terdaftar membatalkan seluruh impor; data lama tidak ditimpa. Setelah pemilihan dibuka atau ditutup, daftar tetap dapat dilihat tetapi impor dikunci.

### Impor siswa melalui terminal (alternatif)

Siapkan CSV UTF-8 dengan pemisah koma dan header persis `nis,nama,kelas`. Contoh format tersedia di `docs/contoh-siswa.csv`; berkas tersebut **hanya contoh**, bukan data resmi.

```csv
nis,nama,kelas
001001,Alya Putri,X A
001002,Bima Pratama,X B
```

```powershell
php artisan students:import "D:\data-pemilihan\siswa.csv"
```

Impor hanya boleh saat persiapan. Seluruh berkas divalidasi terlebih dahulu; kesalahan atau duplikasi membatalkan seluruh impor, termasuk bila benturan database baru ditemukan pada batch berikutnya. Tidak ada penimpaan otomatis. Batas 10 MB / 20.000 siswa per berkas. NIS maksimal 32 angka dan disimpan sebagai teks; nama maksimal 120 karakter dan kelas 60 karakter. Spasi di tepi dihapus.

Untuk impor lewat browser pada server lain, atur PHP `upload_max_filesize=10M` dan `post_max_size=12M`, lalu mulai ulang Apache. Skrip lokal sudah menggunakan batas tersebut. Batas foto calon tetap 2 MB.

Di Excel, format kolom NIS sebagai **Text sebelum memasukkan data**. Nol yang sudah hilang di sumber tidak dapat dipulihkan aplikasi. Simpan CSV siswa di luar `public`. Jangan membuka berkas data siswa di laptop siswa.

### Calon dikelola guru

Buka URL hasil `php artisan election:teacher-url`. Menu **Kelola calon** menyediakan tambah, edit, pratinjau dan hapus. Isian: nomor urut 1–999 yang unik, nama, foto JPEG/PNG maksimal 2 MB / 4000 × 4000 piksel, visi maksimal 1500 karakter dan misi maksimal 3000 karakter. Foto disimpan dengan nama acak di luar folder publik dan dilayani lewat route foto. Teks tidak dirender sebagai HTML.

Guru dapat mengakses URL dari laptop mana pun tanpa login, sesuai rancangan. **Siapa pun yang mengetahui URL mempunyai kemampuan yang sama.** Jangan tautkan alamat pada halaman siswa, jangan bagikan di grup siswa, dan jangan menyimpannya di browser laptop siswa. Aplikasi tidak menerapkan pembatasan IP.

## Operasional pemilihan

Menu **Pengaturan** menyediakan nama sekolah, logo PNG/JPEG maksimal 2 MB, periode OSIS, judul pemilihan, jadwal buka/tutup, dan nama penandatangan laporan. Identitas serta jadwal dikunci setelah pemilihan dibuka; tempat laporan dan penandatangan tetap dapat diperbaiki setelah penutupan. Tanpa identitas tambahan, aplikasi tetap memakai Suara OSIS.

Jadwal menggunakan **WIB** pada formulir dan disimpan sebagai UTC. Isi waktu buka dan tutup bersama; jadwal baru harus dimulai setelah waktu server saat disimpan. Kosongkan keduanya untuk kontrol manual. Pembukaan otomatis menunggu seluruh pemeriksaan kesiapan lengkap; jika batas tutup terlewati, pemilihan ditutup meskipun belum sempat dibuka. Tombol Buka dapat memulai lebih awal, tetapi batas tutup tetap berlaku. Penutupan bersifat final. Waktu pengiriman diperiksa lagi di dalam transaksi sehingga suara tidak diterima setelah batas tutup, termasuk saat scheduler berhenti. Jam laptop server menjadi acuan; laptop siswa hanya menampilkan hitung mundur.

Dashboard **Ringkasan** memperbarui progres keseluruhan dan per kelas setiap 10 detik selama halaman terlihat. Jika koneksi terganggu, angka terakhir tetap ditampilkan dengan penanda pembaruan tertunda. Tidak ada perolehan calon pada endpoint progres. Setelah ditutup, **Rekap hasil → Unduh PDF** menyediakan laporan A4 dengan tabel hasil, partisipasi kelas, pemeriksaan konsistensi, waktu kegiatan, dan ruang tanda tangan. Waktu kegiatan lama yang belum pernah tercatat ditampilkan sebagai tidak tersedia. PDF dihasilkan lokal; tidak memerlukan Chrome, layanan luar, atau internet setelah `composer install`.

1. Pada **Ringkasan**, periksa data siswa tersedia, minimal dua calon, seluruh foto tersedia, dan jumlah suara serta partisipasi nol.
2. Buat backup awal: `php artisan election:backup`.
3. Ketik **BUKA** dan tekan **Buka pemilihan**. Data siswa dan calon terkunci secara permanen untuk kegiatan tersebut.
4. Siswa memasukkan NIS, memilih satu calon, lalu mengonfirmasi. Sesi berlaku lima menit sejak verifikasi. Setelah commit, konteks siswa dihapus dan halaman berhasil kembali ke awal dalam lima detik. Tanpa JavaScript, tombol kembali tetap tersedia.
5. Siswa berikutnya harus mulai dari halaman awal. Petugas memeriksa kartu pelajar/identitas sebelum memberikan giliran, tanpa melihat pilihan siswa.
6. Setelah semua selesai, guru mengetik **TUTUP**. Penutupan memakai penguncian yang sama dengan suara, sehingga menunggu transaksi yang sudah lebih dulu mendapat kunci. Suara yang baru diproses setelah ditutup ditolak. Tidak ada fitur buka ulang.
7. Lihat rekap dan unduh CSV. Calon dengan nol suara tetap muncul. Tidak ada pilihan individu. Buat backup final dan salin ke media terpisah.

Rekap per calon dan ekspornya ditolak sebelum ditutup, termasuk akses route langsung. Ringkasan selama pemilihan hanya menampilkan jumlah siswa, calon dan partisipasi.

## Jaringan tiga laptop

- Hubungkan semua laptop ke router/switch yang sama. Router tidak memerlukan koneksi internet. Hindari guest Wi-Fi dengan isolasi antarperangkat.
- Tetapkan IP server, misalnya `192.168.1.10`, melalui reservasi DHCP atau IP statis tanpa benturan.
- Set `APP_URL=http://192.168.1.10:8080` di `.env`. Jalankan `start-local.ps1` tanpa `-LoopbackOnly`.
- Laptop 2 dan 3 membuka `http://192.168.1.10:8080`. Tidak perlu PHP atau MySQL pada laptop klien.
- Pada Windows Firewall izinkan TCP 8080 hanya untuk profil **Private**, dengan sumber dua IP laptop siswa atau subnet lokal yang digunakan. Jika guru memakai laptop keempat, sertakan IP tersebut. Jangan menonaktifkan firewall.
- Jangan membuka port 3306/3307 MySQL ke LAN. Database tetap pada loopback. Jangan membuat port forwarding router atau memakai CDN.
- Gunakan jaringan yang dipercaya: HTTP lokal tidak mengenkripsi trafik.

Uji dari ketiga laptop dengan internet terputus sebelum hari pemilihan. Tidak ada aturan firewall yang dibuat otomatis oleh aplikasi.

## Backup setiap 15 menit

```powershell
php artisan election:backup
php artisan schedule:list
```

Dump memakai transaksi konsisten, tidak menaruh password pada argumen proses, memiliki nama unik, dan baru berganti dari `.partial` menjadi `.sql` setelah berhasil. Gagal backup tidak menghasilkan berkas final palsu. Default: `storage/app/private/backups`. Set `BACKUP_DIRECTORY="E:/Backup-OSIS"` untuk media terpisah. Folder di dalam `public` ditolak. Set `MYSQLDUMP_PATH` ke path absolut `mysqldump.exe` bila tidak di PATH. Biarkan seluruh versi backup tetap tersedia selama kegiatan; awasi ruang penyimpanan.

Pada Windows Task Scheduler, buat tugas dengan akun petugas:

- Program: lokasi absolut `powershell.exe`.
- Argumen: `-NoProfile -File "D:\vibe-coding\osis-election\scripts\run-scheduler.ps1"`.
- Start in: `D:\vibe-coding\osis-election`.
- Pemicu: setiap satu menit selama pemilihan; hindari menjalankan instance yang tumpang tindih.
- Pastikan akun tugas menemukan PHP/MySQL di PATH dan dapat menulis tujuan backup.

Skrip `start-local.ps1` sekarang memulai scheduler proyek dalam proses tersembunyi; `stop-local.ps1` memintanya menyelesaikan tugas yang berjalan lalu berhenti sebelum database dihentikan. Server yang sudah aktif dapat memulai scheduler dengan `scripts/start-scheduler.ps1`; gunakan `scripts/stop-scheduler.ps1` untuk menghentikan scheduler saja. Skrip tidak mendaftarkan tugas Windows global. Jika kebijakan PowerShell memblokir skrip lokal, gunakan `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-scheduler.ps1` untuk menjalankannya pada proses tersebut.

Scheduler memeriksa jadwal dan sesi setiap menit, membersihkan pratinjau kedaluwarsa setiap lima menit, serta menjalankan backup setiap 15 menit. Sesi guru berlaku minimal 30 menit agar pratinjau tetap dapat digunakan; kelayakan sesi siswa tetap lima menit atau sampai batas tutup pemilihan. Pembersihan berkas sesi mempertimbangkan durasi guru. Hasil backup ditulis ke `storage/logs/backup.log`, keluaran scheduler ke `.runtime/scheduler.log`, dan kesalahan proses ke `.runtime/scheduler-error.log`. Periksa bahwa backup pertama benar-benar muncul. Bila memakai Task Scheduler manual seperti contoh di atas, gunakan satu mekanisme scheduler saja.

Cadangkan juga kode siap jalan beserta `vendor`, foto di `storage/app/private/candidate-photos`, serta `.env` secara aman. Batasi akses salinan karena berisi data siswa dan konfigurasi rahasia. Lindungi file `.runtime/mysql-admin.json` pada instalasi lokal. Hindari menyalin berkas data MySQL yang sedang aktif sebagai pengganti dump konsisten.

## Layar pengumuman hasil

Setelah pemilihan ditutup, buka **Ruang Guru → Rekap hasil → Layar pengumuman hasil**. **Pratinjau layar** memperlihatkan tampilan proyektor tanpa membuka hasil kepada siswa. Klik **Aktifkan pengumuman**, lalu **Buka layar pengumuman** untuk menampilkan hasil pada alamat `/pengumuman`. Laptop proyektor lain menggunakan `http://IP-SERVER:8080/pengumuman`; alamat ini tidak mengandung kode guru. Tombol **Layar penuh** tersedia pada browser yang mendukungnya, dengan Escape untuk keluar.

Layar menampilkan identitas sekolah, foto/nomor calon, grafik dan persentase suara, total suara, serta partisipasi keseluruhan. Calon diurutkan menurut perolehan lalu nomor urut; hasil seri ditandai sebagai perolehan tertinggi bersama, tanpa menetapkan pemenang tunggal. Kondisi tanpa suara ditampilkan secara eksplisit. Tidak ada NIS, nama siswa, atau tautan administrasi pada layar publik.

Pengumuman awalnya nonaktif dan hanya dapat diaktifkan setelah penutupan serta jumlah partisipasi sesuai dengan suara. **Sembunyikan pengumuman** menutup akses kembali; layar yang sedang terbuka memeriksa status setiap lima detik saat tersambung. Tanpa JavaScript, gunakan tombol periksa pengumuman atau muat ulang halaman. Semua aset tersedia lokal tanpa CDN.

## Gangguan dan pemulihan

Untuk koneksi putus atau respons kirim yang tidak jelas, **jangan berasumsi suara gagal**. Petugas memeriksa status tanpa melihat pilihan:

```powershell
php artisan election:check 001001
php artisan election:check
```

Perintah pertama hanya menampilkan sudah/belum memilih. Perintah kedua memeriksa jumlah siswa, partisipasi dan suara pada snapshot yang dilindungi kunci pemilihan.

Jika server mati: hentikan giliran siswa, hidupkan server, mulai MySQL dan Apache, periksa IP, jalankan `election:check`, lalu lanjutkan hanya bila database konsisten. Jika database gagal diakses, terjadi kerusakan penyimpanan, atau jumlah berbeda, hentikan penggunaan dan libatkan petugas teknis. Jangan mengubah status siswa secara manual.

Latihan restore dilakukan ke **database uji kosong**, tidak ke database pemilihan aktif:

1. Pastikan aplikasi uji tidak sedang digunakan. Buat database tujuan terpisah dengan charset/collation yang sama.
2. Buka klien MySQL dengan prompt password: `mysql --host=127.0.0.1 --port=3307 --user=osis_test --password osis_election_testing`.
3. Pada prompt MySQL, jalankan `source D:/lokasi-backup/backup.sql;` menggunakan path berkas yang sebenarnya.
4. Hubungkan konfigurasi uji ke database tersebut dan periksa siswa, calon, status, partisipasi serta suara. Pulihkan foto jika berpindah server. Contoh otomatis uji dump/restore ada di `tests/Feature/BackupTest.php`.

Dump dapat menghapus dan membuat ulang tabel pada database tujuan: periksa nama tujuan sebelum restore. Jika disk server rusak, backup terakhir bisa kehilangan suara setelah waktu backup. **Jangan langsung membuka dari backup lama**, karena siswa yang sebenarnya telah memilih dapat terlihat belum memilih. Panitia harus menetapkan penanganan periode data hilang terlebih dahulu. Jangan menghapus partisipasi untuk memberi kesempatan ulang: suara anonim siswa tersebut tidak dapat ditemukan untuk dibatalkan.

## Pengujian

Pasang dependensi pengembangan dengan `composer install`. Buat `.env.testing` dari contoh dengan database MySQL **khusus berakhiran `_testing`**, akun khusus untuk database itu, APP_KEY yang valid, dan `APP_ENV=testing`. Setup lokal sudah membuatnya. Jangan memakai database resmi untuk tes.

```powershell
php artisan config:clear
php artisan test
php tests/support/http-smoke.php
```

Suite menolak berjalan jika database bukan MySQL atau namanya tidak berakhiran `_testing`. Pengujian melakukan migrasi/restore dan menghapus data di database uji. Jangan menjalankan dua suite sekaligus pada database yang sama.

Pengujian mencakup NIS berawalan nol, NIS tidak valid, impor atomik termasuk benturan batch lanjut, CRUD/foto, penguncian data, waktu sesi dan tab lama, transaksi rollback, suara ganda, rekap terkunci, ekspor anonim, batasan database, dump/restore dan backup di folder publik. Pengujian konkurensi memakai tiga proses PHP dengan koneksi MySQL terpisah dan titik mulai bersama. Smoke test menjalankan server HTTP sementara pada 8082 untuk memeriksa sesi file terenkripsi, cookie, CSRF sungguhan, unggahan, alur suara dan replay.

Uji manual sebelum digunakan: tiga laptop fisik memilih bersama, putuskan koneksi sebelum dan setelah pengiriman, restart server, uji sumber daya offline, serta pastikan layar lama tidak dapat digunakan siswa berikutnya. Pengujian proses pada satu laptop tidak menggantikan latihan jaringan/perangkat fisik.

## Integritas dan kerahasiaan

`participations.student_id` adalah primary key unik. Status sudah memilih diturunkan dari tabel ini. Tabel `votes` hanya memiliki UUID acak dan `candidate_id`; tidak ada NIS, student ID, session ID, waktu atau relasi ke partisipasi. Semua perubahan memakai kunci baris pengaturan pemilihan yang sama; penambahan partisipasi dan suara terjadi dalam satu transaksi, dengan pemeriksaan ulang di server. Akun aplikasi hanya boleh digunakan aplikasi, bukan untuk penulisan manual.

Sesi terenkripsi disimpan di file, ID sesi/token CSRF diganti setelah selesai, token alur menolak tab lama, dan halaman memakai `no-store`/`no-referrer`. URL guru tidak dicantumkan dalam halaman siswa. Access log Apache mandiri tidak menyimpan URL/referrer. Log kesalahan database aplikasi tidak menyimpan SQL/binding yang bisa mengungkap identitas atau pilihan. Jangan memasang Telescope, debug toolbar atau pencatat request/query selama pemilihan.

Ini bukan anonimitas kriptografis terhadap administrator yang menguasai seluruh server, binlog atau jaringan. NIS tanpa login juga tidak membuktikan identitas siswa. Pengawasan petugas dan pembatasan akses fisik/akun server tetap merupakan bagian operasional aplikasi.

Untuk tahun berikutnya: arsipkan database, konfigurasi dan foto; siapkan instalasi/database baru. Tidak tersedia tombol reset suara atau buka ulang yang berisiko dipakai saat pemilihan.
