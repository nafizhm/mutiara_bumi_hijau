<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([['H-IN', 'Pencairan Hutang', 'PEMASUKAN'], ['H-OUT', 'Pembayaran Hutang', 'PENGELUARAN'], ['M-IN', 'Mutasi Masuk', 'PEMASUKAN'], ['M-OUT', 'Mutasi Keluar', 'PENGELUARAN']] as [$code, $name, $type]) {
            $row = DB::table('kategori_transaksi')->where('kategori', $name)->where('jenis_kategori', $type)->first();
            if (!$row) DB::table('kategori_transaksi')->insert(['kode' => $code, 'kategori' => $name, 'jenis_kategori' => $type, 'stt_fix' => 1]);
            else DB::table('kategori_transaksi')->where('id', $row->id)->update(['stt_fix' => 1]);
        }
        if (!Schema::hasColumn('pemasukan', 'id_metode')) Schema::table('pemasukan', fn (Blueprint $table) => $table->unsignedInteger('id_metode')->nullable());
    }

    public function down(): void
    {
        // Preserve categories and payment methods used by existing ledger entries.
    }
};
