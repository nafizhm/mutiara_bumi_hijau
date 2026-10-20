@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-content-center justify-content-between">
                        <h3 class="font-weight-bold text-xl">Data Arsip Customer</h3>
                        <div class="d-flex align-items-center">
                            <a href="javascript:void(0);" onclick="reloadTable()" class="btn btn-light btn-rounded btn-sm ms-2"
                                title="Reload Tabel">
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
                                    <th>Tanggal Cancel</th>
                                    <th>Nama Nasabah</th>
                                    <th>Marketing</th>
                                    <th>Perumahan</th>
                                    <th>Status Progres</th>
                                    <th>Alasan Cancel</th>
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
@endsection
@push('scripts')
    <script>
        var permissions = @json($permissions);
        var showActionColumn = (permissions['hapus'] == 1);

        $(function() {
            var table = $('.data-table').DataTable({
                processing: false,
                serverSide: true,
                ordering: false,
                responsive: true,
                autoWidth: false,
                ajax: "{{ route('arsip-customer.index') }}",
                columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }, {
                    data: 'tgl_cancel',
                    name: 'tgl_cancel',
                    searchable: false
                }, {
                    data: 'nama_lengkap',
                    name: 'nama_lengkap',
                    searchable: true
                }, {
                    data: 'id_marketing',
                    name: 'id_marketing',
                    searchable: false
                }, {
                    data: 'id_lokasi',
                    name: 'id_lokasi',
                    searchable: false
                }, {
                    data: 'id_status_progres',
                    name: 'id_status_progres',
                    className: 'text-center',
                    searchable: false
                }, {
                    data: 'alasan_cancel',
                    name: 'alasan_cancel',
                    searchable: true
                }, {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false,
                    visible: showActionColumn
                }],
                columnDefs: [{
                    targets: 0,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                }]
            });
        });

        $(document).on('click', '.restore-button', function(e) {
            e.preventDefault();

            const id = $(this).data('id');

            Swal.fire({
                title: 'Kembalikan data?',
                text: 'Data customer akan dikembalikan ke daftar aktif',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<span class="swal-btn-text">Ya, Kembalikan</span>',
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success mx-2',
                    cancelButton: 'btn btn-secondary'
                },
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const confirmBtn = Swal.getConfirmButton();
                    const btnText = confirmBtn.querySelector('.swal-btn-text');
                    btnText.innerHTML = `<span class="spinner-border spinner-border-sm mx-2"></span> Memproses...`;
                    confirmBtn.disabled = true;

                    return $.ajax({
                        url: '{{ route("customer.restore", ":id") }}'.replace(':id', id),
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        }
                    }).then(response => {
                        if (!response.success) {
                            throw new Error(response.message);
                        }
                        return response;
                    }).catch(error => {
                        let msg = 'Terjadi kesalahan';
                        if (error.responseJSON && error.responseJSON.message) {
                            msg = error.responseJSON.message;
                        }
                        Swal.showValidationMessage(msg);
                        btnText.innerHTML = `Ya, Kembalikan`;
                        confirmBtn.disabled = false;
                        throw error;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    audio.play();
                    toastr.success("Data berhasil dikembalikan!", "BERHASIL", {
                        progressBar: true,
                        timeOut: 3500,
                        positionClass: "toast-bottom-right"
                    });
                    $('.data-table').DataTable().ajax.reload(null, false);
                }
            });
        });
    </script>
@endpush
