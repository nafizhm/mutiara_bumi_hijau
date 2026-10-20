@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-content-center justify-content-between">
                        <h3 class="font-weight-bold text-xl">Data Kavling</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @if ($permissions['edit'])
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importExcelModal">Upload Excel</button>
                            @endif
                            <a href="{{ route('kavling.cetakPdf', 0) }}" target="_blank"
                                class="btn btn-danger btn-rounded btn-sm ms-2" title="Cetak PDF">
                                <i class="bi bi-file-earmark-pdf"></i> Cetak PDF
                            </a>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#exportExcelModal"
                                class="btn btn-success btn-rounded btn-sm ms-2" title="Cetak Excel">
                                <i class="bi bi-file-earmark-excel"></i> Cetak Excel
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label">Lokasi Kavling</label>
                        <div class="col-sm-4">
                            <select class="form-select select-lokasi" id="id_lokasi" name="id_lokasi">
                                <option value=""></option>
                                @foreach ($lokasiList as $lokasi)
                                    <option value="{{ $lokasi->id }}">{{ $lokasi->nama_kavling }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <table class="table data-table small table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="50px">No</th>
                                <th>Lokasi Kavling</th>
                                <th>Kode Kavling</th>
                                <th>Panjang</th>
                                <th>Lebar</th>
                                <th>Luas</th>
                                <th>Harga</th>
                                <th>Keterangan</th>
                                <th width="150px" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    <div class="modal fade" id="exportExcelModal" tabindex="-1" aria-labelledby="exportExcelTitle" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form id="exportExcelForm">
                <div class="modal-header"><h5 id="exportExcelTitle" class="modal-title">Download Excel Kavling</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <label for="exportLocation" class="form-label">Lokasi Kavling</label>
                    <select id="exportLocation" class="form-select" required>
                        <option value="">Pilih lokasi yang akan didownload</option>
                        @foreach ($lokasiList as $lokasi)<option value="{{ $lokasi->id }}">{{ $lokasi->nama_kavling }}</option>@endforeach
                    </select>
                    <p class="small text-muted mt-2 mb-0">File hanya berisi data dari satu lokasi yang dipilih.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Download Excel</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelTitle" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form id="importExcelForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h5 id="importExcelTitle" class="modal-title">Upload Excel Kavling</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Gunakan file Cetak Excel terbaru. File harus hanya berisi kavling dari lokasi yang dipilih. Kode kavling dan header tidak boleh diubah. Upload memperbarui data master, bukan harga transaksi penjualan yang sudah tercatat.</p>
                    <div id="importExcelError" class="alert alert-danger d-none"></div>
                    <label for="importLocation" class="form-label">Lokasi Kavling</label>
                    <select id="importLocation" name="id_lokasi" class="form-select mb-3" required>
                        <option value="">Pilih lokasi tujuan</option>
                        @foreach ($lokasiList as $lokasi)<option value="{{ $lokasi->id }}">{{ $lokasi->nama_kavling }}</option>@endforeach
                    </select>
                    <label for="importFile" class="form-label">File Excel (.xlsx, maksimal 5 MB)</label>
                    <input id="importFile" type="file" name="file" class="form-control" accept=".xlsx" required>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Upload dan Perbarui</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade" id="editKavlingModal" tabindex="-1" aria-labelledby="editKavlingTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content" id="editKavlingContent"></div></div>
    </div>
@endsection
@push('scripts')
    <script>
        $('#importExcelForm').on('submit', function (event) {
            event.preventDefault();
            const button = $(this).find('button[type="submit"]');
            button.prop('disabled', true);
            $('#importExcelError').addClass('d-none').text('');
            $.ajax({url: @json(route('kavling.importExcel')), method: 'POST', data: new FormData(this), contentType: false, processData: false, headers: {'Accept': 'application/json'}})
                .done(function (response) {
                    $('#importExcelModal').modal('hide');
                    toastr.success(response.message);
                    $('.data-table').DataTable().ajax.reload(null, false);
                }).fail(function (xhr) {
                    $('#importExcelError').removeClass('d-none').text(xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : (xhr.responseJSON?.message || 'Upload gagal. Periksa format file.'));
                }).always(function () { button.prop('disabled', false); });
        });
        $('#exportExcelModal').on('show.bs.modal', function () {
            $('#exportLocation').val($('#id_lokasi').val() || '');
        });
        $('#exportExcelForm').on('submit', function (event) {
            event.preventDefault();
            const location = $('#exportLocation').val();
            if (!location) return;
            window.location.assign(@json(route('kavling.cetakExcel', ['id_lokasi' => '__id__'])).replace('__id__', encodeURIComponent(location)));
            $('#exportExcelModal').modal('hide');
        });
        $('#id_lokasi').on('change', function () {
            const location = this.value || 0;
            $('a[title="Cetak PDF"]').attr('href', @json(route('kavling.cetakPdf', ['id_lokasi' => '__id__'])).replace('__id__', location));
            $('#importLocation').val(this.value);
        });
        var permissions = @json($permissions);
        var showActionColumn = (permissions['edit'] == 1);

        $(document).ready(function() {
            $('.select-lokasi').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Lokasi Kavling',
                allowClear: true,
            });
            $('.select-supplier').select2({
                dropdownParent: $('#modalForm'),
                width: '100%',
                placeholder: 'Pilih Supplier',
                allowClear: true,
            });

            if ($('body').hasClass('dark')) {
                $('.select2-container').addClass('select2-dark');
            }
        });

        $(function() {
            var table = $('.data-table').DataTable({
                processing: false,
                serverSide: true,
                ordering: false,
                responsive: true,
                ajax: {
                    url: "{{ route('kavling.index') }}",
                    data: function(d) {
                        d.id_lokasi = $('#id_lokasi').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'lokasi.nama_kavling',
                        name: 'lokasi.nama_kavling',
                        orderable: false,
                        searchable: true
                    },
                    {
                        data: 'kode_kavling',
                        name: 'kode_kavling',
                        orderable: false,
                        searchable: true,
                    },
                    {
                        data: 'panjang',
                        name: 'panjang',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'lebar',
                        name: 'lebar',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'luas',
                        name: 'luas',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'harga',
                        name: 'harga',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'keterangan',
                        name: 'keterangan',
                        orderable: false,
                        searchable: false
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

            const editModalElement = document.getElementById('editKavlingModal');
            const editModal = bootstrap.Modal.getOrCreateInstance(editModalElement);
            function openKavling(url) {
                $('#editKavlingContent').html('<div class="modal-body">Memuat data...</div>');
                editModal.show();
                $.ajax({url: url, headers: {Accept: 'text/html'}})
                    .done(function(html) { $('#editKavlingContent').html(html); })
                    .fail(function() {
                        editModal.hide();
                        Swal.fire('Gagal', 'Data kavling tidak dapat dimuat. Silakan muat ulang halaman.', 'error');
                    });
            }
            $(document).on('click', '.data-table a.edit', function(event) {
                event.preventDefault();
                openKavling(this.href);
            });
            $(document).on('submit', '#formData', function(event) {
                event.preventDefault();
                const form = $(this);
                const button = form.find('[type="submit"]');
                if (button.prop('disabled')) return;
                button.prop('disabled', true);
                button.find('.spinner-border').removeClass('d-none');
                button.find('.button-text').text('Menyimpan...');
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').remove();
                const errorBox = form.find('#editKavlingError').addClass('d-none').empty();
                const payload = new FormData(this);
                payload.append('_method', 'PUT');
                $.ajax({
                    url: this.action, method: 'POST', data: payload,
                    contentType: false, processData: false, dataType: 'json',
                    headers: {Accept: 'application/json'},
                    success: function(response) {
                        if (!response.success) {
                            errorBox.text(response.message || 'Data gagal disimpan.').removeClass('d-none');
                            return;
                        }
                        editModal.hide();
                        table.ajax.reload(null, false);
                        Swal.fire('Berhasil', response.message, 'success');
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON || {};
                        errorBox.text(xhr.status === 422 ? 'Periksa kembali isian berikut.' :
                            (response.message || 'Data gagal disimpan. Silakan muat ulang halaman dan coba kembali.')).removeClass('d-none');
                        $.each(response.errors || {}, function(name, messages) {
                            const input = form.find('[name="' + name + '"]');
                            input.addClass('is-invalid');
                            $('<div class="invalid-feedback"></div>').text(messages[0]).insertAfter(input);
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        button.find('.spinner-border').addClass('d-none');
                        button.find('.button-text').text('Simpan');
                    }
                });
            });
            const initialEdit = @json(request('edit'));
            if (initialEdit && /^\d+$/.test(initialEdit)) {
                openKavling(@json(route('kavling.edit', ['kavling' => '__id__'])).replace('__id__', initialEdit));
            }

            $('#id_lokasi').on('change', function() {
                table.ajax.reload();
            });
        });
    </script>
@endpush
