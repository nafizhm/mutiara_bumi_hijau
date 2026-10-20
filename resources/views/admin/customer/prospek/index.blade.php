@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-content-center justify-content-between">
                        <h3 class="font-weight-bold text-xl">Data Prospek</h3>
                        <div class="d-flex align-items-center">
                            @if (isset($permissions['tambah']) && $permissions['tambah'] == 1)
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalForm">
                                    <i class="bi bi-plus-lg"></i> Tambah Prospek
                                </button>
                            @endif
                            <a href="javascript:void(0);" onclick="reloadTable()"
                                class="btn btn-light btn-rounded btn-sm ms-2" title="Reload Tabel">
                                <i class="bi bi-arrow-clockwise"></i> Reload
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table data-table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama User</th>
                                    <th>No.Tlp</th>
                                    <th>Pekerjaan</th>
                                    <th class="text-center">Rangking</th>
                                    <th>Keterangan</th>
                                    <th class="text-center" width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade text-left" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel"
        aria-hidden="true" data-backdrop="static" data-keyboard="false" data-focus="false">

        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title white" id="myModalLabel160">Form Data Prospek
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form id="formData">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" id="primary_id" name="primary_id">
                        <div class="row mb-3">
                            <label for="tgl_terima" class="col-sm-3 col-form-label">Tanggal</label>
                            <div class="col-sm-8">
                                <input type="date" name="tgl_terima" id="tgl_terima" class="form-control"
                                    value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="nama_lengkap" class="col-sm-3 col-form-label">Nama Lengkap</label>
                            <div class="col-sm-8">
                                <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="no_wa" class="col-sm-3 col-form-label">No. Telp</label>
                            <div class="col-sm-8">
                                <input type="number" name="no_wa" id="no_wa" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="usia" class="col-sm-3 col-form-label">Usia</label>
                            <div class="col-sm-8">
                                <select name="usia" id="usia" class="form-control select-usia" style="width: 100%;">
                                    <option value=""></option>
                                    @for ($i = 17; $i <= 60; $i++)
                                        <option value="{{ $i }}">{{ $i }} tahun</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="pekerjaan" class="col-sm-3 col-form-label">Pekerjaan</label>
                            <div class="col-sm-3">
                                <input type="text" name="pekerjaan" id="pekerjaan" class="form-control">
                            </div>
                            <label for="penghasilan" class="col-sm-2 col-form-label">Penghasilan</label>
                            <div class="col-sm-3">
                                <input type="text" name="penghasilan" id="penghasilan"
                                    class="form-control format-number">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="sumber_informasi" class="col-sm-3 col-form-label">Sumber Informasi</label>
                            <div class="col-sm-8">
                                <input type="text" name="sumber_informasi" id="sumber_informasi"
                                    class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="rangking" class="col-sm-3 col-form-label">Rangking</label>
                            <div class="col-sm-8">
                                <select name="rangking" id="rangking" class="form-control select-rank">
                                    <option value=""></option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="X">X</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="id_marketing" class="col-sm-3 col-form-label">Marketing</label>
                            <div class="col-sm-8">
                                <select name="id_marketing" id="id_marketing" class="form-control select-marketing">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="keterangan_belum" class="col-sm-3 col-form-label">Keterangan</label>
                            <div class="col-sm-8">
                                <input type="text" name="keterangan_belum" id="keterangan_belum"
                                    class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            <span class="button-text">Batal</span>
                        </button>
                        <button type="submit" class="btn btn-primary ms-1" id="submitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                aria-hidden="true"></span>
                            <span class="button-text">Simpan</span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select-rank').select2({
                dropdownParent: $('#modalForm'),
                width: '100%',
                placeholder: 'Pilih Ranking',
                minimumResultsForSearch: Infinity,
            });
            $('.select-usia').select2({
                dropdownParent: $('#modalForm'),
                width: '100%',
                placeholder: 'Pilih Usia',
                minimumResultsForSearch: Infinity,
            });
            $('.select-marketing').select2({
                dropdownParent: $('#modalForm'),
                width: '100%',
                placeholder: 'Pilih Marketing',
                minimumResultsForSearch: 0,

                ajax: {
                    url: "{{ route('getMarketing') }}",
                    dataType: 'json',
                    delay: 250,
                    processResults: function(response) {
                        return {
                            results: $.map(response.data, function(item) {
                                return {
                                    id: item.id,
                                    text: item.nama_marketing
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            if ($('body').hasClass('dark')) {
                $('.select2-container').addClass('select2-dark');
            }
        });

        var permissions = @json($permissions);
        var showActionColumn = (permissions['edit'] == 1 || permissions['hapus'] == 1);
        var audio = new Audio('{{ asset('audio/notification.ogg') }}');

        $(function() {
            var table = $('.data-table').DataTable({
                processing: false,
                serverSide: true,
                ordering: false,
                responsive: true,
                ajax: "{{ route('prospek.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'nama_lengkap',
                        name: 'nama_lengkap'
                    },
                    {
                        data: 'no_wa',
                        name: 'no_wa'
                    },
                    {
                        data: 'pekerjaan',
                        name: 'pekerjaan'
                    },
                    {
                        data: 'rangking',
                        name: 'rangking',
                        className: 'text-center'
                    },
                    {
                        data: 'keterangan_belum',
                        name: 'keterangan_belum'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        visible: showActionColumn,
                        className: 'text-center'
                    }
                ],
                columnDefs: [{
                    targets: 0,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                }]
            });
        });

        function reloadTable() {
            table.ajax.reload(null, false);
        }

        // Tombol edit
        $(document).on('click', '.edit-button', function() {
            var url = $(this).data('url');
            $.get(url, function(response) {
                if (response.success) {
                    $('#primary_id').val(response.data.id);

                    $('#tgl_terima').val(response.data.tgl_terima);
                    $('#nama_lengkap').val(response.data.nama_lengkap);
                    $('#no_wa').val(response.data.no_wa);
                    $('#pekerjaan').val(response.data.pekerjaan);
                    $('#penghasilan').val(Number(response.data.penghasilan).toLocaleString('id-ID'));
                    $('#sumber_informasi').val(response.data.sumber_informasi);

                    $('#usia').val(response.data.usia).trigger('change');
                    $('#rangking').val(response.data.rangking).trigger('change');

                    $('#id_marketing').val(response.data.id_marketing);

                    var marketingId = response.data.id_marketing;
                    var marketingName = response.data.marketing?.nama_marketing || '';

                    if (!$('.select-marketing option[value="' + marketingId + '"]').length) {
                        var newOption = new Option(marketingName, marketingId, true, true);
                        $('.select-marketing').append(newOption).trigger('change');
                    }

                    $('.select-marketing').val(marketingId).trigger('change');

                    $('.select-marketing').val(marketingId).trigger('change');

                    $('#keterangan_belum').val(response.data.keterangan_belum);

                    $('#modalForm').modal('show');
                }
            });
        });

        $('#modalForm').on('hidden.bs.modal', function() {
            $('#formData')[0].reset();
            $('#primary_id').val('');
            $('#tgl_terima').val('');
            $('#nama_lengkap').val('');
            $('#no_wa').val('');
            $('#usia').val('').trigger('change');
            $('#pekerjaan').val('');
            $('#penghasilan').val('');
            $('#sumber_informasi').val('');
            $('#rangking').val('').trigger('change');
            $('#id_marketing').val('').trigger('change');
            $('#keterangan_belum').val('');

            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();
            let submitBtn = $('#submitBtn');
            let spinner = submitBtn.find('.spinner-border');
            let btnText = submitBtn.find('.button-text');

            spinner.addClass('d-none');
            btnText.text('Simpan');
            submitBtn.prop('disabled', false);
        });


        $('#formData').on('submit', function(e) {
            e.preventDefault();

            let submitBtn = $('#submitBtn');
            let spinner = submitBtn.find('.spinner-border');
            let btnText = submitBtn.find('.button-text');

            spinner.removeClass('d-none');
            btnText.text('Menyimpan...');
            submitBtn.prop('disabled', true);

            let id = $('#primary_id').val();
            let url = id ? '{{ route('prospek.update', ['prospek' => ':id']) }}'.replace(':id',
                    id) :
                '{{ route('prospek.store') }}';
            let method = id ? 'PUT' : 'POST';

            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            let formData = new FormData(this);
            formData.append('_method', method);

            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(res) {
                    if (res.success) {
                        $('#modalForm').modal('hide');
                        audio.play();
                        let msg = id ? "Pengguna berhasil diupdate!" : "Pengguna berhasil ditambahkan!";
                        toastr.success(msg, "BERHASIL", {
                            progressBar: true,
                            timeOut: 3500,
                            positionClass: "toast-bottom-right",
                        });
                        $('.data-table').DataTable().ajax.reload();
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
                    btnText.text('Simpan');
                    submitBtn.prop('disabled', false);
                }
            });
        });

        // Hapus data
        $(document).on('click', '.delete-button', function(e) {
            e.preventDefault();

            const form = $(this).closest('form');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Data ini akan dihapus secara permanen!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<span class="swal-btn-text">Ya, Hapus</span>',
                cancelButtonText: 'Batal',
                showLoaderOnConfirm: false,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger mx-2',
                    cancelButton: 'btn btn-secondary'
                },
                preConfirm: () => {
                    return new Promise((resolve) => {
                        const confirmBtn = Swal.getConfirmButton();
                        const btnText = confirmBtn.querySelector('.swal-btn-text');

                        btnText.innerHTML =
                            `<span class="spinner-border spinner-border-sm mx-2" role="status" aria-hidden="true"></span> Menghapus...`;
                        confirmBtn.disabled = true;

                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            success: function(res) {
                                if (res.success) {
                                    audio.play();
                                    toastr.success("Data berhasil dihapus!",
                                        "BERHASIL", {
                                            progressBar: true,
                                            timeOut: 3500,
                                            positionClass: "toast-bottom-right"
                                        });

                                    $('.data-table').DataTable().ajax.reload(null,
                                        false);
                                    Swal.close();
                                }
                            },
                            error: function() {
                                audio.play();
                                toastr.error("Gagal menghapus Pengguna.",
                                    "GAGAL!", {
                                        progressBar: true,
                                        timeOut: 3500,
                                        positionClass: "toast-bottom-right"
                                    });

                                btnText.innerHTML = `Ya, Hapus`;
                                confirmBtn.disabled = false;
                            }
                        });
                    });
                }
            });
        });
    </script>
@endpush
