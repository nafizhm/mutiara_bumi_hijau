<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\KategoriTransaksi;
use App\Models\Pemasukan;
use App\Models\Piutang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivablePayment
{
    public static function canManage(?int $userId, string $route, string $ability): bool
    {
        return $userId && DB::table('hak_akses as h')->join('menu as m', 'm.id', '=', 'h.id_menu')
            ->where('h.id_user', $userId)->where('h.lihat', 1)->where('m.route_name', $route)->where('h.'.$ability, 1)->exists();
    }

    public static function canPay(?int $userId): bool
    {
        return $userId && DB::table('hak_akses as h')->join('menu as m', 'm.id', '=', 'h.id_menu')
            ->where('h.id_user', $userId)->where('h.lihat', 1)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('m.route_name', 'piutang.index')->where('h.edit', 1))
                ->orWhere(fn ($q) => $q->where('m.route_name', 'pemasukan.index')->where('h.tambah', 1)))->exists();
    }

    public function validate(Request $request): array
    {
        $value = (string) $request->input('nominal', '');
        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
            $request->merge(['nominal' => str_replace('.', '', $value)]);
        }

        return $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'nominal' => 'required|integer|min:1|max:2147483647',
            'id_bank' => 'required|integer|exists:bank,id',
            'keterangan' => 'nullable|string|max:1000',
        ]);
    }

    public function pay(int $id, array $input): Pemasukan
    {
        return DB::transaction(function () use ($id, $input) {
            $receivable = Piutang::lockForUpdate()->findOrFail($id);
            $this->assertNonSales($receivable);
            $receivable->terbayar = $receivable->payments()->sum('nominal');
            $amount = (int) $input['nominal'];
            $this->assertAmount($receivable, $amount, $input['tanggal']);
            $category = KategoriTransaksi::where('kategori', 'Pembayaran Piutang')->where('jenis_kategori', 'PEMASUKAN')->firstOrFail();
            $payment = Pemasukan::create([
                'id_piutang' => $receivable->id,
                'id_bank' => $input['id_bank'],
                'id_kategori_transaksi' => $category->id,
                'tanggal' => $input['tanggal'],
                'nominal' => $amount,
                'keterangan' => $input['keterangan'] ?? ('Pembayaran piutang: '.$receivable->deskripsi),
            ]);
            $this->sync($receivable);

            return $payment;
        }, 3);
    }

    public function amend(int $paymentId, array $input): void
    {
        DB::transaction(function () use ($paymentId, $input) {
            $payment = Pemasukan::lockForUpdate()->findOrFail($paymentId);
            $receivable = Piutang::lockForUpdate()->findOrFail($payment->id_piutang);
            $this->assertNonSales($receivable);
            $previousPaid = (int) $receivable->payments()->sum('nominal') - (int) $payment->nominal;
            $receivable->terbayar = $previousPaid;
            $this->assertAmount($receivable, (int) $input['nominal'], $input['tanggal']);
            $payment->update($input);
            $this->sync($receivable);
        }, 3);
    }

    public function cancel(int $paymentId, bool $sales = false): void
    {
        DB::transaction(function () use ($paymentId, $sales) {
            $payment = Pemasukan::lockForUpdate()->findOrFail($paymentId);
            $receivable = Piutang::lockForUpdate()->findOrFail($payment->id_piutang);
            if (!$sales) $this->assertNonSales($receivable);
            elseif (!$receivable->id_customer || (int) $receivable->id_customer !== (int) $payment->id_customer) {
                throw ValidationException::withMessages(['id_piutang' => 'Tautan pembayaran penjualan tidak sesuai.']);
            }
            $payment->delete();
            $this->sync($receivable);
        }, 3);
    }

    private function assertNonSales(Piutang $receivable): void
    {
        if ($receivable->id_customer) {
            throw ValidationException::withMessages(['id_piutang' => 'Piutang penjualan dibayar melalui menu Pembayaran.']);
        }
    }

    private function assertAmount(Piutang $receivable, int $amount, string $date): void
    {
        $remaining = (int) $receivable->nominal - (int) $receivable->terbayar;
        if ($amount <= 0 || $amount > $remaining) {
            throw ValidationException::withMessages(['nominal' => 'Nominal harus lebih dari nol dan tidak boleh melebihi sisa piutang.']);
        }
        if ($receivable->tanggal_piutang && $date < $receivable->tanggal_piutang) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal pembayaran tidak boleh sebelum tanggal piutang.']);
        }
    }

    public function sync(Piutang $receivable): void
    {
        $paid = (int) $receivable->payments()->sum('nominal');
        if ($paid > $receivable->nominal) throw ValidationException::withMessages(['nominal' => 'Nominal piutang lebih kecil dari pembayaran yang sudah tercatat.']);
        $remaining = (int) $receivable->nominal - $paid;
        $receivable->update([
            'terbayar' => $paid, 'sisa_bayar' => $remaining, 'status' => $remaining === 0 ? 2 : 1,
            'tgl_pelunasan' => $remaining === 0 ? $receivable->payments()->max('tanggal') : null,
        ]);
    }

    public function paySales(int $customerId, array $input): Pemasukan
    {
        return DB::transaction(function () use ($customerId, $input) {
            $customer = \App\Models\Customer::lockForUpdate()->findOrFail($customerId);
            if ($customer->stt_arsip) throw ValidationException::withMessages(['nominal_bayar' => 'Customer sudah diarsipkan.']);
            $query = Piutang::where('id_customer', $customerId);
            if ($input['id_kategori_transaksi'] == 102) $query->where('id', $input['id_tagihan']);
            else $query->where(fn($q) => $q->whereNull('id_kategori_transaksi')->orWhere('id_kategori_transaksi', 0));
            $receivable = $query->orderBy('id')->lockForUpdate()->first();
            if (!$receivable) throw ValidationException::withMessages(['id_tagihan' => 'Tagihan tidak ditemukan atau bukan milik customer ini.']);
            $receivable->terbayar = $receivable->payments()->sum('nominal');
            $this->assertAmount($receivable, (int) $input['nominal'], $input['tanggal']);
            $payment = Pemasukan::create([
                'id_customer' => $customerId, 'id_lokasi' => $customer->id_lokasi, 'id_piutang' => $receivable->id,
                'id_bank' => $input['id_bank'], 'id_metode' => $input['id_metode'] ?? null,
                'id_kategori_transaksi' => $input['id_kategori_transaksi'], 'tanggal' => $input['tanggal'],
                'nominal' => $input['nominal'], 'keterangan' => $input['keterangan'] ?? '', 'lampiran' => $input['lampiran'] ?? '',
            ]);
            $payment->update(['no_kwitansi' => 'KW-' . $payment->id . '/' . $customer->id_lokasi . '/' . Carbon::parse($input['tanggal'])->format('m/Y')]);
            $this->sync($receivable);
            return $payment;
        }, 3);
    }
}
