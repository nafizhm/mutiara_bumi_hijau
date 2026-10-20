<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\LokasiKavlingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LokasiKavlingEditTest extends TestCase
{
    public function test_edit_does_not_require_hidden_fields_or_overwrite_existing_values(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('lokasi_kavling', function (Blueprint $table) {
            $table->id();
            foreach (['nama_kavling', 'nama_singkat', 'alamat', 'nama_perusahaan', 'alamat_perusahaan',
                'telp_perusahaan', 'kota_penandatangan', 'nama_penandatangan', 'nama_admin',
                'nama_mengetahui', 'jabatan_penandatangan', 'header', 'kop_surat', 'bg_kwitansi', 'foto_kavling'] as $column) {
                $table->string($column)->default('');
            }
            $table->integer('urutan')->default(1);
            $table->integer('stt_tampil')->default(1);
        });
        DB::table('lokasi_kavling')->insert(['id' => 1, 'header' => 'Header lama', 'stt_tampil' => 3, 'kop_surat' => 'kop.jpg']);
        $input = ['nama_kavling' => 'Lokasi Uji', 'nama_singkat' => 'UJI', 'alamat' => 'Alamat lokasi',
            'urutan' => 2, 'nama_perusahaan' => 'Perusahaan Uji', 'alamat_perusahaan' => 'Alamat kantor',
            'telp_perusahaan' => '081234567890', 'kota_penandatangan' => 'Balikpapan',
            'nama_penandatangan' => 'Penandatangan', 'nama_admin' => 'Admin',
            'nama_mengetahui' => 'Mengetahui', 'jabatan_penandatangan' => 'Direktur'];
        $response = app(LokasiKavlingController::class)->update(Request::create('/', 'PUT', $input), 1);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('lokasi_kavling', ['id' => 1, 'nama_kavling' => 'Lokasi Uji',
            'header' => 'Header lama', 'stt_tampil' => 3, 'kop_surat' => 'kop.jpg']);
    }
}
