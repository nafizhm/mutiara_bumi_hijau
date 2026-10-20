<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteRegressionTest extends TestCase
{
    public function test_all_application_controller_routes_have_public_actions(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_starts_with($action, 'App\\') || !str_contains($action, '@')) {
                continue;
            }
            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $route->uri().' => '.$action);
            $this->assertTrue((new \ReflectionMethod($controller, $method))->isPublic(), $action);
        }
    }

    public function test_admin_get_routes_require_login(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/') || !in_array('GET', $route->methods()) || $route->getName() === 'login') {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->get('/'.$uri)->assertRedirect(route('login'));
        }
    }

    public function test_legacy_aliases_use_existing_actions_and_removed_features_stay_removed(): void
    {
        $this->assertSame('App\\Http\\Controllers\\PembayaranController@show', Route::getRoutes()->getByName('pembayaran.detail')->getActionName());
        $this->assertSame('App\\Http\\Controllers\\Customer\\UploadFileController@getFileNasabah', Route::getRoutes()->getByName('nasabah.files')->getActionName());
        foreach (['prospek.index', 'prospekData.utj', 'kavling.uploud', 'pembayaran.create', 'laporan-arus-kas.destroy'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }
}
