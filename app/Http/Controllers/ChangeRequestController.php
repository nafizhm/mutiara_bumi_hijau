<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Http\Middleware\AuthorizeMenu;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

class ChangeRequestController extends Controller
{
    public function statuses()
    {
        return response()->json(['data' => \App\Services\PendingApproval::statuses()])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $changes = ChangeRequest::with('requester')->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest()->paginate(25);
        return view('admin.approvals.index', compact('changes'));
    }

    public function show(Request $request, ChangeRequest $change)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        return view('admin.approvals.show', compact('change'));
    }

    public function review(Request $request, ChangeRequest $change)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $request->validate(['decision' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $change) {
            $change = ChangeRequest::whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($change->status === 'pending', 409, 'Pengajuan sudah diproses.');
            if ($request->decision === 'approved') $this->apply($request, $change);
            $change->update(['status' => $request->decision, 'reviewer_id' => $request->user()->id,
                'reviewed_at' => now(), 'review_note' => $request->review_note]);
        });
        return redirect()->route('approvals.show', $change)->with('success', 'Pengajuan berhasil diproses.');
    }

    private function apply(Request $review, ChangeRequest $change): void
    {
        $user = $change->requester;
        abort_unless($user && $user->status !== 'BLOKIR', 422, 'Pengaju sudah tidak tersedia atau diblokir.');
        $payload = $change->payload;
        $temporary = [];
        $decode = function ($value) use (&$decode, &$temporary) {
            if (!isset($value['content'], $value['name'])) return array_map($decode, $value);
            $path = tempnam(sys_get_temp_dir(), 'approval');
            $temporary[] = $path;
            file_put_contents($path, base64_decode($value['content'], true));
            return new UploadedFile($path, $value['name'], null, null, true);
        };
        $original = app('request');
        $originalUser = auth()->user();
        try {
            $files = array_map($decode, $payload['files']);
            $pending = Request::create(route($change->route_name, $payload['parameters']), $payload['method'], $payload['input'], [], $files,
                ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            $pending->setLaravelSession($review->session());
            $pending->setUserResolver(fn () => $user);
            $route = Route::getRoutes()->match($pending);
            abort_unless($route->getName() === $change->route_name, 422);
            $pending->setRouteResolver(fn () => $route);
            app()->instance('request', $pending);
            auth()->setUser($user);
            // Run the existing controller, with the requester's current menu permissions.
            $response = app(AuthorizeMenu::class)->handle($pending, fn () => $route->run());
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData(true);
                if ($response->getStatusCode() >= 400 || ($data['success'] ?? true) === false) {
                    throw ValidationException::withMessages(['approval' => $data['message'] ?? 'Perubahan gagal diterapkan.']);
                }
            } elseif (method_exists($response, 'getStatusCode') && $response->getStatusCode() >= 400) {
                throw ValidationException::withMessages(['approval' => 'Perubahan gagal diterapkan.']);
            }
        } finally {
            app()->instance('request', $original);
            auth()->setUser($originalUser);
            foreach ($temporary as $path) @unlink($path);
        }
    }
}
