<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorizeMenu
{
    public function handle(Request $request, Closure $next)
    {
        $controller = class_basename($request->route()->getControllerClass() ?? '');
        $selfProfile = false;
        if ($controller === 'PenggunaController' && !$request->user()->canApproveChanges()) {
            $action = $request->route()->getActionMethod();
            $selfProfile = in_array($action, ['edit', 'update'], true)
                && (string) $request->route('pengguna') === (string) $request->user()->id;
            abort_unless($action === 'index' || $selfProfile, 403, 'Anda hanya dapat mengelola profil sendiri.');
        }
        $menu = \App\Services\MenuAccess::menu($request);
        if (!$menu) {
            return $next($request);
        }
        $action = $request->route()->getActionMethod();
        if ($controller === 'PiutangController' && in_array($action, ['pay', 'payable'])) {
            abort_unless(\App\Services\ReceivablePayment::canPay($request->user()->id), 403);
            return $next($request);
        }
        $ability = $selfProfile ? 'lihat' : \App\Services\MenuAccess::ability($request);
        $menus = $ability === 'lihat' ? \App\Services\MenuAccess::readMenus($request) : [$menu];
        $allowed = DB::table('hak_akses as h')->join('menu as m', 'm.id', '=', 'h.id_menu')
            ->where('h.id_user', $request->user()->id)->whereIn('m.route_name', $menus)
            ->where('h.lihat', 1)->where('h.'.$ability, 1)->exists();
        abort_unless($allowed, 403, 'Anda tidak memiliki izin untuk tindakan ini.');
        return $next($request);
    }
}
