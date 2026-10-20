<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorizeMenu
{
    public function handle(Request $request, Closure $next)
    {
        $controller = class_basename($request->route()->getControllerClass() ?? '');
        $menus = [
            'DashboardController' => 'dashboard', 'SiteplanPenjualanController' => 'st-penjualan.index',
            'PembayaranController' => 'pembayaran.index', 'CustomerController' => 'customer.index',
            'UploadFileController' => 'upload-file.index', 'LegalitasController' => 'legalitas.index',
            'MarketingController' => 'marketing.index', 'LokasiKavlingController' => 'lokasi-kavling.index',
            'KavlingController' => 'kavling.index', 'NotarisController' => 'notaris.index',
            'BankController' => 'bank.index', 'PemasukanController' => 'pemasukan.index',
            'PengeluaranController' => 'pengeluaran.index', 'HutangController' => 'hutang.index',
            'PiutangController' => 'piutang.index', 'MutasiSaldoController' => 'mutasi-saldo.index',
            'KategoriTransaksiController' => 'kategori-transaksi.index', 'LaporanArusKasController' => 'laporan-arus-kas.index',
            'KonfigurasiAplikasiController' => 'pengaturan-profile.index', 'KonfigurasiMediaController' => 'pengaturan-profile.index',
            'PengaturanLandingController' => 'pengaturanLanding.index', 'PenggunaController' => 'pengguna.index',
            'HakAksesController' => 'hak-akses.index', 'ListPenjualanController' => 'list-penjualan.index',
            'PengaturanKoneksiController' => 'pengaturan-koneksi.index', 'PengaturanPesanController' => 'template-pesan.index',
            'FetchDataController' => 'marketing.index', 'GetController' => 'customer.index',
        ];
        $menu = $menus[$controller] ?? null;
        if (!$menu) {
            return $next($request);
        }
        $action = $request->route()->getActionMethod();
        if ($controller === 'PiutangController' && in_array($action, ['pay', 'payable'])) {
            abort_unless(\App\Services\ReceivablePayment::canPay($request->user()->id), 403);
            return $next($request);
        }
        $ability = match ($request->method()) {
            'DELETE' => 'hapus', 'PUT', 'PATCH' => 'edit', 'POST' => 'tambah', default => 'lihat',
        };
        if ($action === 'updateHakAkses') {
            $ability = 'edit';
        }
        if ($controller === 'KavlingController' && $action === 'importExcel') {
            $ability = 'edit';
        }
        if ($controller === 'PembayaranController' && $action === 'tambahPemasukan') {
            $ability = 'edit';
        }
        if ($controller === 'SiteplanPenjualanController' && $action === 'cetak') {
            $ability = 'lihat';
        }
        $allowed = DB::table('hak_akses as h')->join('menu as m', 'm.id', '=', 'h.id_menu')
            ->where('h.id_user', $request->user()->id)->where('m.route_name', $menu)
            ->where('h.lihat', 1)->where('h.'.$ability, 1)->exists();
        abort_unless($allowed, 403, 'Anda tidak memiliki izin untuk tindakan ini.');
        return $next($request);
    }
}
