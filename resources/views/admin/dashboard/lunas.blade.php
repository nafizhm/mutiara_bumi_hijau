@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="font-weight-bold text-xl">Data Lunas</h3>
                        <div class="d-flex align-items-center">
                            <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm d-flex align-items-center">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali
                            </a>
                            <a href="javascript:void(0);" onclick="reloadTable()" class="btn btn-light btn-sm ms-2">
                                <i class="bi bi-arrow-clockwise"></i> Reload
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="data-table" class="table table-bordered table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama Customer</th>
                                    <th>Perumahan</th>
                                    <th>Kode Kavling</th>
                                    <th>Harga Jual</th>
                                    <th>Tanggal Lunas</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            window.table = $('#data-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('lunas.unit') }}",
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
                        data: 'perumahan',
                        name: 'perumahan'
                    },
                    {
                        data: 'kode_kavling',
                        name: 'kode_kavling'
                    },
                    {
                        data: 'harga_jual',
                        name: 'harga_jual'
                    },
                    {
                        data: 'tanggal_lunas',
                        name: 'tanggal_lunas'
                    }
                ]
            });
        });

        function reloadTable() {
            table.ajax.reload(null, false);
        }
    </script>
@endpush
