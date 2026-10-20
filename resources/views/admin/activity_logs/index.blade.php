@extends('admin.layout')
@section('content')
    <style>
        .activity-table td { vertical-align:middle; padding:1rem .75rem; }
        .activity-table th { white-space:nowrap; font-size:.8rem; }
        .activity-meta { font-size:.8rem; color:var(--bs-secondary-color, #6c757d); overflow-wrap:anywhere; }
        .activity-pages svg { width:1.25rem; height:1.25rem; }
        @media(max-width:767.98px) {
            .activity-table thead { display:none; }
            .activity-table, .activity-table tbody, .activity-table tr, .activity-table td { display:block; width:100%; }
            .activity-table tr { padding:.75rem 0; border-bottom:1px solid var(--bs-border-color, #dee2e6); }
            .activity-table td { display:flex; gap:1rem; justify-content:space-between; padding:.4rem .25rem; border:0; }
            .activity-table td:before { content:attr(data-label); color:#6c757d; flex-shrink:0; font-size:.8rem; }
            .activity-table td > div { text-align:right; min-width:0; }
            .activity-table .activity-empty { display:block; text-align:center; }
        }
    </style>
    <div class="page-content">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-1">Log Aktivitas</h3>
                <p class="text-muted small mb-0">Riwayat login, logout, dan perubahan data. Waktu ditampilkan dalam WIB.</p>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-3 mb-4">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small" for="q">Cari aktivitas</label>
                        <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Pengguna, data, atau IP">
                    </div>
                    <div class="col-12 col-sm-4 col-lg-2">
                        <label class="form-label small" for="action">Tindakan</label>
                        <select class="form-select" id="action" name="action">
                            <option value="">Semua tindakan</option>
                            @foreach (['login' => 'Login', 'logout' => 'Logout', 'tambah' => 'Tambah', 'edit' => 'Edit', 'hapus' => 'Hapus'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <label class="form-label small" for="from">Dari tanggal</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <label class="form-label small" for="to">Sampai tanggal</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="col-12 col-lg-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary flex-fill" type="submit">Terapkan</button>
                        <a class="btn btn-light" href="{{ route('activity-logs.index') }}">Reset</a>
                    </div>
                </form>
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <p class="text-muted small">{{ number_format($logs->total(), 0, ',', '.') }} aktivitas ditemukan</p>
                <table class="table table-hover activity-table">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Tindakan</th><th>Aktivitas</th><th>Alamat IP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $color = match ($log->action) { 'login', 'tambah' => 'success', 'hapus' => 'danger', 'edit' => 'primary', default => 'secondary' };
                            @endphp
                            <tr>
                                <td data-label="Waktu"><div>{{ \Carbon\Carbon::parse($log->created_at, 'UTC')->timezone('Asia/Jakarta')->format('d M Y') }}<div class="activity-meta">{{ \Carbon\Carbon::parse($log->created_at, 'UTC')->timezone('Asia/Jakarta')->format('H:i:s') }} WIB</div></div></td>
                                <td data-label="Pengguna"><div class="fw-semibold">{{ $log->username }}</div></td>
                                <td data-label="Tindakan"><div><span class="badge bg-{{ $color }}">{{ ucfirst($log->action) }}</span></div></td>
                                <td data-label="Aktivitas"><div>
                                    <div>{{ $log->table_name ? \Illuminate\Support\Str::headline($log->table_name) : ($log->action === 'login' ? 'Masuk aplikasi' : 'Keluar aplikasi') }}</div>
                                    @if ($log->record_reference)<div class="activity-meta">{{ $log->record_reference }}</div>@endif
                                    @if ($log->operations > 1)<div class="activity-meta">{{ $log->operations }} operasi data</div>@endif
                                </div></td>
                                <td data-label="Alamat IP"><div class="activity-meta">{{ $log->ip_address ?? '-' }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="activity-empty text-center text-muted py-5">Belum ada aktivitas yang sesuai. Riwayat akan muncul saat pengguna beraktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="activity-pages">{{ $logs->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </div>
@endsection
