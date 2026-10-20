<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashFlow
{
    public function report(int $year, int $month = 0, int $bank = 0): array
    {
        $start = Carbon::create($year, $month ?: 1, 1)->startOfDay();
        $end = $month ? $start->copy()->endOfMonth() : $start->copy()->endOfYear();
        $base = function (string $table) use ($bank) {
            return DB::table($table)->whereNotNull('tanggal')
                ->when($bank > 0, fn ($q) => $q->where('id_bank', $bank))
                ->when($bank === 0, fn ($q) => $q->where(fn ($q) => $q->whereNull('id_mutasi')->orWhere('id_mutasi', 0)));
        };
        $opening = (int) $base('pemasukan')->where('tanggal', '<', $start->toDateString())->sum('nominal')
            - (int) $base('pengeluaran')->where('tanggal', '<', $start->toDateString())->sum('nominal');
        $entries = function (string $table, string $type) use ($base, $start, $end) {
            return $base($table)->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->select('id', 'tanggal', 'id_bank', 'id_kategori_transaksi', 'nominal', 'keterangan', DB::raw("'$type' as tipe"));
        };
        $union = $entries('pemasukan', 'pemasukan')->unionAll($entries('pengeluaran', 'pengeluaran'));
        $rows = DB::query()->fromSub($union, 'ledger')->leftJoin('kategori_transaksi as c', 'c.id', '=', 'ledger.id_kategori_transaksi')
            ->leftJoin('bank as b', 'b.id', '=', 'ledger.id_bank')
            ->select('ledger.*', 'c.kategori', 'b.nama as rekening')->orderBy('ledger.tanggal')->orderBy('ledger.tipe')->orderBy('ledger.id')->get();
        $balance = $opening;
        foreach ($rows as $row) {
            $row->debit_value = $row->tipe === 'pemasukan' ? (int) $row->nominal : 0;
            $row->kredit_value = $row->tipe === 'pengeluaran' ? (int) $row->nominal : 0;
            $balance += $row->debit_value - $row->kredit_value;
            $row->saldo_value = $balance;
            $row->kategori = $row->kategori ?: 'Tanpa kategori';
            $row->rekening = $row->rekening ?: 'Rekening belum ditentukan';
        }
        return ['rows' => $rows, 'saldo_awal' => $opening, 'total_pemasukan' => (int) $rows->sum('debit_value'),
            'total_pengeluaran' => (int) $rows->sum('kredit_value'), 'saldo_akhir' => $balance,
            'perubahan_saldo' => $balance - $opening];
    }
}
