# Audit izin menu

Pemeriksaan izin di middleware dan tampilan memakai pemetaan yang sama di `App\Services\MenuAccess`. Tidak ada perubahan SQL, skema, atau hak akses akun.

Aturan umum: lihat untuk membaca, tambah untuk membuat, edit untuk mengubah, hapus untuk menghapus. Form GET create/edit memakai izin yang sama dengan proses simpannya. Seluruh aksi tetap membutuhkan izin lihat pada menu terkait.

| Menu / aksi khusus | Izin |
| --- | --- |
| Pembayaran: tambah/hapus pembayaran, tambah/hapus tagihan, kirim kwitansi | Edit Pembayaran, mengikuti controller yang sudah ada |
| Arsip customer dan restore | Lihat Arsip Customer; restore membutuhkan Hapus Arsip Customer |
| Import harga kavling | Edit Kavling |
| Pengaturan koneksi: tes kirim WA | Edit Pengaturan Koneksi |
| Hak akses: simpan | Edit Hak Akses |
| Lokasi kavling: edit detail dan setting | Edit Lokasi Kavling |
| Media profil | Edit Pengaturan Profil, sesuai halaman profil yang memuat media |
| Cetak siteplan | Lihat Siteplan |
| Bayar piutang nonpenjualan | Edit Piutang atau Tambah Pemasukan, sesuai ReceivablePayment::canPay |
| Riwayat/sisa piutang dari Pemasukan | Lihat Piutang atau Lihat Pemasukan; tidak memberikan izin mutasi |
| Sisa hutang dari Pengeluaran | Lihat Hutang atau Lihat Pengeluaran; tidak memberikan izin mutasi |

Perbaikan tampilan mencakup aksi customer dengan izin edit saja, detail pembayaran untuk pengguna lihat saja, tombol edit legalitas/detail lokasi, tombol hapus berkas, serta riwayat keuangan dan tombol bayar hutang. Tombol ubah/hapus/kirim pembayaran disembunyikan bagi pengguna tanpa Edit Pembayaran.

Validasi: MenuPermissionsTest memeriksa seluruh route admin bercontroller yang terdaftar, akses dengan izin yang sesuai, penolakan tanpa izin, aturan aksi khusus, dan izin halaman detail. FinanceSimulationTest memeriksa proses keuangan dan saldo. Pemeriksaan ini tidak menggantikan uji interaktif setiap halaman di browser.
