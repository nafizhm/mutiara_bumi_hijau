<?php

namespace Tests\Feature;

use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Middleware\AuthorizeMenu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PenggunaProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('surname'); $t->string('username'); $t->string('email');
            $t->string('password'); $t->string('status'); $t->integer('id_role'); $t->integer('id_marketing');
        });
        Schema::create('menu', function (Blueprint $t) { $t->id(); $t->string('route_name'); });
        Schema::create('hak_akses', function (Blueprint $t) {
            $t->id(); $t->integer('id_user'); $t->integer('id_menu');
            $t->integer('lihat'); $t->integer('edit'); $t->integer('tambah'); $t->integer('hapus');
        });
        DB::table('menu')->insert(['id' => 1, 'route_name' => 'pengguna.index']);
        DB::table('hak_akses')->insert(['id_user' => 2, 'id_menu' => 1, 'lihat' => 1, 'edit' => 0, 'tambah' => 1, 'hapus' => 1]);
        DB::table('users')->insert(['id' => 2, 'surname' => 'Nama', 'username' => 'user', 'email' => 'user@example.com',
            'password' => Hash::make('password'), 'status' => 'AKTIF', 'id_role' => 3, 'id_marketing' => 7]);
        $this->actingAs(User::find(2)->setRelation('role', (new Role)->forceFill(['role' => 'Admin'])));
    }

    private function requestFor(string $routeName, string $method, ?int $id = null): Request
    {
        $request = Request::create(route($routeName, $id === null ? [] : ['pengguna' => $id]), $method);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => auth()->user());
        return $request;
    }

    public function test_regular_user_sees_own_form_even_for_ajax_and_can_access_self_update(): void
    {
        $request = $this->requestFor('pengguna.index', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $view = app(PenggunaController::class)->index($request);
        $this->assertSame('admin.pengaturan.pengguna.profile', $view->name());
        $this->assertSame(2, $view->getData()['user']->id);
        $this->assertSame(200, app(AuthorizeMenu::class)->handle($this->requestFor('pengguna.update', 'PUT', 2), fn () => response('ok'))->getStatusCode());
    }

    public function test_other_accounts_and_create_delete_are_denied_before_approval_queue(): void
    {
        foreach ([['pengguna.edit', 'GET', 1], ['pengguna.update', 'PUT', 1], ['pengguna.store', 'POST', null], ['pengguna.destroy', 'DELETE', 2]] as [$name, $method, $id]) {
            try {
                app(AuthorizeMenu::class)->handle($this->requestFor($name, $method, $id), fn () => response('ok'));
                $this->fail('Seharusnya ditolak');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_self_update_cannot_change_role_status_or_marketing_and_keeps_blank_password(): void
    {
        $password = User::find(2)->password;
        $request = $this->requestFor('pengguna.update', 'PUT', 2);
        $request->headers->set('Accept', 'application/json');
        $request->merge(['surname' => 'Nama Baru', 'username' => 'user', 'email' => 'new@example.com',
            'password' => '', 'id_role' => 1, 'status' => 'BLOKIR', 'id_marketing' => 99]);
        app(PenggunaController::class)->update($request, 2);
        $user = User::find(2);
        $this->assertSame('Nama Baru', $user->surname);
        $this->assertSame(3, $user->id_role);
        $this->assertSame('AKTIF', $user->status);
        $this->assertSame(7, $user->id_marketing);
        $this->assertSame($password, $user->password);
    }

    public function test_superadmin_and_director_can_access_other_accounts_with_menu_permission(): void
    {
        DB::table('hak_akses')->update(['edit' => 1]);
        foreach (['Super Admin', 'Direktur'] as $role) {
            auth()->user()->setRelation('role', (new Role)->forceFill(['role' => $role]));
            $this->assertSame(200, app(AuthorizeMenu::class)->handle($this->requestFor('pengguna.edit', 'GET', 1), fn () => response('ok'))->getStatusCode());
        }
    }
}
