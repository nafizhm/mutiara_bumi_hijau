<?php

namespace App\Services;

use App\Models\Hutang;
use App\Models\Pengeluaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtPayment
{
    public function pay(int $id, array $input): Pengeluaran
    {
        return DB::transaction(function () use ($id, $input) {
            $debt = Hutang::lockForUpdate()->findOrFail($id);
            $this->check($debt, $input, 0);
            $expense = Pengeluaran::create($input + [
                'id_hutang' => $id, 'id_bank' => $input['id_bank'],
                'id_kategori_transaksi' => FinanceCategories::id('Pembayaran Hutang', 'PENGELUARAN'),
                'keterangan' => 'Pembayaran hutang: ' . $debt->deskripsi,
                'lampiran' => '',
            ]);
            $this->sync($debt);
            return $expense;
        }, 3);
    }

    public function amend(int $id, array $input): void
    {
        DB::transaction(function () use ($id, $input) {
            $expense = Pengeluaran::lockForUpdate()->findOrFail($id);
            $debt = Hutang::lockForUpdate()->findOrFail($expense->id_hutang);
            $this->check($debt, $input, (int) $expense->nominal);
            $expense->update($input);
            $this->sync($debt);
        }, 3);
    }

    public function cancel(int $id): void
    {
        DB::transaction(function () use ($id) {
            $expense = Pengeluaran::lockForUpdate()->findOrFail($id);
            $debt = Hutang::lockForUpdate()->findOrFail($expense->id_hutang);
            $expense->delete();
            $this->sync($debt);
        }, 3);
    }

    private function check(Hutang $debt, array $input, int $replace): void
    {
        $paid = (int) Pengeluaran::where('id_hutang', $debt->id)->sum('nominal') - $replace;
        if ($input['nominal'] <= 0 || $input['nominal'] > $debt->nominal - $paid) {
            throw ValidationException::withMessages(['nominal' => 'Nominal harus positif dan tidak boleh melebihi sisa hutang.']);
        }
        if ($input['tanggal'] < $debt->tanggal_hutang) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal pembayaran tidak boleh sebelum tanggal hutang.']);
        }
    }

    public function sync(Hutang $debt): void
    {
        $paid = (int) Pengeluaran::where('id_hutang', $debt->id)->sum('nominal');
        if ($paid > $debt->nominal) throw ValidationException::withMessages(['nominal' => 'Nominal hutang lebih kecil dari pembayaran yang sudah tercatat.']);
        $remaining = (int) $debt->nominal - $paid;
        $debt->update(['terbayar' => $paid, 'sisa_bayar' => $remaining, 'status' => $remaining === 0 ? 2 : 1,
            'tgl_pelunasan' => $remaining === 0 ? Pengeluaran::where('id_hutang', $debt->id)->max('tanggal') : null]);
    }
}
