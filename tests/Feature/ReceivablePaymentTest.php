<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceivablePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\QueueChanges::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('username');
            $t->string('password');
        });
        Schema::create('menu', function (Blueprint $t) {
            $t->id();
            $t->string('route_name');
            $t->string('title')->default('');
            $t->string('icon')->default('');
            foreach (['id_parent', 'urutan', 'lihat', 'tambah', 'edit', 'hapus'] as $c) {
                $t->integer($c)->default(0);
            }
        });
        Schema::create('hak_akses', function (Blueprint $t) {
            $t->id();
            $t->integer('id_user');
            $t->integer('id_menu');
            foreach (['lihat', 'beranda', 'tambah', 'edit', 'hapus'] as $c) {
                $t->integer($c)->default(1);
            }
        });
        Schema::create('bank', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
        });
        Schema::create('customer', function (Blueprint $t) {
            $t->id();
            $t->string('nama_lengkap');
        });
        Schema::create('kategori_transaksi', function (Blueprint $t) {
            $t->id();
            $t->string('kategori');
            $t->string('jenis_kategori');
        });
        Schema::create('piutang', function (Blueprint $t) {
            $t->id();
            $t->integer('id_customer')->nullable();
            $t->integer('id_bank')->nullable();
            $t->string('deskripsi');
            $t->date('tanggal_piutang');
            $t->date('tgl_pelunasan')->nullable();
            $t->string('lampiran')->nullable();
            foreach (['nominal', 'terbayar', 'sisa_bayar', 'status'] as $c) {
                $t->integer($c)->default(0);
            }
        });
        Schema::create('pemasukan', function (Blueprint $t) {
            $t->id();
            foreach (['id_piutang', 'id_bank', 'id_kategori_transaksi', 'id_customer', 'id_lokasi', 'id_hutang', 'id_mutasi'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->date('tanggal');
            $t->integer('nominal');
            $t->string('keterangan')->nullable();
            $t->string('lampiran')->nullable();
            $t->string('no_kwitansi')->nullable();
        });
        Schema::create('pengeluaran', function (Blueprint $t) {
            $t->id();
            $t->integer('id_piutang');
            $t->integer('id_bank');
            $t->integer('nominal');
            $t->date('tanggal');
            $t->string('lampiran')->nullable();
            $t->string('keterangan')->nullable();
        });
        DB::table('users')->insert(['id' => 1, 'username' => 'tester', 'password' => bcrypt('password')]);
        DB::table('menu')->insert([['id' => 1, 'route_name' => 'piutang.index'], ['id' => 2, 'route_name' => 'pemasukan.index']]);
        DB::table('hak_akses')->insert([['id_user' => 1, 'id_menu' => 1], ['id_user' => 1, 'id_menu' => 2]]);
        (require database_path('migrations/2026_10_20_000000_create_activity_logs_table.php'))->up();
        DB::table('bank')->insert(['id' => 9, 'nama' => 'Bank Contoh']);
        DB::table('customer')->insert(['id' => 7, 'nama_lengkap' => 'Customer Penjualan']);
        DB::table('kategori_transaksi')->insert([
            ['id' => 103, 'kategori' => 'Pembayaran Piutang', 'jenis_kategori' => 'PEMASUKAN'],
            ['id' => 44, 'kategori' => 'Lainnya', 'jenis_kategori' => 'PEMASUKAN'],
        ]);
        foreach ([1 => null, 2 => 0, 3 => 7] as $id => $customer) {
            DB::table('piutang')->insert([
                'id' => $id, 'id_customer' => $customer, 'id_bank' => 9, 'deskripsi' => 'Piutang '.$id,
                'tanggal_piutang' => '2026-10-20', 'nominal' => 1000000, 'terbayar' => 0, 'sisa_bayar' => 1000000, 'status' => 0,
            ]);
        }
        $this->actingAs(User::findOrFail(1));
    }

    private function payment(array $overrides = []): array
    {
        return $overrides + ['tanggal' => '2026-10-20', 'nominal' => 250000, 'id_bank' => 9, 'keterangan' => 'Angsuran piutang'];
    }

    public function test_sales_components_appear_as_one_package_with_aggregate_status_and_detail(): void
    {
        DB::table('piutang')->where('id', 3)->update(['terbayar' => 1000000, 'sisa_bayar' => 0, 'status' => 2]);
        DB::table('piutang')->insert([
            'id' => 4, 'id_customer' => 7, 'id_bank' => 9, 'deskripsi' => 'Biaya surat tambahan',
            'tanggal_piutang' => '2026-10-21', 'nominal' => 250000, 'terbayar' => 0, 'sisa_bayar' => 250000, 'status' => 1,
        ]);
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        $this->getJson('/admin/keuangan/piutang?draw=1&start=0&length=10', $headers)
            ->assertOk()->assertJsonPath('recordsTotal', 3)->assertJsonCount(3, 'data');
        $this->getJson('/admin/keuangan/piutang?draw=1&start=0&length=10&sumber=penjualan&status=belum', $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nominal', 'Rp 1.250.000')
            ->assertJsonPath('data.0.terbayar', 'Rp 1.000.000')
            ->assertJsonPath('data.0.sisa_bayar', 'Rp 250.000');
        $this->getJson('/admin/keuangan/piutang?draw=1&start=0&length=10&sumber=penjualan&status=lunas', $headers)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/admin/keuangan/piutang/3')->assertOk()
            ->assertJsonCount(2, 'purchase_components')
            ->assertJsonPath('data.nominal', 1250000)->assertJsonPath('data.sisa_bayar', 250000);
    }

    public function test_partial_payment_and_settlement_are_reflected_in_history_and_income(): void
    {
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment())->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 1, 'terbayar' => 250000, 'sisa_bayar' => 750000, 'status' => 1]);
        $this->assertDatabaseHas('pemasukan', ['id_piutang' => 1, 'id_bank' => 9, 'nominal' => 250000, 'id_kategori_transaksi' => 103]);
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['nominal' => '750.000', 'tanggal' => '2026-10-21']))->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 1, 'terbayar' => 1000000, 'sisa_bayar' => 0, 'status' => 2, 'tgl_pelunasan' => '2026-10-21']);
        $this->getJson('/admin/keuangan/piutang/1')->assertOk()->assertJsonCount(2, 'data.payments')->assertJsonPath('data.payments.0.bank.nama', 'Bank Contoh')->assertJsonPath('can_pay', false);
        $this->assertDatabaseHas('activity_logs', ['action' => 'tambah', 'table_name' => 'pemasukan']);
    }

    public function test_income_menu_uses_same_payment_flow_for_zero_customer_id(): void
    {
        $this->postJson('/admin/keuangan/pemasukan', $this->payment(['id_piutang' => 2, 'id_kategori_transaksi' => 103]))->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 2, 'terbayar' => 250000, 'sisa_bayar' => 750000]);
        $this->getJson('/admin/keuangan/piutang-terbuka')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_invalid_payments_and_sales_receivables_are_rejected_without_changes(): void
    {
        foreach ([0, -1, 1000001, 'invalid'] as $amount) {
            $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['nominal' => $amount]))->assertUnprocessable();
        }
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['id_bank' => 999]))->assertUnprocessable();
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['tanggal' => '2026-10-19']))->assertUnprocessable();
        $this->postJson('/admin/keuangan/piutang/3/bayar', $this->payment())->assertUnprocessable();
        $this->postJson('/admin/keuangan/pemasukan', $this->payment(['id_piutang' => 3, 'id_kategori_transaksi' => 103]))->assertUnprocessable();
        $this->assertSame(0, DB::table('pemasukan')->count());
        $this->assertDatabaseHas('piutang', ['id' => 1, 'terbayar' => 0, 'sisa_bayar' => 1000000]);
    }

    public function test_edit_and_delete_payment_preserve_receivable_balance(): void
    {
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['nominal' => 1000000]))->assertOk();
        $id = DB::table('pemasukan')->value('id');
        $this->putJson('/admin/keuangan/pemasukan/'.$id, $this->payment(['nominal' => 400000]))->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 1, 'terbayar' => 400000, 'sisa_bayar' => 600000, 'status' => 1, 'tgl_pelunasan' => null]);
        $this->putJson('/admin/keuangan/pemasukan/'.$id, $this->payment(['nominal' => 1000001]))->assertUnprocessable();
        $this->assertDatabaseHas('pemasukan', ['id' => $id, 'nominal' => 400000]);
        $this->deleteJson('/admin/keuangan/pemasukan/'.$id)->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 1, 'terbayar' => 0, 'sisa_bayar' => 1000000, 'status' => 1]);
    }

    public function test_payments_require_permission_and_cannot_be_repeated_after_settlement(): void
    {
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment(['nominal' => 1000000]))->assertOk();
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment())->assertUnprocessable();
        $this->assertSame(1, DB::table('pemasukan')->count());
        DB::table('hak_akses')->update(['tambah' => 0, 'edit' => 0]);
        $this->postJson('/admin/keuangan/piutang/2/bayar', $this->payment())->assertForbidden();
        $this->getJson('/admin/keuangan/piutang-terbuka')->assertForbidden();
    }

    public function test_list_uses_balance_for_status_and_always_exposes_history(): void
    {
        $response = $this->getJson('/admin/keuangan/piutang?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertArrayNotHasKey('error', $response->json());
        $this->assertStringContainsString('Belum lunas', $response->json('data.0.status'));
        $this->assertStringContainsString('piutang-history-button', $response->json('data.0.action'));
        $this->assertStringNotContainsString('piutang-pay-button', $response->json('data.0.action'));
        $this->assertStringContainsString('piutang-pay-button', $response->json('data.1.action'));
    }

    public function test_changing_receivable_amount_preserves_previous_payments(): void
    {
        $this->postJson('/admin/keuangan/piutang/1/bayar', $this->payment())->assertOk();
        DB::table('piutang')->where('id', 1)->update(['lampiran' => 'fixture.pdf']);
        $input = ['tanggal_piutang' => '2026-10-20', 'deskripsi' => 'Piutang diperbarui', 'nominal' => 200000, 'id_bank' => 9];
        $this->putJson('/admin/keuangan/piutang/1', $input)->assertUnprocessable();
        $input['nominal'] = 1500000;
        $this->putJson('/admin/keuangan/piutang/1', $input)->assertOk();
        $this->assertDatabaseHas('piutang', ['id' => 1, 'nominal' => 1500000, 'terbayar' => 250000, 'sisa_bayar' => 1250000]);
        $this->deleteJson('/admin/keuangan/piutang/1')->assertUnprocessable();
    }
}
