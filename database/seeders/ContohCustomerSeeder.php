<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\KavlingPeta;
use App\Models\Pemasukan;
use App\Models\PersyaratanLegal;
use App\Models\Piutang;
use App\Models\TransaksiKavling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContohCustomerSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $bank = DB::table('bank')->orderBy('id')->value('id');
            if (!$bank) {
                throw new \RuntimeException('Rekening bank diperlukan untuk data contoh.');
            }

            $examples = [
                ['Andi Pratama (Contoh)', 1, 'Booking', 'Booking Fee', 120000000, 2000000, 0],
                ['Siti Rahma (Contoh)', 1, 'Cash Keras', 'Cash Keras', 150000000, 150000000, 0],
                ['Budi Santoso (Contoh)', 1, 'Kredit', 'Kredit', 180000000, 36000000, 24],
                ['Dewi Lestari (Contoh)', 2, 'Kredit', 'Kredit', 144000000, 36000000, 36],
                ['Rizky Saputra (Contoh)', 2, 'Booking', 'Booking Fee', 100000000, 5000000, 0],
            ];

            foreach ($examples as $index => [$name, $location, $method, $progress, $price, $paid, $tenor]) {
                $code = 'DEMO-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
                if (Customer::where('kode_customer', $code)->exists()) {
                    continue;
                }

                $lot = KavlingPeta::where('id_lokasi', $location)
                    ->whereDoesntHave('customer')->orderBy('id')->lockForUpdate()->firstOrFail();
                $progressId = DB::table('progres_list_penjualan')
                    ->where('status_progres', $progress)->value('id');
                if (!$progressId) {
                    throw new \RuntimeException('Tahapan penjualan tidak ditemukan: ' . $progress);
                }
                $date = '2026-10-20';
                $balance = $price - $paid;
                $customer = Customer::create([
                    'kode_customer' => $code,
                    'nama_lengkap' => $name,
                    'no_ktp' => '000000000000000' . ($index + 1),
                    'tempat_lahir' => 'Pekanbaru',
                    'tgl_lahir' => '1990-01-0' . ($index + 1),
                    'jenis_kelamin' => in_array($index, [1, 3]) ? 'Perempuan' : 'Laki-laki',
                    'no_telp' => '00000000000' . ($index + 1),
                    'no_wa' => '00000000000' . ($index + 1),
                    'email' => 'customer' . ($index + 1) . '@example.com',
                    'alamat' => 'Alamat contoh ' . ($index + 1) . ', Pekanbaru, Riau (data fiktif)',
                    'alamat_domisili' => 'Pekanbaru, Riau (data fiktif)',
                    'pekerjaan' => 'Wiraswasta',
                    'id_lokasi' => $location,
                    'id_status_progres' => $progressId,
                    'id_marketing' => 0,
                    'id_bank' => $bank,
                    'jenis_pembelian' => $method,
                    'discount' => 0,
                    'pembayaran_booking' => $method === 'Booking' ? $paid : null,
                    'tgl_batas_booking' => $method === 'Booking' ? '2026-11-03' : null,
                    'dp_kredit' => $method === 'Kredit' ? $paid : null,
                    'inhouse_tenor' => $tenor ?: null,
                    'inhouse_perbulan' => $tenor ? $balance / $tenor : null,
                    'inhouse_jatuh_tempo' => $tenor ? '2026-11-20' : null,
                    'stt_arsip' => 0,
                ]);
                PersyaratanLegal::create(['id_customer' => $customer->id]);
                TransaksiKavling::create([
                    'id_customer' => $customer->id,
                    'id_kavling' => $lot->id,
                    'tgl_terima' => $date,
                    'hrg_rumah' => $price,
                ]);
                $receivable = Piutang::create([
                    'id_bank' => $bank,
                    'id_customer' => $customer->id,
                    'tanggal_piutang' => $date,
                    'deskripsi' => 'Harga Tanah Kavling di ' . $lot->lokasi->nama_kavling . ' Blok ' . $lot->kode_kavling . ' (contoh)',
                    'nominal' => $price,
                    'terbayar' => $paid,
                    'sisa_bayar' => $balance,
                    'status' => $balance === 0 ? 2 : 0,
                    'tgl_pelunasan' => $balance === 0 ? $date : null,
                    'id_kategori_transaksi' => 0,
                ]);
                Pemasukan::create([
                    'id_bank' => $bank,
                    'id_piutang' => $receivable->id,
                    'id_customer' => $customer->id,
                    'id_lokasi' => $location,
                    'tanggal' => $date,
                    'nominal' => $paid,
                    'no_kwitansi' => 'KW-' . $code,
                    'id_kategori_transaksi' => match ($method) {
                        'Booking' => 25,
                        'Kredit' => 26,
                        'Cash Keras' => 27,
                    },
                    'keterangan' => match ($method) {
                        'Booking' => 'Pembayaran Booking Kavling',
                        'Kredit' => 'Pembayaran DP Kredit Kavling',
                        'Cash Keras' => 'Pelunasan Cash Keras Kavling',
                    } . ' - ' . $name,
                ]);
            }
        });
    }
}
