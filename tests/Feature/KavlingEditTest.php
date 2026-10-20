<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\KavlingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KavlingEditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('kavling_peta', function (Blueprint $table) {
            $table->id();
            $table->integer('id_lokasi');
            foreach (['panjang', 'lebar', 'luas_tanah', 'hrg_jual'] as $column) $table->integer($column)->default(10);
            $table->string('keterangan')->default('Lama');
            $table->string('id_rumah_sikumbang')->default('SIP-1');
        });
        DB::table('kavling_peta')->insert([
            ['id' => 1, 'id_lokasi' => 1], ['id' => 2, 'id_lokasi' => 1], ['id' => 3, 'id_lokasi' => 2],
        ]);
    }

    private function save(array $extra = [])
    {
        return app(KavlingController::class)->update(Request::create('/', 'PUT', array_merge([
            'panjang' => 12, 'lebar' => 6, 'luas_tanah' => 72,
            'hrg_jual' => '150.000.000', 'keterangan' => 'Baru',
        ], $extra)), 1);
    }

    public function test_single_edit_preserves_other_plots_and_sikumbang_id(): void
    {
        $this->assertSame(200, $this->save()->getStatusCode());
        $this->assertDatabaseHas('kavling_peta', ['id' => 1, 'hrg_jual' => 150000000, 'id_rumah_sikumbang' => 'SIP-1']);
        $this->assertDatabaseHas('kavling_peta', ['id' => 2, 'hrg_jual' => 10]);
    }

    public function test_bulk_edit_changes_only_prices_in_the_same_location(): void
    {
        $this->assertTrue($this->save(['ubah_semua' => '1'])->getData()->success);
        $this->assertDatabaseHas('kavling_peta', ['id' => 2, 'hrg_jual' => 150000000, 'panjang' => 10, 'keterangan' => 'Lama']);
        $this->assertDatabaseHas('kavling_peta', ['id' => 3, 'hrg_jual' => 10]);
    }

    public function test_invalid_price_does_not_change_data(): void
    {
        try {
            $this->save(['hrg_jual' => 'abc', 'ubah_semua' => '1']);
            $this->fail('Harga tidak valid harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('hrg_jual', $exception->errors());
        }
        $this->assertDatabaseHas('kavling_peta', ['id' => 1, 'hrg_jual' => 10]);
    }
}
