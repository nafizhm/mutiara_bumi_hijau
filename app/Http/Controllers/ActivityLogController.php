<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(DB::table('hak_akses as h')->join('menu as m', 'm.id', '=', 'h.id_menu')
            ->where('h.id_user', $request->user()->id)->where('h.lihat', 1)
            ->where('m.route_name', 'activity-logs.index')->exists(), 403);

        $filters = $request->validate([
            'action' => 'nullable|in:login,logout,tambah,edit,hapus',
            'q' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]),
        ]);
        $query = DB::table('activity_logs')->orderByDesc('id');
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';
            $query->where(fn ($q) => $q->where('username', 'like', $search)->orWhere('table_name', 'like', $search)
                ->orWhere('route_name', 'like', $search)->orWhere('ip_address', 'like', $search)->orWhere('record_reference', 'like', $search));
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], 'Asia/Jakarta')->startOfDay()->utc());
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], 'Asia/Jakarta')->endOfDay()->utc());
        }
        $logs = $query->paginate(25)->withQueryString();
        return view('admin.activity_logs.index', compact('logs', 'filters'));
    }
}
