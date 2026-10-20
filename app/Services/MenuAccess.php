<?php

namespace App\Services;

use Illuminate\Http\Request;

class MenuAccess
{
    public static function menu(Request $request): ?string
    {
        $controller = class_basename($request->route()?->getControllerClass() ?? '');
        if ($controller === 'CustomerController' && in_array($request->route()?->getActionMethod(), ['arsip', 'restore'], true)) {
            return 'arsip-customer.index';
        }
        return self::menus()[$controller] ?? null;
    }

    public static function menus(): array
    {
        return [
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
            'ActivityLogController' => 'activity-logs.index',
        ];
    }

    public static function ability(Request $request): string
    {
        $controller = class_basename($request->route()?->getControllerClass() ?? '');
        $action = $request->route()?->getActionMethod();
        $overrides = [
            'PembayaranController' => [
                'tambahPemasukan' => 'edit', 'DeletePemasukan' => 'edit',
                'tambahTagihan' => 'edit', 'DeleteTagihan' => 'edit', 'kirimKwitansi' => 'edit',
            ],
            'KavlingController' => ['importExcel' => 'edit'],
            'CustomerController' => ['restore' => 'hapus'],
            'HakAksesController' => ['updateHakAkses' => 'edit'],
            'PengaturanKoneksiController' => ['kirimPesanWa' => 'edit'],
            'SiteplanPenjualanController' => ['cetak' => 'lihat'],
            'LokasiKavlingController' => ['editDetail' => 'edit', 'setting' => 'edit'],
        ];
        return $overrides[$controller][$action] ?? match ($request->method()) {
            'DELETE' => 'hapus', 'PUT', 'PATCH' => 'edit', 'POST' => 'tambah',
            default => match ($action) { 'create' => 'tambah', 'edit' => 'edit', default => 'lihat' },
        };
    }

    public static function readMenus(Request $request): array
    {
        $menus = [self::menu($request)];
        $controller = class_basename($request->route()?->getControllerClass() ?? '');
        $action = $request->route()?->getActionMethod();
        if ($controller === 'PiutangController' && in_array($action, ['show', 'getSisaBayar'], true)) {
            $menus[] = 'pemasukan.index';
        }
        if ($controller === 'HutangController' && $action === 'getSisaBayar') {
            $menus[] = 'pengeluaran.index';
        }
        return $menus;
    }
}
