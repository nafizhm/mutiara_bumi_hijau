<?php

namespace App\Services;

use App\Models\ChangeRequest;
use Illuminate\Support\Facades\Route;

class PendingApproval
{
    public static function identity(string $name, array $payload): string
    {
        $route = Route::getRoutes()->getByName($name);
        $controller = $route?->getControllerClass() ?? $name;
        $action = $route?->getActionMethod();
        // Payments, bills and customer prices use different record IDs.
        $group = class_basename($controller) === 'PembayaranController' ? $action : 'record';
        $parameters = array_map('strval', array_values($payload['parameters'] ?? []));
        if (!$parameters) {
            $input = $payload['input'] ?? [];
            $parameters = [(string) ($input['id_user'] ?? $input['id'] ?? $input['primary_id'] ?? $action)];
        }
        return json_encode([$controller, $group, $parameters]);
    }

    public static function statuses(): array
    {
        $routes = Route::getRoutes();
        return ChangeRequest::where('status', 'pending')->get()->map(function ($change) use ($routes) {
            if (!auth()->user()?->isSuperAdmin() && !ReceivablePayment::canManage(auth()->id(), $change->menu, 'lihat')) return null;
            $source = $routes->getByName($change->route_name);
            if (!$source) return null;
            $payload = $change->payload;
            $parameters = array_values($payload['parameters'] ?? []);
            $urls = [];
            $pages = [];
            foreach ($routes as $route) {
                if (!$route->getName() || $route->getControllerClass() !== $source->getControllerClass()) continue;
                if ($route->getActionMethod() === 'index') $pages[] = route($route->getName(), [], false);
                $sameRecord = class_basename($source->getControllerClass()) !== 'PembayaranController'
                    && in_array($route->getActionMethod(), ['edit', 'show', 'update', 'destroy', 'editDetail', 'setting', 'updateDetail', 'updateSetting', 'restore', 'deleteFile']);
                if ($route->getName() !== $change->route_name && !$sameRecord) continue;
                if (count($route->parameterNames()) !== count($parameters)) continue;
                $urls[] = route($route->getName(), $parameters, false);
            }
            return ['id' => $change->id, 'urls' => array_values(array_unique($urls)),
                'pages' => $pages, 'record' => count($parameters) === 1 ? (string) $parameters[0] : null];
        })->filter()->values()->all();
    }
}
