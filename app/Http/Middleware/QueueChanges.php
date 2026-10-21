<?php

namespace App\Http\Middleware;

use App\Models\ChangeRequest;
use App\Services\MenuAccess;
use Closure;
use Illuminate\Http\Request;

class QueueChanges
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->isSuperAdmin() || $request->isMethodSafe()
            || !\App\Services\ApprovalSettings::required(MenuAccess::menu($request), MenuAccess::ability($request))) {
            return $next($request);
        }
        $request->merge(['approval_reason' => trim((string) $request->input('approval_reason', $request->header('X-Approval-Reason')))]);
        $request->validate(['approval_reason' => 'required|string|min:5|max:2000'], [
            'approval_reason.required' => 'Alasan edit/hapus wajib diisi.',
            'approval_reason.min' => 'Alasan minimal 5 karakter.',
        ]);
        $identity = \App\Services\PendingApproval::identity($request->route()->getName(), [
            'parameters' => $request->route()->parameters(), 'input' => $request->all(),
        ]);
        foreach (ChangeRequest::where('status', 'pending')->get() as $pending) {
            if (\App\Services\PendingApproval::identity($pending->route_name, $pending->payload) === $identity) {
                $message = 'Menunggu persetujuan. Data ini sudah memiliki pengajuan yang belum diproses.';
                if ($request->expectsJson()) return response()->json(['success' => true, 'pending_approval' => true, 'message' => $message], 202);
                return redirect()->back()->with('success', $message);
            }
        }
        $encode = function ($file) use (&$encode) {
            if (is_array($file)) return array_map($encode, $file);
            abort_unless($file->isValid(), 422, 'Upload tidak valid.');
            return ['name' => $file->getClientOriginalName(), 'content' => base64_encode(file_get_contents($file->getRealPath()))];
        };
        $change = ChangeRequest::create([
            'user_id' => $request->user()->id, 'menu' => MenuAccess::menu($request),
            'action' => $request->isMethod('DELETE') ? 'hapus' : MenuAccess::ability($request),
            'route_name' => $request->route()->getName(), 'reason' => $request->approval_reason,
            'payload' => ['parameters' => $request->route()->parameters(), 'method' => $request->method(),
                'input' => $request->except(['_token', '_method', 'approval_reason']),
                'files' => array_map($encode, $request->allFiles())],
        ]);
        $message = 'Pengajuan #'.$change->id.' menunggu persetujuan Super Admin atau Direktur. Data belum berubah.';
        if ($request->expectsJson()) return response()->json(['success' => true, 'pending_approval' => true, 'message' => $message], 202);
        return redirect()->back()->with('success', $message);
    }
}
