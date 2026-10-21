@extends('admin.layout')
@section('content')
<h4>Detail pengajuan #{{ $change->id }}</h4>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@foreach($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
<div class="card"><div class="card-body">
<p>{{ $change->action }} · {{ $change->menu }} · {{ $change->status }}</p>
<p>Pengaju: {{ $change->requester?->username }} · {{ $change->created_at }}</p>
<p>Alasan: {{ $change->reason }}</p>
<button class="btn btn-primary" type="button" onclick="document.getElementById('approval-data').hidden=false; this.hidden=true">Buka data untuk disetujui</button>
<div id="approval-data" hidden class="mt-3">
<p>Data tujuan: {{ json_encode($change->payload['parameters']) }}</p>
<table class="table"><thead><tr><th>Kolom</th><th>Data yang diajukan</th></tr></thead><tbody>
@foreach($change->payload['input'] as $key => $value)
<tr><td>{{ $key }}</td><td>{{ preg_match('/password|token|secret/i', $key) ? '••••••' : (is_array($value) ? json_encode($value) : $value) }}</td></tr>
@endforeach
</tbody></table>
@if(count($change->payload['files']))<p>Lampiran: {{ implode(', ', array_keys($change->payload['files'])) }}</p>@endif
@if($change->action === 'hapus')<div class="alert alert-warning">Data tujuan akan dihapus setelah disetujui.</div>@endif
@if($change->status === 'pending')
<form method="POST" action="{{ route('approvals.review', $change) }}">@csrf
<label for="review-note">Catatan super admin</label><textarea id="review-note" name="review_note" class="form-control mb-3" maxlength="2000"></textarea>
<button name="decision" value="approved" class="btn btn-success">Setujui dan terapkan</button>
<button name="decision" value="rejected" class="btn btn-danger">Tolak</button>
</form>
@else<p>Diproses {{ $change->reviewed_at }}: {{ $change->review_note }}</p>@endif
</div></div></div>
@endsection
