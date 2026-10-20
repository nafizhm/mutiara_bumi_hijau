# Deployment production

## Gate wajib sebelum go-live

- Rotasi kredensial database yang pernah tersimpan di `.env.example`; menghapusnya dari contoh tidak mencabut kredensial lama.
- Jalankan `composer audit --no-dev`; selesaikan advisory sebelum membuka akses publik. Upgrade major framework/library harus diuji di staging.
- Jalankan `php artisan test` dan uji browser pada staging: seluruh menu, role terbatas, CRUD, upload, preview/download dokumen, PDF/Excel, dan WhatsApp sandbox.
- Tinjau hak akses setiap akun. Endpoint sekarang memeriksa izin menu di server; akun tanpa izin akan menerima 403.
- Database proyek ini tidak memiliki migration lengkap seluruh tabel bisnis. Provision staging dari backup yang telah disanitasi, bukan menganggap `migrate:fresh` dapat membangun seluruh aplikasi.

## Backup dan migration

1. Backup MySQL dengan `mysqldump --single-transaction --routines --triggers --result-file=backup.sql DATABASE_NAME` menggunakan kredensial aman, bukan password pada command line.
2. Backup `.env`, `storage/app`, dan seluruh direktori upload lama di `public`. Simpan terenkripsi di luar document root dan di lokasi terpisah.
3. Uji restore backup ke database staging terpisah. Verifikasi jumlah data dan lampiran.
4. Jalankan `php artisan migrate:status`. Migration `add_dp_kredit_to_customer_table` perlu dicocokkan dengan kolom aktual sebelum dijalankan; jangan menjalankan migration membabi buta.
5. Jalankan migration yang sudah ditinjau di staging dahulu. Production hanya setelah backup dan rencana rollback disetujui.

## Server

- PHP 8.2+ beserta extension sesuai Composer. Gunakan versi PHP yang masih menerima pembaruan keamanan.
- Document root wajib direktori `public`, bukan root proyek. Jangan upload `.git`, test report, backup, atau `.env.example` ke document root.
- Gunakan HTTPS. Isi konfigurasi dari `.env.production.example`; jangan mengganti `APP_KEY` milik instalasi lama.
- Berikan akses tulis hanya ke `storage` dan `bootstrap/cache`; jangan gunakan permission 777.
- Apache memakai `public/.htaccess`. Pada Nginx wajib tambahkan `location ^~ /berkas_user/ { deny all; }` agar dokumen lama tidak dapat diakses langsung. File baru tersimpan di storage privat dan diakses lewat endpoint berizin.
- Audit direktori upload publik lain sebelum go-live; perubahan penyimpanan privat saat ini khusus modul Upload File Nasabah, bukan seluruh lampiran aplikasi.
- Nonaktifkan eksekusi PHP/script pada semua direktori upload. Jangan membuat symlink ke `storage/app/private`.
- Atur trusted proxy hanya untuk alamat proxy milik hosting jika TLS diterminasi di proxy.
- Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` menggunakan lockfile yang telah diaudit. Build aset dengan `npm ci && npm run build` hanya jika lockfile tersedia; jika belum, hasilkan dan tinjau lockfile di staging.
- Setelah konfigurasi final: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`. Jangan menjalankannya di mesin development sebagai pengganti konfigurasi server.
- Jalankan queue worker di bawah supervisor bila queue digunakan. Tambahkan cron `php artisan schedule:run` tiap menit hanya bila ada jadwal aplikasi.
- Pantau error log dan kapasitas disk. Jadwalkan backup database dan file harian beserta retensi dan uji restore berkala.

## Verifikasi setelah deployment

- `/up` sukses; login gagal dibatasi; akun diblokir tidak dapat login.
- Pengguna tanpa izin tidak bisa membaca/mengubah data lewat request langsung.
- `.env`, backup, log, dan URL langsung `/berkas_user/...` tidak dapat dibuka.
- Jalankan `php artisan finance:audit` tanpa `--repair`; tinjau hasil sebelum mengubah data.
- Uji pembayaran dan rekonsiliasi memakai transaksi uji di staging, bukan membuat transaksi palsu pada production.
- Jika gagal: maintenance mode, rollback source dan dependensi ke release sebelumnya; restore database hanya sesuai rencana yang mencegah kehilangan transaksi baru.
