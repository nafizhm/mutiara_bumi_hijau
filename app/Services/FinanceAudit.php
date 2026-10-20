<?php

namespace App\Services;

use App\Models\Piutang;
use App\Models\Hutang;
use Illuminate\Support\Facades\DB;

class FinanceAudit
{
    public function repair(): array
    {
        return DB::transaction(function () {
            $snapshot = ['database' => DB::connection()->getDatabaseName(), 'created_at' => now()->toIso8601String(), 'tables' => []];
            foreach (['hutang', 'piutang', 'pemasukan', 'pengeluaran', 'mutasi_saldo'] as $table) {
                $snapshot['tables'][$table] = DB::table($table)->lockForUpdate()->get()->all();
            }
            $directory = config('finance.backup_directory', storage_path('app/private/backups'));
            if (!is_dir($directory)) mkdir($directory, 0700, true);
            $backup = $directory . '/finance-repair-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
            $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json)) throw new \RuntimeException('Cadangan gagal disimpan.');
            $linked = 0;
            $unlinked = DB::table('pemasukan')->where('id_customer', '>', 0)->whereIn('id_kategori_transaksi', [25,26,27,28,29])
                ->where(fn ($q) => $q->whereNull('id_piutang')->orWhere('id_piutang', 0))->get()->groupBy('id_customer');
            foreach ($unlinked as $customer => $receipts) {
                $candidates = Piutang::where('id_customer', $customer)->where(fn ($q) => $q->whereNull('id_kategori_transaksi')->orWhere('id_kategori_transaksi', 0))->get();
                if ($candidates->count() !== 1) continue;
                $bill = $candidates->first();
                $expected = (int) $bill->payments()->sum('nominal') + (int) $receipts->sum('nominal');
                // Link only when both ownership and the existing paid amount confirm the allocation.
                if ($expected !== (int) $bill->terbayar || $expected > $bill->nominal) continue;
                $linked += DB::table('pemasukan')->whereIn('id', $receipts->pluck('id'))->update(['id_piutang' => $bill->id]);
            }
            foreach (Piutang::all() as $bill) {
                if ($bill->id_customer && DB::table('pemasukan')->where('id_customer', $bill->id_customer)->where(fn($q)=>$q->whereNull('id_piutang')->orWhere('id_piutang',0))->exists()) continue;
                if ($bill->payments()->sum('nominal') <= $bill->nominal) app(ReceivablePayment::class)->sync($bill);
            }
            foreach (Hutang::all() as $debt) {
                if (DB::table('pengeluaran')->where('id_hutang', $debt->id)->sum('nominal') <= $debt->nominal) app(DebtPayment::class)->sync($debt);
            }
            return ['linked_receipts' => $linked, 'backup' => $backup];
        });
    }

    public function issues(): array
    {
        $issues = [];
        foreach (['hutang' => 'pengeluaran', 'piutang' => 'pemasukan'] as $table => $ledger) {
            foreach (DB::table($table)->get() as $row) {
                $paid = (int) DB::table($ledger)->where('id_' . $table, $row->id)->sum('nominal');
                $remaining = (int) $row->nominal - $paid;
                if ($paid !== (int) $row->terbayar || $remaining !== (int) $row->sisa_bayar || $remaining < 0 || (int) $row->status !== ($remaining === 0 ? 2 : 1)) {
                    $issues[] = ['type' => 'saldo_' . $table, 'id' => $row->id, 'nominal' => (int) $row->nominal, 'terbayar' => (int) $row->terbayar, 'ledger' => $paid, 'sisa' => (int) $row->sisa_bayar];
                }
                if ($table === 'hutang' || !$row->id_customer) {
                    $origin = $table === 'hutang' ? 'pemasukan' : 'pengeluaran';
                    $entries = DB::table($origin)->where('id_' . $table, $row->id)->get();
                    if ($entries->count() !== 1 || (int) $entries->sum('nominal') !== (int) $row->nominal
                        || ($entries->count() === 1 && ((int) $entries->first()->id_bank !== (int) $row->id_bank || $entries->first()->tanggal !== ($row->tanggal_hutang ?? $row->tanggal_piutang)))) {
                        $issues[] = ['type' => 'transaksi_asal_' . $table, 'id' => $row->id];
                    }
                }
            }
        }
        foreach (['pemasukan' => 'PEMASUKAN', 'pengeluaran' => 'PENGELUARAN'] as $table => $type) {
            foreach (DB::table($table)->get() as $row) {
                if ($row->nominal <= 0 || !DB::table('bank')->where('id', $row->id_bank)->exists()) $issues[] = ['type' => 'nominal_atau_rekening_' . $table, 'id' => $row->id];
                if (!DB::table('kategori_transaksi')->where('id', $row->id_kategori_transaksi)->where('jenis_kategori', $type)->exists()) $issues[] = ['type' => 'kategori_' . $table, 'id' => $row->id];
                foreach (['id_hutang' => 'hutang', 'id_piutang' => 'piutang', 'id_mutasi' => 'mutasi_saldo'] as $key => $target) {
                    if ($row->$key && !DB::table($target)->where('id', $row->$key)->exists()) $issues[] = ['type' => 'referensi_' . $table, 'id' => $row->id, 'field' => $key];
                }
                if ($table === 'pemasukan' && $row->id_customer) {
                    if (!$row->id_piutang) $issues[] = ['type' => 'pembayaran_tanpa_tagihan', 'id' => $row->id];
                    elseif ((int) DB::table('piutang')->where('id', $row->id_piutang)->value('id_customer') !== (int) $row->id_customer) $issues[] = ['type' => 'pemilik_tagihan', 'id' => $row->id];
                }
            }
        }
        foreach (DB::table('mutasi_saldo')->get() as $transfer) {
            foreach (['pemasukan' => 'rekening_tujuan', 'pengeluaran' => 'rekening_asal'] as $table => $bank) {
                $legs = DB::table($table)->where('id_mutasi', $transfer->id)->get();
                if ($legs->count() !== 1 || (int) $legs->sum('nominal') !== (int) $transfer->nominal
                    || ($legs->count() === 1 && ((int) $legs->first()->id_bank !== (int) $transfer->$bank || $legs->first()->tanggal !== $transfer->tanggal))) $issues[] = ['type' => 'pasangan_mutasi_' . $table, 'id' => $transfer->id];
            }
        }
        return $issues;
    }
}
