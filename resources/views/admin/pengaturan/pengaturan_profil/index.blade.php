@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-content-center justify-content-between">
                        <h3 class="font-weight-bold text-xl">Pengaturan Profil</h3>
                    </div>
                </div>
                <form id="formData">
                    @csrf
                    <div class="card-body">
                        <input type="hidden" value="{{ $data->id }}" name="primary_id" id="primary_id">

                        <div class="row mb-3 align-items-center">
                            <label for="nama_perusahaan" class="col-sm-3 col-form-label">Nama Perusahaan</label>
                            <div class="col-9">
                                <input type="text" class="form-control" id="nama_perusahaan" name="nama_perusahaan"
                                    value="{{ old('nama_perusahaan', $data->nama_perusahaan ?? '') }}">
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="email" class="col-sm-3 col-form-label">Email</label>
                            <div class="col-9">
                                <input type="email" class="form-control" id="email" name="email"
                                    value="{{ old('email', $data->email ?? '') }}">
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="alamat" class="col-sm-3 col-form-label">Alamat</label>
                            <div class="col-9">
                                <textarea class="form-control" id="alamat" name="alamat" rows="3">{{ old('alamat', $data->alamat ?? '') }}</textarea>
                            </div>
                        </div>

                        <div class="row mb-3 align-items-center">
                            <label for="telp" class="col-sm-3 col-form-label">No Telepon</label>
                            <div class="col-9">
                                <input type="text" class="form-control" id="telp" name="telp"
                                    value="{{ old('telp', $data->telp ?? '') }}">
                            </div>
                        </div>





                        <div class="row mb-3 align-items-center">
                            <label for="pesan_jatuh_tempo" class="col-sm-3 col-form-label">Pesan Jatuh Tempo</label>
                            <div class="col-9">
                                <textarea class="form-control" id="pesan_jatuh_tempo" name="pesan_jatuh_tempo" rows="5">{{ old('pesan_jatuh_tempo', $data->pesan_jatuh_tempo ?? '') }}</textarea>

                                <small class="text-danger">
                                    [[nama_customer]] : Nama Customer Jatuh Tempo <br>
                                    [[jumlah_bulan]] : Jumlah Bulan Tunggakan <br>
                                    [[jumlah_tagihan]] : Total Nominal Tagihan
                                </small>
                            </div>
                        </div>
                    </div>
                    @if (isset($permissions['edit']) && $permissions['edit'] == 1)
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                    aria-hidden="true"></span>
                                <span class="button-text">Simpan Pengaturan</span>
                            </button>
                        </div>
                    @endif
                </form>
        </section>
    </div>
    <div class="card mt-4">
        <div class="card-header"><h4>Media Aplikasi</h4><p class="text-muted mb-0">Upload logo, favicon, kop surat, dan background login. JPG, PNG, atau WebP, maksimal 3 MB. Background yang diupload langsung digunakan pada halaman login.</p></div>
        <div class="card-body"><div class="row g-4">
            @foreach ($mediaList as $media)
                @php($mediaLabel = $media->jenis_data === 'kwitansi' ? 'Background Login' : ucwords($media->jenis_data))
                <div class="col-12 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <h6>{{ $mediaLabel }}</h6>
                        @if ($media->nama_file)<img src="{{ asset('config_media/'.$media->nama_file) }}" alt="{{ $media->jenis_data }}" class="img-fluid mb-3" style="max-height:140px">@endif
                        @if ($canEditMedia)
                            <form class="profile-media-upload" action="{{ route('pengaturan-media.update', $media->id) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="id" value="{{ $media->id }}">
                                <input type="file" name="nama_file" class="form-control mb-2" accept="image/jpeg,image/png,image/webp" required>
                                <div class="media-upload-error text-danger small mb-2"></div>
                                <button type="submit" class="btn btn-primary btn-sm">Upload {{ $mediaLabel }}</button>
                            </form>
                        @else
                            <p class="text-muted small">Akun ini tidak memiliki izin edit Media Aplikasi.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div></div>
    </div>
@endsection
@push('scripts')
    <script>
        $('.profile-media-upload').on('submit', function (event) {
            event.preventDefault();
            const form = $(this), button = form.find('button[type="submit"]');
            button.prop('disabled', true);
            form.find('.media-upload-error').text('');
            $.ajax({url: form.attr('action'), method: 'POST', data: new FormData(this), contentType: false, processData: false, headers: {'Accept': 'application/json'}})
                .done(function () { window.location.reload(); })
                .fail(function (xhr) { form.find('.media-upload-error').text(xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : 'Upload gagal. Silakan coba lagi.'); })
                .always(function () { button.prop('disabled', false); });
        });
        var permissions = @json($permissions);
        var audio = new Audio('{{ asset('audio/notification.ogg') }}');

        $('#formData').on('submit', function(e) {
            e.preventDefault();

            let submitBtn = $('#submitBtn');
            let spinner = submitBtn.find('.spinner-border');
            let btnText = submitBtn.find('.button-text');

            spinner.removeClass('d-none');
            btnText.text('Menyimpan...');
            submitBtn.prop('disabled', true);

            let id = $('#primary_id').val();
            let url = '{{ route('pengaturan-profile.update', ['pengaturan_profile' => ':id']) }}'.replace(':id',
                id);
            let method = 'PUT';

            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            let formData = new FormData(this);
            formData.append('id', id);
            formData.append('_method', method);

            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(res) {
                    if (res.success) {
                        audio.play();
                        let msg = "Pengaturan berhasil diupdate!";
                        toastr.success(msg, "BERHASIL", {
                            progressBar: true,
                            timeOut: 3500,
                            positionClass: "toast-bottom-right",
                            onHidden: function() {
                                window.location.href =
                                    '{{ route('pengaturan-profile.index') }}';
                            }
                        });
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        audio.play();
                        toastr.error("Ada inputan yang salah!", "GAGAL!", {
                            progressBar: true,
                            timeOut: 3500,
                            positionClass: "toast-bottom-right",
                        });

                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, val) {
                            let input = $('#' + key);
                            input.addClass('is-invalid');
                            input.parent().find('.invalid-feedback').remove();
                            input.parent().append(
                                '<span class="invalid-feedback" role="alert"><strong>' +
                                val[0] + '</strong></span>'
                            );
                        });
                    } else {
                        audio.play();
                        let msg = xhr.responseJSON?.message || "Terjadi kesalahan server!";
                        toastr.error(msg, "ERROR!", {
                            progressBar: true,
                            timeOut: 3500,
                            positionClass: "toast-bottom-right",
                        });
                    }
                },
                complete: function() {
                    spinner.addClass('d-none');
                    btnText.text('Simpan Pengaturan');
                    submitBtn.prop('disabled', false);
                }
            });
        });
    </script>
@endpush
