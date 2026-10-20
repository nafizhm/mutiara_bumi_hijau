<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('username'); $table->string('password'); $table->string('remember_token')->nullable();
        });
        Schema::create('menu', function (Blueprint $table) {
            $table->id(); $table->integer('id_parent'); $table->string('title'); $table->string('route_name');
            $table->string('icon'); $table->integer('urutan');
            foreach (['lihat', 'tambah', 'edit', 'hapus'] as $column) $table->integer($column);
        });
        Schema::create('hak_akses', function (Blueprint $table) {
            $table->id(); $table->integer('id_user'); $table->integer('id_menu');
            foreach (['lihat', 'beranda', 'tambah', 'edit', 'hapus'] as $column) $table->integer($column)->default(0);
        });
        Schema::create('bank', function (Blueprint $table) { $table->id(); $table->string('nama'); });
        DB::table('users')->insert(['id' => 1, 'username' => 'tester', 'password' => bcrypt('test-password')]);
        DB::table('menu')->insert(['id' => 1, 'id_parent' => 0, 'title' => 'Pengguna', 'route_name' => 'pengguna.index', 'icon' => '', 'urutan' => 1, 'lihat' => 1, 'tambah' => 0, 'edit' => 0, 'hapus' => 0]);
        DB::table('hak_akses')->insert(['id_user' => 1, 'id_menu' => 1, 'lihat' => 1]);
        (require database_path('migrations/2026_10_20_000000_create_activity_logs_table.php'))->up();

        Route::middleware('web')->post('/_activity-test/login', function () {
            Auth::login(User::findOrFail(1));
            return response()->json(['success' => true]);
        });
        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/_activity-test/logout', function () { Auth::logout(); return response()->json(['success' => true]); });
            Route::post('/_activity-test/create', function () {
                DB::table('bank')->insert(['nama' => 'SECRET-FORM-VALUE']);
                return response()->json(['success' => true]);
            })->name('test-bank.store');
            Route::patch('/_activity-test/{id}', function ($id) {
                DB::table('bank')->where('id', $id)->update(['nama' => 'Updated']);
                return response()->json(['success' => true]);
            })->name('test-bank.update');
            Route::delete('/_activity-test/{id}', function ($id) {
                DB::table('bank')->where('id', $id)->delete();
                return response()->json(['success' => true]);
            })->name('test-bank.destroy');
            Route::post('/_activity-test/rollback', function () {
                DB::beginTransaction();
                DB::table('bank')->insert(['nama' => 'Rolled back']);
                DB::rollBack();
                return response()->json(['success' => false]);
            });
            Route::post('/_activity-test/nested', function () {
                DB::transaction(function () {
                    DB::table('bank')->insert(['nama' => 'Committed']);
                    DB::beginTransaction();
                    DB::table('bank')->insert(['nama' => 'Rolled back']);
                    DB::rollBack();
                });
                return response()->json(['success' => true]);
            });
        });
    }

    public function test_login_and_logout_are_recorded_with_actor(): void
    {
        $this->post('/_activity-test/login')->assertOk();
        $this->post('/_activity-test/logout')->assertOk();
        $this->assertSame(['login', 'logout'], DB::table('activity_logs')->orderBy('id')->pluck('action')->all());
        $this->assertSame('tester', DB::table('activity_logs')->value('username'));
    }

    public function test_query_builder_mutations_record_metadata_without_form_secrets(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->post('/_activity-test/create', ['password' => 'DO-NOT-LOG'])->assertOk();
        $id = DB::table('bank')->value('id');
        $this->patch('/_activity-test/' . $id)->assertOk();
        $this->delete('/_activity-test/' . $id)->assertOk();
        $logs = DB::table('activity_logs')->orderBy('id')->get();
        $this->assertSame(['tambah', 'edit', 'hapus'], $logs->pluck('action')->all());
        $this->assertSame(['id=' . $id], $logs->pluck('record_reference')->unique()->values()->all());
        $this->assertStringNotContainsString('SECRET-FORM-VALUE', $logs->toJson());
        $this->assertStringNotContainsString('DO-NOT-LOG', $logs->toJson());
    }

    public function test_rolled_back_changes_are_not_logged(): void
    {
        $this->actingAs(User::findOrFail(1))->post('/_activity-test/rollback')->assertOk();
        $this->assertSame(0, DB::table('activity_logs')->count());
        $this->assertSame(0, DB::table('bank')->count());
        $this->post('/_activity-test/nested')->assertOk();
        $this->assertSame(1, DB::table('activity_logs')->count());
        $this->assertSame(1, DB::table('activity_logs')->value('operations'));
        $this->assertSame(1, DB::table('bank')->count());
    }

    public function test_logs_require_permission_and_dates_are_validated(): void
    {
        DB::table('hak_akses')->where('id_menu', DB::table('menu')->where('route_name', 'activity-logs.index')->value('id'))->update(['lihat' => 0]);
        $this->actingAs(User::findOrFail(1))->get('/admin/log-aktivitas')->assertForbidden();
        DB::table('hak_akses')->update(['lihat' => 1]);
        $this->getJson('/admin/log-aktivitas?from=invalid')->assertUnprocessable();
    }

    public function test_filters_use_wib_day_boundaries_and_allow_end_date_only(): void
    {
        foreach (['2026-10-19 16:59:59', '2026-10-19 17:00:00', '2026-10-20 17:00:00'] as $timestamp) {
            DB::table('activity_logs')->insert([
                'user_id' => 1, 'username' => 'tester', 'action' => 'login',
                'method' => 'POST', 'created_at' => $timestamp,
            ]);
        }
        $controller = app(\App\Http\Controllers\ActivityLogController::class);
        $request = \Illuminate\Http\Request::create('/admin/log-aktivitas', 'GET', [
            'from' => '2026-10-20', 'to' => '2026-10-20', 'q' => 'tester', 'action' => 'login',
        ]);
        $request->setUserResolver(fn () => User::findOrFail(1));
        $logs = $controller->index($request)->getData()['logs'];
        $this->assertSame(1, $logs->total());
        $this->assertSame('2026-10-19 17:00:00', $logs->first()->created_at);

        $request = \Illuminate\Http\Request::create('/admin/log-aktivitas', 'GET', ['to' => '2026-10-20']);
        $request->setUserResolver(fn () => User::findOrFail(1));
        $this->assertSame(2, $controller->index($request)->getData()['logs']->total());
    }
}
