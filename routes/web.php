<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Beranda\BerandaController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\UploadFileController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\FetchDataController;
use App\Http\Controllers\Keuangan\HutangController;
use App\Http\Controllers\Keuangan\KategoriTransaksiController;
use App\Http\Controllers\Keuangan\LaporanArusKasController;
use App\Http\Controllers\Keuangan\MutasiSaldoController;
use App\Http\Controllers\Keuangan\PemasukanController;
use App\Http\Controllers\Keuangan\PengeluaranController;
use App\Http\Controllers\Keuangan\PiutangController;
use App\Http\Controllers\Legalitas\LegalitasController;
use App\Http\Controllers\Marketing\MarketingController;
use App\Http\Controllers\Master\BankController;
use App\Http\Controllers\Master\KavlingController;
use App\Http\Controllers\Master\LokasiKavlingController;
use App\Http\Controllers\Master\NotarisController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Http\Controllers\Pengaturan\KonfigurasiAplikasiController;
use App\Http\Controllers\Pengaturan\KonfigurasiMediaController;
use App\Http\Controllers\Pengaturan\ListPenjualanController;
use App\Http\Controllers\Pengaturan\PengaturanLandingController;
use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Controllers\PengaturanWA\PengaturanKoneksiController;
use App\Http\Controllers\PengaturanWA\PengaturanPesanController;
use App\Http\Controllers\STPenjualan\SiteplanPenjualanController;
use App\Http\Controllers\Utility\GetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     if (Auth::check()) {
//         return redirect()->route('beranda');
//     }

//     return view('homepage.index');
// });

Route::get('/', function () {
    return redirect()->route('login');
});

// Route::get('/', [PengaturanLandingController::class, 'homepage'])->name('homepage');

Route::group(['middleware' => 'guest'], function () {
    Route::get('admin/login', [AuthController::class, 'getLogin'])->name('login');
    Route::post('admin/post-login', [AuthController::class, 'postLogin'])->middleware('throttle:5,1')->name('admin.loginPost');
});

Route::middleware(['auth', \App\Http\Middleware\AuthorizeMenu::class])->prefix('admin')->group(function () {
    Route::get('log-aktivitas', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/beranda', fn() => redirect()->route('dashboard'))->name('beranda');

    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::get('/dashboard-chart1', 'chart1')->name('dashboard.chart1');
        Route::get('/dashboard-chart2', 'chart2')->name('dashboard.chart2');
        Route::get('/dashboard-detail-penjualan', 'detailPenjualan')->name('dashboard.detail-penjualan');
        Route::get('/dashboard/statistik-cicilan-customer', 'statistikCicilanCustomer')->name('dashboard.statistik-cicilan-customer');
        Route::get('/dashboard/lokasi-penjualan/{id}', 'showLokasiPenjualan')->name('dashboard.lokasi-penjualan-show');
        Route::get('/dashboard/customer-status-progres/{id}', 'showCustomer')->name('dashboard.customer-status-progres-show');
        Route::get('/dashboard/customer-bank/{id}', 'showCustomer')->name('dashboard.customer-bank-show');
        Route::get('/dashboard/customer-marketing/{id}', 'showCustomer')->name('dashboard.customer-marketing-show');
        Route::get('/dashboard/customer-freelance/{id}', 'showCustomer')->name('dashboard.customer-freelance-show');
        Route::get('/total-unit', 'totalUnit')->name('total.unit');
        Route::get('/booking-unit', 'booking')->name('booking.unit');
        Route::get('/akad-unit', 'akad')->name('akad.unit');
        Route::get('/lunas/unit', 'lunasUnit')->name('lunas.unit');
    });

    Route::controller(SiteplanPenjualanController::class)->group(function () {
        Route::get('st-penjualan', 'index')->name('st-penjualan.index');
        Route::get('st-penjualan/{id}/detail', 'detail')->name('st-penjualan.detail');
        Route::get('st-penjualan/tagihan/{id_customer}', 'tagihan')->name('st-penjualan.tagihan');
        Route::get('st-penjualan/load/{id_lokasi}', 'loadSiteplan')->name('st-penjualan.load');
        Route::post('st-penjualan/cetak', 'cetak')->name('st-penjualan.cetak');

        Route::get('denah-penjualan/cetak/pdf/{id_lokasi}', 'cetakPDF')->name('denah-penjualan.cetak.pdf');
        Route::get('denah-penjualan/cetak/jpg/{id_lokasi}', 'cetakJPG')->name('denah-penjualan.cetak.jpg');
    });

    Route::prefix('/')->controller(PembayaranController::class)->group(function () {
        Route::get('pembayaran/{id}/detail', 'show')->name('pembayaran.detail');
        Route::get('pembayaran/detail-tagihan/{id}', 'detailTagihan')->name('pembayaran.detail-tagihan');
        Route::get('pembayaran/detail-pemasukan/{id}', 'detailPemasukan')->name('pembayaran.detail-pemasukan');
        Route::put('pembayaran/update-harga-rumah/{id}', 'UpdateHargaRumah')->name('Pembayaran.update-harga-rumah');

        Route::get('pembayaran/rekap-pembayaran', 'rekapPembayaran')->name('pembayaran.rekap');

        Route::post('pembayaran/tambah-tagihan/{id}', 'tambahTagihan')->name('pembayaran.tambah-tagihan');
        Route::post('pembayaran/tambah-pemasukan/{id}', 'tambahPemasukan')->name('pembayaran.tambah-pemasukan');
        Route::post('pembayaran/kirim-kwitansi', 'kirimKwitansi')->name('pembayaran.kirim-kwitansi');

        Route::delete('pembayaran/delete-tagihan/{id}', 'DeleteTagihan')->name('pembayaran.delete-tagihan');
        Route::delete('pembayaran/delete-pemasukan/{id}', 'DeletePemasukan')->name('pembayaran.delete-pemasukan');
        Route::get('/customer/cetak-rekap/{id}', 'cetakRekap')->name('customer.cetak-rekap');
        Route::get('/pembayaran/cetak/{id}', 'cetak')->name('pembayaran.cetak');
        Route::get('/pembayaran/print/{id}', 'print')->name('pembayaran.print');
        Route::resource('pembayaran', PembayaranController::class)->only('index', 'show');

    });

    Route::prefix('keuangan')->group(function () {
        Route::get('/laporan-arus-kas/filter', [LaporanArusKasController::class, 'filter'])->name('laporan-arus-kas.filter');
        Route::get('laporan-arus-kas/export-pdf', [LaporanArusKasController::class, 'exportPdf'])->name('laporan-arus-kas.exportPDF');
        Route::get('laporan-arus-kas/export-excel', [LaporanArusKasController::class, 'exportExcel'])->name('laporan-arus-kas.exportExcel');

        Route::resource('pemasukan', PemasukanController::class)->only('index', 'store', 'show', 'edit', 'update', 'destroy');
        Route::resource('pengeluaran', PengeluaranController::class)->only('index', 'store', 'show', 'edit', 'update', 'destroy');
        Route::resource('hutang', HutangController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::get('piutang-terbuka', [PiutangController::class, 'payable'])->name('piutang.payable');
        Route::post('piutang/{id}/tambahan', [PiutangController::class, 'additional'])->name('piutang.additional');
        Route::post('piutang/{id}/bayar', [PiutangController::class, 'pay'])->name('piutang.pay');
        Route::resource('piutang', PiutangController::class)->only('index', 'store', 'show', 'edit', 'update', 'destroy');
        Route::get('/piutang-rekap', [PiutangController::class, 'rekap'])->name('piutang.rekap');
        Route::get('/piutang-rekap/filter', [PiutangController::class, 'filter'])->name('piutang.filter');
        Route::get('/piutang-rekap/export-pdf', [PiutangController::class, 'exportPdf'])->name('piutang.exportPDF');
        Route::get('/piutang-rekap/export-excel', [PiutangController::class, 'exportExcel'])->name('piutang.exportExcel');
        Route::resource('kategori-transaksi', KategoriTransaksiController::class)->only('index', 'store', 'show', 'update', 'destroy');
        Route::resource('mutasi-saldo', MutasiSaldoController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::resource('laporan-arus-kas', LaporanArusKasController::class)->only('index');

        Route::get('/hutang/sisa-bayar/{id}', [HutangController::class, 'getSisaBayar']);
        Route::get('/piutang/sisa-bayar/{id}', [PiutangController::class, 'getSisaBayar']);
    });

    Route::prefix('customer')->group(function () {
        Route::get('/get-kavling/{idLokasi}', [CustomerController::class, 'getKavling'])->name('customer.getKavling');
        Route::get('/get-harga-kavling/{ids}', [CustomerController::class, 'getHargaKavling'])->name('customer.getHargaKavling');
        Route::get('/customer/cetak', [CustomerController::class, 'cetakData'])->name('customer.cetak');
        Route::get('customer/{id}/cetak-subsidi', [CustomerController::class, 'cetakFormSubsidi'])->name('cetak.subsidi');
        Route::get('customer/arsip-customer', [CustomerController::class, 'arsip'])->name('arsip-customer.index');
        Route::post('customer/{id}/restore', [CustomerController::class, 'restore'])->name('customer.restore');
        Route::resource('customer', CustomerController::class)->only('index', 'create', 'store', 'edit', 'update', 'destroy');

        Route::get('/get-harga-kavling/{id_kavling}', [CustomerController::class, 'getHargaKavling'])->name('customer.getHargaKavling');
        Route::get('/customer/cetak', [CustomerController::class, 'cetakData'])->name('customer.cetak');
        Route::get('customer-data/{id_customer}/subsidi-cetak', [CustomerController::class, 'cetakFormSubsidi'])->name('subsidi.cetak');


        Route::resource('upload-file', UploadFileController::class)->only('index');
        Route::get('/search', [UploadFileController::class, 'getNasabah'])->name('nasabah.search');
        Route::get('/{id}/details', [UploadFileController::class, 'getNasabahDetails'])->name('nasabah.details');
        Route::get('/{id}/files', [UploadFileController::class, 'getFileNasabah'])->name('nasabah.files');
        Route::get('/file/{id}/download', [UploadFileController::class, 'downloadFile'])->name('nasabah.file.download');
        Route::delete('/file/{id}', [UploadFileController::class, 'deleteFile'])->name('nasabah.file.delete');
        Route::get('/{id}/file-nasabah', [UploadFileController::class, 'getFileNasabah'])->name('getFileNasabah');
        Route::post('/file-nasabah/upload', [UploadFileController::class, 'uploadFile'])->name('uploadFile');
        Route::delete('/file-nasabah/{id}', [UploadFileController::class, 'deleteFile'])->name('deleteFile');

    });

    Route::resource('legalitas', LegalitasController::class)->only('index', 'edit', 'update');
    Route::get('get-foto-legalitas/{id}', [LegalitasController::class, 'getFotoLegalitas'])->name('getFotoLegalitas');

    Route::resource('marketing', MarketingController::class)->except(['create', 'show']);

    Route::prefix('master')->group(function () {
        Route::resource('lokasi-kavling', LokasiKavlingController::class)->only('index', 'create', 'store', 'edit', 'update', 'destroy');

        Route::get('lokasi-kavling/{id}/editDetail', [LokasiKavlingController::class, 'editDetail'])->name('lokasi-kavling.editDetail');
        Route::put('lokasi-kavling/{id}/updateDetail', [LokasiKavlingController::class, 'updateDetail'])->name('lokasi-kavling.updateDetail');
        Route::get('lokasi-kavling/{id}/setting', [LokasiKavlingController::class, 'setting'])->name('lokasi-kavling.setting');
        Route::put('lokasi-kavling/{id}/setting', [LokasiKavlingController::class, 'updateSetting'])->name('lokasi-kavling.updateSetting');
        Route::get('lokasi-kavling/{id}/detail', [LokasiKavlingController::class, 'detail'])->name('lokasi-kavling.detail');
        Route::get('lokasi-kavling/export/{id}', [LokasiKavlingController::class, 'exportDetail'])->name('lokasi-kavling.export');
        Route::post('lokasi-kavling/upload-excel', [LokasiKavlingController::class, 'uploadExcel'])->name('lokasi-kavling.uploadExcel');

        Route::post('kavling/import-excel', [KavlingController::class, 'importExcel'])->name('kavling.importExcel');
        Route::resource('kavling', KavlingController::class)->only('index', 'edit', 'update');

        Route::get('/kavling/cetak-excel/{id_lokasi}', [KavlingController::class, 'cetakExcel'])->name('kavling.cetakExcel');
        Route::get('kavling/cetak-pdf/{id_lokasi}', [KavlingController::class, 'cetakPdf'])->name('kavling.cetakPdf');

        Route::resource('notaris', NotarisController::class)->except('create', 'show');

        Route::resource('bank', BankController::class)->except('create', 'show');

        Route::get('kategori', fn () => redirect()->route('kategori-transaksi.index'))->name('kategori.index');
    });

    Route::prefix('pengaturan')->group(function () {
        Route::resource('pengaturan-profile', KonfigurasiAplikasiController::class)->only('index', 'update');
        Route::resource('pengaturan-media', KonfigurasiMediaController::class)->only('edit', 'update');
        Route::resource('pengguna', PenggunaController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::resource('hak-akses', HakAksesController::class)->only('index');
        Route::get('get-hak-akses', [HakAksesController::class, 'getHakAkses'])->name('admin.getHakAkses');
        Route::put('updateHakAkses', [HakAksesController::class, 'updateHakAkses'])->name('admin.updateHakAkses');
        Route::resource('list-penjualan', ListPenjualanController::class)->only('index', 'store', 'edit', 'update', 'destroy');
    });

    Route::prefix('pengaturan-wa')->group(function () {
        Route::resource('template-pesan', PengaturanPesanController::class)->except(['create', 'show']);
        Route::post('kirim-pesan-wa', [PengaturanKoneksiController::class, 'kirimPesanWa'])->name('kirimPesanWa');
        Route::resource('pengaturan-koneksi', PengaturanKoneksiController::class)->only('index', 'update');
    });

    Route::controller(FetchDataController::class)->group(function () {
        Route::get('get-marketing', 'getMarketing')->name('getMarketing');
    });

    Route::get('get-customer/{id}', [GetController::class, 'getCustomer'])->name('get.customer');

    Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout');
});

Route::get('/refresh-csrf', function () {
    return response()->json(['token' => csrf_token()]);
})->name('refresh.csrf');

Route::get('/paksa-logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/')->with('success', 'Anda telah logout.');
});
