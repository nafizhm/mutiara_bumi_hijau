<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customer', function (Blueprint $table) {
            $table->id();
            $table->integer('stt_arsip');
            $table->integer('id_marketing');
        });
        Schema::create('transaksi_kavling', function (Blueprint $table) {
            $table->id();
            $table->integer('id_customer');
            $table->date('tgl_terima')->nullable();
        });
        DB::table('customer')->insert([
            ['id' => 1, 'stt_arsip' => 0, 'id_marketing' => 11],
            ['id' => 2, 'stt_arsip' => 0, 'id_marketing' => 12],
            ['id' => 3, 'stt_arsip' => 1, 'id_marketing' => 11],
        ]);
        DB::table('transaksi_kavling')->insert([
            ['id_customer' => 1, 'tgl_terima' => '2026-01-01'],
            ['id_customer' => 1, 'tgl_terima' => '2026-10-20'],
            ['id_customer' => 1, 'tgl_terima' => '2026-10-20'],
            ['id_customer' => 2, 'tgl_terima' => '2026-10-20'],
            ['id_customer' => 3, 'tgl_terima' => '2026-10-20'],
            ['id_customer' => 99, 'tgl_terima' => '2026-10-20'],
            ['id_customer' => 1, 'tgl_terima' => null],
            ['id_customer' => 1, 'tgl_terima' => '2025-12-01'],
        ]);
    }

    public function test_sales_chart_counts_sold_lots_by_transaction_month_without_income_categories(): void
    {
        $data = app(DashboardController::class)->chart1(Request::create('/', 'GET', ['tahun' => 2026]))->getData(true);
        $this->assertCount(12, $data);
        $this->assertSame(1, $data[0]);
        $this->assertSame(3, $data[9]);
        $this->assertSame(4, array_sum($data));
    }

    public function test_marketing_and_year_filters_use_the_same_sales_source(): void
    {
        $controller = app(DashboardController::class);
        $data = $controller->chart2(Request::create('/', 'GET', ['tahun' => 2026, 'marketing' => 11]))->getData(true);
        $this->assertSame(1, $data[0]);
        $this->assertSame(2, $data[9]);
        $this->assertSame(3, array_sum($data));
        $all = $controller->chart2(Request::create('/', 'GET', ['tahun' => 0, 'marketing' => 0]))->getData(true);
        $this->assertSame(1, $all[11]);
        $this->assertSame(5, array_sum($all));
        $empty = $controller->chart1(Request::create('/', 'GET', ['tahun' => 2024]))->getData(true);
        $this->assertSame(array_fill(0, 12, 0), $empty);
    }
}
