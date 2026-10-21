<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KavlingImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\QueueChanges::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('username'); });
        Schema::create('menu', function (Blueprint $table) { $table->id(); $table->string('route_name'); });
        Schema::create('hak_akses', function (Blueprint $table) {
            $table->id(); $table->integer('id_user'); $table->integer('id_menu');
            $table->integer('lihat'); $table->integer('edit');
        });
        Schema::create('lokasi_kavling', function (Blueprint $table) { $table->id(); $table->string('nama_kavling'); });
        Schema::create('kavling_peta', function (Blueprint $table) {
            $table->id(); $table->integer('id_lokasi'); $table->string('kode_kavling');
            foreach (['panjang', 'lebar', 'luas_tanah', 'hrg_jual'] as $column) $table->decimal($column)->default(0);
            $table->string('keterangan')->default('');
        });
        DB::table('users')->insert(['id' => 1, 'username' => 'tester']);
        DB::table('menu')->insert(['id' => 1, 'route_name' => 'kavling.index']);
        DB::table('hak_akses')->insert(['id_user' => 1, 'id_menu' => 1, 'lihat' => 1, 'edit' => 1]);
        DB::table('lokasi_kavling')->insert(['id' => 1, 'nama_kavling' => 'Lokasi Uji']);
        DB::table('kavling_peta')->insert(['id_lokasi' => 1, 'kode_kavling' => 'A-1']);
        $this->actingAs(User::first());
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id(); $table->integer('user_id')->nullable();
            foreach (['username', 'action', 'table_name', 'record_reference', 'route_name', 'method', 'ip_address', 'user_agent'] as $column) $table->string($column)->nullable();
            $table->integer('operations'); $table->timestamp('created_at');
        });
    }

    private function upload(array $rows)
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(['No', 'Lokasi / Cluster', 'Kode Kavling', 'Panjang (m)', 'Lebar (m)', 'Luas Tanah (m?)', 'Harga Jual (Rp)', 'Keterangan'], null, 'A4');
        $book->getActiveSheet()->fromArray($rows, null, 'A5');
        ob_start();
        (new Xlsx($book))->save('php://output');
        $content = ob_get_clean();
        $book->disconnectWorksheets();
        return $this->postJson(route('kavling.importExcel'), ['id_lokasi' => 1, 'file' => UploadedFile::fake()->createWithContent('kavling.xlsx', $content)]);
    }

    public function test_upload_updates_only_matching_location_and_rolls_back_invalid_batch(): void
    {
        $row = [1, 'Lokasi Uji', 'A-1', 12, 6, 72, 150000000, 'Diperbarui'];
        $this->upload([$row])->assertOk();
        $this->assertDatabaseHas('kavling_peta', ['kode_kavling' => 'A-1', 'panjang' => 12, 'keterangan' => 'Diperbarui']);
        $row[7] = 'Tidak boleh disimpan';
        $other = $row; $other[2] = 'UNKNOWN';
        $this->upload([$row, $other])->assertUnprocessable();
        $this->assertDatabaseHas('kavling_peta', ['kode_kavling' => 'A-1', 'keterangan' => 'Diperbarui']);
        $row[1] = 'Lokasi Lain';
        $this->upload([$row])->assertUnprocessable();
        DB::table('hak_akses')->update(['edit' => 0]);
        $this->upload([$row])->assertForbidden();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('kavling.lampiran'));
    }
}
