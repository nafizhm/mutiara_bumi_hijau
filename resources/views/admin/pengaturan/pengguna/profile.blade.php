@extends('admin.layout')
@section('content')
<div class="card">
    <div class="card-header"><h4>Profil Pengguna</h4></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('pengguna.update', $user->id) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="surname" class="form-label">Nama lengkap</label>
                <input id="surname" name="surname" class="form-control" value="{{ old('surname', $user->surname) }}" required maxlength="255" autocomplete="name">
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input id="username" name="username" class="form-control" value="{{ old('username', $user->username) }}" required maxlength="255" autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password baru</label>
                <input type="password" id="password" name="password" class="form-control" minlength="6" autocomplete="new-password">
                <small class="text-muted">Kosongkan jika tidak ingin mengubah password.</small>
            </div>
            <div class="mb-3"><span class="text-muted">Role:</span> {{ $user->role?->role ?? '-' }}</div>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>
@endsection
