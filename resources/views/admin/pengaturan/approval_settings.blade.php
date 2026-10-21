@extends('admin.layout')
@section('content')
<div class="card">
    <div class="card-header"><h4>Pengaturan Persetujuan</h4></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <p>Centang tindakan yang memerlukan persetujuan Direktur atau Superadmin. Tindakan yang tidak dicentang langsung diproses. Superadmin tetap dapat memproses langsung.</p>
        <form method="POST" action="{{ route('approval-settings.update') }}">
            @csrf @method('PUT')
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead><tr><th>Nama menu</th><th class="text-center">Edit</th><th class="text-center">Hapus</th></tr></thead>
                    <tbody>
                    @forelse($menus as $menu)
                        <tr><td>{{ $menu->title }}</td>
                        @foreach(['edit' => 'Edit', 'hapus' => 'Hapus'] as $ability => $label)
                            <td class="text-center">
                            @if($menu->$ability)
                                <input type="checkbox" class="form-check-input" name="settings[{{ $menu->id }}][{{ $ability }}]" value="1"
                                    aria-label="{{ $label }} {{ $menu->title }}" @checked($rules[$menu->route_name][$ability] ?? false)>
                            @else <span class="text-muted">—</span> @endif
                            </td>
                        @endforeach
                        </tr>
                    @empty<tr><td colspan="3">Belum ada menu yang dapat diatur.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
        </form>
    </div>
</div>
@endsection
