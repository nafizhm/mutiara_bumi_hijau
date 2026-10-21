@extends('admin.layout')
@section('content')
<h4>Persetujuan edit dan hapus</h4>
<div class="card"><div class="card-body table-responsive">
<table class="table"><thead><tr><th>Pengajuan</th><th>Menu</th><th>Pengaju</th><th>Alasan</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($changes as $change)
@php
    $statusBadge = match ($change->status) {
        'pending' => ['class' => 'bg-warning text-dark', 'label' => 'Menunggu persetujuan'],
        'approved' => ['class' => 'bg-success text-white', 'label' => 'Disetujui'],
        'rejected' => ['class' => 'bg-danger text-white', 'label' => 'Ditolak'],
        default => ['class' => 'bg-secondary text-white', 'label' => $change->status],
    };
@endphp
<tr><td>#{{ $change->id }} · {{ $change->action }}<br>{{ $change->created_at }}</td><td>{{ $change->menu }}</td><td>{{ $change->requester?->username }}</td><td>{{ $change->reason }}</td><td><span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span></td><td><a href="{{ route('approvals.show', $change) }}">Lihat detail</a></td></tr>
@empty<tr><td colspan="6">Belum ada pengajuan.</td></tr>@endforelse
</tbody></table>{{ $changes->links() }}
</div></div>
@endsection
