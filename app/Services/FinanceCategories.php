<?php

namespace App\Services;

use App\Models\KategoriTransaksi;
use Illuminate\Validation\ValidationException;

class FinanceCategories
{
    public const SYSTEM = ['Pencairan Hutang', 'Pembayaran Hutang', 'Pembayaran Piutang', 'Pemberian Piutang', 'Mutasi Masuk', 'Mutasi Keluar'];

    public static function id(string $name, string $type): int
    {
        return (int) KategoriTransaksi::where('kategori', $name)->where('jenis_kategori', $type)->firstOrFail()->id;
    }

    public static function ordinary(int $id, string $type): KategoriTransaksi
    {
        $category = KategoriTransaksi::where('jenis_kategori', $type)->findOrFail($id);
        if (in_array($category->kategori, self::SYSTEM) || ($type === 'PEMASUKAN' && in_array($id, range(25, 36)))) {
            throw ValidationException::withMessages(['id_kategori_transaksi' => 'Kategori ini dicatat melalui menu transaksi asalnya.']);
        }
        return $category;
    }
}
