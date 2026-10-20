@extends('admin.layout')
@section('content')
    <style>
        .receivable-table td { vertical-align:middle; padding:1rem .75rem; }
        .receivable-table th { font-size:.8rem; white-space:nowrap; }
        .receivable-table .receivable-money { white-space:nowrap; font-variant-numeric:tabular-nums; }
        @media(max-width:575.98px) {
            .receivable-header > div { width:100%; }
            .receivable-header button, .receivable-header a { flex:1; }
            .receivable-table td { padding:.75rem .4rem; }
        }
    </style>
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 receivable-header">
                        <div><h3 class="mb-1">Piutang</h3><p class="small text-muted mb-0">Pantau tagihan, pembayaran masuk, dan sisa piutang.</p></div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('piutang.rekap') }}" class="btn btn-outline-secondary btn-sm">Rekap Piutang</a>
                            @if ($canPayPiutang)<button type="button" class="btn btn-success btn-sm piutang-open-pay">Bayar Piutang</button>@endif
                            @if ($permissions['tambah'])
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalForm"><i class="bi bi-plus-lg"></i> Tambah Piutang</button>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-4"><label class="form-label small" for="filter_tanggal">Tanggal piutang</label><input type="date" class="form-control" id="filter_tanggal"></div>
                        <div class="col-6 col-sm-4"><label class="form-label small" for="filter_status">Status</label><select id="filter_status" class="form-select"><option value="">Semua status</option><option value="belum">Belum lunas</option><option value="lunas">Lunas</option></select></div>
                        <div class="col-6 col-sm-4"><label class="form-label small" for="filter_sumber">Sumber piutang</label><select id="filter_sumber" class="form-select"><option value="">Semua sumber</option><option value="penjualan">Penjualan kavling</option><option value="lainnya">Piutang lainnya</option></select></div>
                    </div>
                    <table class="table table-hover data-table receivable-table small w-100">
                        <thead><tr><th>No</th><th>Tanggal</th><th>Informasi Piutang</th><th>Total Piutang</th><th>Sudah Dibayar</th><th>Sisa Tagihan</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    {{-- MODAL FORM (TAMBAH / EDIT) --}}
    <div class="modal fade text-left" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">

        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title white" id="modalFormLabel">Form Data Piutang</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form id="formData" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="primary_id" name="primary_id">
                    <div class="modal-body">
                        <div id="salesEditInfo" class="alert alert-info d-none">Ubah nominal total piutang sesuai harga yang disepakati. Sisa tagihan dihitung kembali dari pembayaran yang sudah tercatat. Perubahan ini berlaku pada tagihan piutang ini.</div>
                        <div class="form-group row">
                            <label for="tanggal_piutang" class="col-sm-3 col-form-label">Tanggal Piutang</label>
                            <div class="col-sm-5">
                                <input type="date" class="form-control" id="tanggal_piutang" name="tanggal_piutang"
                                    value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="id_bank" class="col-sm-3 col-form-label">Rekening</label>
                            <div class="col-sm-3">
                                <select class="form-select" name="id_bank" id="id_bank">
                                    <option value=""></option>
                                    @foreach ($bankList as $item)
                                        <option value="{{ $item->id }}">{{ $item->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label for="nominal" class="col-sm-2 col-form-label">Nominal</label>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp.</span>
                                    </div>
                                    <input name="nominal" id="nominal" class="form-control format-number" type="text">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Lampiran</label>
                            <div class="col-sm-8">
                                <input type="file" name="lampiran" id="lampiran"
                                    accept=".png, .jpg, .jpeg, .webp, .pdf">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label"></label>
                            <div class="col-sm-8">
                                <div class="img-thumbnail mb-2 d-flex align-items-center justify-content-center"
                                    id="previewFoto"
                                    style="max-width: 140px; height: 150px; background-color: #f8f9fa; border: 1px solid #dee2e6; overflow: hidden;">
                                    <span style="color: #6c757d;">Tidak ada File</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="deskripsi" class="col-sm-3 col-form-label">Deskripsi</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                            </div>
                        </div>

                        <div id="info-pembayaran" style="display: none;">
                            <div class="form-group row">
                                <label for="sisa_bayar" class="col-sm-3 col-form-label">Sisa Bayar</label>
                                <div class="col-sm-5">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp.</span>
                                        </div>
                                        <input type="text" class="form-control" id="sisa_bayar" name="sisa_bayar"
                                            disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row mt-3">
                                <label for="terbayar" class="col-sm-3 col-form-label">Terbayar</label>
                                <div class="col-sm-5">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp.</span>
                                        </div>
                                        <input type="text" class="form-control" id="terbayar" name="terbayar"
                                            disabled>
                                    </div>
                                </div>
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

    @include('admin.keuangan.piutang.payment-modal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Inisialisasi Select2 untuk Modal Form
            $('#id_bank').select2({
                dropdownParent: $('#modalForm'),
                width: '100%',
                placeholder: 'Pilih Rekening',
                minimumResultsForSearch: Infinity,
            });

            if ($('body').hasClass('dark')) {
                $('.select2-container').addClass('select2-dark');
            }
        });

        $('#lampiran').on('change', function() {
            const file = this.files[0];
            const previewDiv = $('#previewFoto');

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.html(
                        `<img src="${e.target.result}" style="max-width: 100%; max-height: 100%;">`);
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.html('<span style="color: #6c757d;">Tidak ada File</span>');
            }
        });

        var audio = new Audio('{{ asset('audio/notification.ogg') }}');

        $(function() {
            var table = $('.data-table').DataTable({
                processing: false,
                serverSide: true,
                ordering: false,
                responsive: true,
                ajax: { url: "{{ route('piutang.index') }}", data: function(d) {
                    d.tanggal = $('#filter_tanggal').val(); d.status = $('#filter_status').val(); d.sumber = $('#filter_sumber').val();
                } },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'tanggal_piutang',
                        name: 'tanggal_piutang',
                        orderable: false,
                        searchable: true
                    },
                    {
                        data: 'informasi',
                        name: 'informasi', responsivePriority: 1,
                        orderable: false,
                        searchable: true
                    },
                    {
                        data: 'nominal',
                        name: 'nominal',
                        className: 'text-end receivable-money',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'terbayar', name: 'terbayar', orderable: false, searchable: false, className: 'text-end receivable-money'
                    },
                    {
                        data: 'sisa_bayar', name: 'sisa_bayar', orderable: false, searchable: false, className: 'text-end receivable-money', responsivePriority: 2
                    },
                    {
                        data: 'status', name: 'status', orderable: false, searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        responsivePriority: 3,
                        className: 'text-center'
                    },
                ],
                columnDefs: [{
                    targets: 0,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                }]
            });
            $('#filter_tanggal, #filter_status, #filter_sumber').on('change', function() { table.ajax.reload(); });
        });
        $(document).on('click', '.edit-button', function() {
            var url = $(this).data('url');
            $.get(url, function(response) {
                if (response.status === 'success') {
                    const sales = Boolean(Number(response.data.id_customer));
                    $('#salesEditInfo').toggleClass('d-none', !sales);
                    $('#tanggal_piutang').prop('readonly', sales);
                    $('#id_bank, #lampiran').prop('disabled', sales);
                    $('#primary_id').val(response.data.id);
                    $('#tanggal_piutang').val(response.data.tanggal_piutang);
                    $('#deskripsi').val(response.data.deskripsi);

                    let nominal = parseFloat(response.data.nominal);
                    if (!isNaN(nominal)) {
                        $('#nominal').val(nominal.toLocaleString('id-ID'));
                    } else {
                        $('#nominal').val('');
                    }

                    let lampiran = response.data.lampiran;
                    let preview = $('#previewFoto');
                    if (lampiran) {
                        let imageUrl = '/assets/keuangan/pengeluaran/' + lampiran;
                        preview.html(
                            `<img src="${imageUrl}" alt="File" style="max-height: 100%; max-width: 100%;">`
                        );
                    } else {
                        preview.html(`<span style="color: #6c757d;">Tidak ada File</span>`);
                    }

                    const terbayar = parseFloat(response.data.terbayar) || 0;
                    const sisaBayar = parseFloat(response.data.sisa_bayar) || 0;

                    $('#terbayar').val(terbayar.toLocaleString('id-ID'));
                    $('#sisa_bayar').val(sisaBayar.toLocaleString('id-ID'));
                    $('#id_bank').val(response.data.id_bank).trigger('change');

                    $('#info-pembayaran').show();

                    $('#modalForm').modal('show');
                }
            });
        });

        $('#modalForm').on('hidden.bs.modal', function() {
            $('#formData')[0].reset();
            $('#salesEditInfo').addClass('d-none');
            $('#tanggal_piutang').prop('readonly', false);
            $('#id_bank, #lampiran').prop('disabled', false);
            $('#primary_id').val('');
            $('#id_bank').val('').trigger('change');
            $('#info-pembayaran').hide();
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            let submitBtn = $('#submitBtn');
            let spinner = submitBtn.find('.spinner-border');
            let btnText = submitBtn.find('.button-text');

            spinner.addClass('d-none');
            btnText.text('Simpan');
            submitBtn.prop('disabled', false);

            $('#previewFoto').html(`<span style="color: #6c757d;">Tidak ada File</span>`);
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
            let url = id ? '{{ route('piutang.update', ['piutang' => ':id']) }}'.replace(':id', id) :
                '{{ route('piutang.store') }}';
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
                dataType: 'json',
                headers: {Accept: 'application/json'},
                success: function() {
                    $('#modalForm').modal('hide');
                    audio.play();
                    let msg = id ? "Piutang berhasil diupdate!" : "Piutang berhasil ditambahkan!";
                    toastr.success(msg, "BERHASIL", {
                        progressBar: true,
                        timeOut: 3500,
                        positionClass: "toast-bottom-right",
                    });
                    $('.data-table').DataTable().ajax.reload();
                },
                error: function(xhr) {
                    const response = xhr.responseJSON || {};
                    const errors = response.errors || {};
                    let message = Object.values(errors).flat().join(' ');
                    if (!message) message = xhr.status === 403 ? 'Anda tidak memiliki izin Edit Piutang.' :
                        (xhr.status === 422 ? response.message : 'Gagal menyimpan piutang. Muat ulang halaman lalu coba kembali.');
                    toastr.error(message, 'GAGAL!');
                    $.each(errors, function(key, values) {
                        const input = $('#formData').find('[name="' + key + '"]');
                        input.addClass('is-invalid');
                        $('<div class="invalid-feedback d-block"></div>').text(values[0]).appendTo(input.parent());
                    });
                },
                complete: function() {
                    spinner.addClass('d-none');
                    btnText.text('Simpan');
                    submitBtn.prop('disabled', false);
                }
            });
        });

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
                            `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Menghapus...`;
                        confirmBtn.disabled = true;

                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            success: function() {
                                audio.play();
                                toastr.success("Data telah dihapus!", "BERHASIL", {
                                    progressBar: true,
                                    timeOut: 3500,
                                    positionClass: "toast-bottom-right"
                                });
                                $('.data-table').DataTable().ajax.reload(null,
                                    false);
                                Swal.close();
                            },
                            error: function() {
                                audio.play();
                                toastr.error("Gagal menghapus data.", "GAGAL!", {
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

        function formatRupiah(input) {
            let value = input.value.replace(/\D/g, '');
            if (!value) {
                input.value = '';
                return;
            }
            input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    </script>
@endpush
