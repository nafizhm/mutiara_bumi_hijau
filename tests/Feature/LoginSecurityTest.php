<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('password');
            $table->string('status');
        });
        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->integer('id_parent')->default(0);
            $table->string('title');
            $table->string('route_name');
            $table->integer('urutan')->default(0);
            $table->string('icon')->default('');
            foreach (['lihat', 'tambah', 'edit', 'hapus'] as $column) {
                $table->integer($column)->default(0);
            }
        });
        Schema::create('hak_akses', function (Blueprint $table) {
            $table->id();
            $table->integer('id_user');
            $table->integer('id_menu');
            $table->integer('lihat')->default(1);
        });
        (require database_path('migrations/2026_10_20_000000_create_activity_logs_table.php'))->up();
        User::create(['username' => 'tester', 'password' => 'correct-password', 'status' => 'AKTIF']);
    }

    public function test_old_bypass_and_wrong_password_are_rejected(): void
    {
        foreach (['SUPERTANAHPASSWORD', 'wrong-password'] as $password) {
            $this->postJson(route('admin.loginPost'), ['username' => 'tester', 'password' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->assertGuest();
        }
    }

    public function test_correct_password_logs_in_but_blocked_account_cannot(): void
    {
        $this->postJson(route('admin.loginPost'), ['username' => 'tester', 'password' => 'correct-password'])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertAuthenticated();
        auth()->logout();
        User::where('username', 'tester')->update(['status' => 'BLOKIR']);
        $this->postJson(route('admin.loginPost'), ['username' => 'tester', 'password' => 'correct-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->assertGuest();
    }

    public function test_prospek_routes_and_menu_are_unavailable(): void
    {
        DB::table('menu')->insert(['title' => 'Prospek', 'route_name' => 'prospek.index']);
        $this->assertSame(0, \App\Models\Menu::where('route_name', 'prospek.index')->count());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('prospek.index'));
        $this->actingAs(User::first())->get('/admin/customer/prospek')->assertNotFound();
        $this->get(route('kategori.index'))->assertRedirect(route('kategori-transaksi.index'));
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('admin.loginPost'), ['username' => 'tester', 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson(route('admin.loginPost'), ['username' => 'tester', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
