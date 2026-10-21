<?php

namespace Tests\Feature;

use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Http\Middleware\AuthorizeMenu;
use App\Models\User;
use App\Services\MenuAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MenuPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('menu', function (Blueprint $t) { $t->id(); $t->string('route_name'); });
        Schema::create('hak_akses', function (Blueprint $t) {
            $t->id(); $t->integer('id_user'); $t->integer('id_menu');
            foreach (['lihat', 'tambah', 'edit', 'hapus'] as $field) $t->integer($field)->default(0);
        });
        foreach (array_unique([...array_values(MenuAccess::menus()), 'arsip-customer.index']) as $name) {
            $id = DB::table('menu')->insertGetId(['route_name' => $name]);
            DB::table('hak_akses')->insert(['id_user' => 1, 'id_menu' => $id]);
        }
        $this->actingAs((new User)->forceFill(['id' => 1, 'username' => 'tester']));
    }

    private function requestFor($route): Request
    {
        $request = Request::create('/'.$route->uri(), $route->methods()[0]);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => auth()->user());
        return $request;
    }

    private function assertDenied(Request $request): void
    {
        try {
            app(AuthorizeMenu::class)->handle($request, fn () => response('allowed'));
            $this->fail('Route harus ditolak: '.$request->route()->getName());
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_every_menu_route_accepts_only_its_required_permission(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (!in_array(AuthorizeMenu::class, $route->gatherMiddleware(), true)) continue;
            $request = $this->requestFor($route);
            $menu = MenuAccess::menu($request);
            if (!$menu) {
                // Approval routes enforce the Super Admin role in the controller.
                $this->assertTrue($route->getControllerClass() === null || in_array(class_basename($route->getControllerClass()), ['AuthController', 'ChangeRequestController']), $route->uri());
                continue;
            }
            DB::table('hak_akses')->update(['lihat' => 0, 'tambah' => 0, 'edit' => 0, 'hapus' => 0]);
            $this->assertDenied($request);
            $ability = MenuAccess::ability($request);
            if (class_basename($route->getControllerClass()) === 'PiutangController' && in_array($route->getActionMethod(), ['pay', 'payable'], true)) $ability = 'edit';
            $menuId = DB::table('menu')->where('route_name', $menu)->value('id');
            DB::table('hak_akses')->where('id_menu', $menuId)->update(['lihat' => 1, $ability => 1]);
            $this->assertSame(200, app(AuthorizeMenu::class)->handle($request, fn () => response('allowed'))->getStatusCode(), $route->uri());
            if ($ability !== 'lihat') {
                DB::table('hak_akses')->where('id_menu', $menuId)->update([$ability => 0]);
                $this->assertDenied($request);
            }
            $count++;
        }
        $this->assertGreaterThan(100, $count);
    }

    public function test_shared_finance_reads_work_without_granting_mutation_permissions(): void
    {
        foreach ([['PiutangController', 'show', 'pemasukan.index'],
            ['PiutangController', 'getSisaBayar', 'pemasukan.index'],
            ['HutangController', 'getSisaBayar', 'pengeluaran.index']] as [$controller, $action, $source]) {
            DB::table('hak_akses')->update(['lihat' => 0, 'tambah' => 0, 'edit' => 0, 'hapus' => 0]);
            DB::table('hak_akses')->where('id_menu', DB::table('menu')->where('route_name', $source)->value('id'))->update(['lihat' => 1]);
            foreach (Route::getRoutes() as $route) {
                if (class_basename($route->getControllerClass() ?? '') !== $controller || $route->getActionMethod() !== $action) continue;
                $request = $this->requestFor($route);
                $this->assertSame(200, app(AuthorizeMenu::class)->handle($request, fn () => response('allowed'))->getStatusCode());
            }
            $name = $controller === 'PiutangController' ? 'piutang.update' : 'hutang.update';
            $this->assertDenied($this->requestFor(Route::getRoutes()->getByName($name)));
        }
    }

    public function test_special_actions_use_the_same_ability_as_their_menu(): void
    {
        foreach ([
            'pembayaran.tambah-tagihan' => 'edit', 'pembayaran.delete-tagihan' => 'edit',
            'pembayaran.tambah-pemasukan' => 'edit', 'pembayaran.delete-pemasukan' => 'edit',
            'pembayaran.kirim-kwitansi' => 'edit', 'customer.restore' => 'hapus',
            'kirimPesanWa' => 'edit', 'kavling.importExcel' => 'edit',
            'lokasi-kavling.setting' => 'edit', 'lokasi-kavling.editDetail' => 'edit',
            'customer.create' => 'tambah', 'customer.edit' => 'edit',
            'st-penjualan.cetak' => 'lihat',
        ] as $name => $expected) {
            $this->assertSame($expected, MenuAccess::ability($this->requestFor(Route::getRoutes()->getByName($name))), $name);
        }
        $this->assertSame('arsip-customer.index', MenuAccess::menu($this->requestFor(Route::getRoutes()->getByName('customer.restore'))));
    }

    public function test_detail_pages_resolve_permissions_from_their_parent_menu(): void
    {
        $original = app('request');
        try {
            foreach (['lokasi-kavling.detail' => 'lokasi-kavling.index', 'lokasi-kavling.setting' => 'lokasi-kavling.index',
                'arsip-customer.index' => 'arsip-customer.index', 'pengaturan-media.edit' => 'pengaturan-profile.index'] as $route => $menu) {
                DB::table('hak_akses')->update(['edit' => 0]);
                DB::table('hak_akses')->where('id_menu', DB::table('menu')->where('route_name', $menu)->value('id'))->update(['lihat' => 1, 'edit' => 1]);
                app()->instance('request', $this->requestFor(Route::getRoutes()->getByName($route)));
                $this->assertSame(1, HakAksesController::getUserPermissions()['edit'], $route);
            }
        } finally {
            app()->instance('request', $original);
        }
    }
}

