<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([['P-IN', 'Pembayaran Piutang', 'PEMASUKAN'], ['P-OUT', 'Pemberian Piutang', 'PENGELUARAN']] as [$code, $name, $type]) {
            if (! DB::table('kategori_transaksi')->where('kategori', $name)->where('jenis_kategori', $type)->exists()) {
                DB::table('kategori_transaksi')->insert(['kode' => $code, 'kategori' => $name, 'jenis_kategori' => $type, 'stt_fix' => 1]);
            }
        }
    }

    public function down(): void
    {
        // Keep categories that may already be referenced by financial records.
    }
};
