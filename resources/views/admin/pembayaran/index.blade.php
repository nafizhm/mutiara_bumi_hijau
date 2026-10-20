@extends('admin.layout')
@section('content')
    <style>
        .payment-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; }
        .payment-filters { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
        .payment-filter { min-width:160px; flex:1; }
        .payment-table td { vertical-align:middle; padding:1rem .75rem; }
        .payment-table th { font-size:.75rem; color:#607080; white-space:nowrap; }
        .payment-money { font-weight:600; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .payment-customer { min-width:150px; }
        @media(max-width:575.98px) {
            .payment-filters { width:100%; }
            .payment-filter { min-width:120px; }
            .payment-filters > a { width:100%; }
            .payment-table td { padding:.8rem .4rem; }
            .dataTables_filter, .dataTables_length { text-align:left !important; margin-bottom:.75rem; }
        }
    </style>
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="payment-toolbar">
                        <div><h3 class="font-weight-bold text-xl mb-1">Pembayaran</h3><p class="text-muted small mb-0">Pantau pembayaran dan sisa tagihan pelanggan.</p></div>
                        <div class="payment-filters">
                            <div class="payment-filter">
                                <label for="status" class="visually-hidden">Status pembayaran</label>
                                <select class="form-control select-1" name="status" id="status">
                                    <option value="">Semua Status</option>
                                    <option value="Lunas">Lunas</option>
                                    <option value="Terhutang">Terhutang</option>
                                </select>
                            </div>

                            <div class="payment-filter">
                                <label for="progres" class="visually-hidden">Tahapan pembelian</label>
                                <select class="form-control select-2" name="progres" id="progres">
                                    <option value="">Semua Progres</option>
                                    @foreach ($progreslists as $list)
                                        <option value="{{ $list->id }}">{{ $list->status_progres }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table data-table payment-table small table-hover w-100">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Customer</th>
                                <th>Lokasi Kavling</th>
                                <th>Kavling Dibeli</th>
                                <th>Status</th>
                                <th class="text-end">Sudah Dibayar</th>
                                <th class="text-end">Sisa Tagihan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select-1').select2({
                dropdownParent: $('body'),
                width: '100%',
                minimumResultsForSearch: Infinity,
            });
            $('.select-2').select2({
                dropdownParent: $('body'),
                width: '100%',
            });

            if ($('body').hasClass('dark')) {
                $('.select2-container').addClass('select2-dark');
            }
        });

        $(function() {
            var permissions = @json($permissions);
            var showActionColumn = (permissions['edit'] == 1 || permissions['hapus'] == 1);

            var table = $('.data-table').DataTable({
                processing: false,
                serverSide: true,
                responsive: true,
                ordering: false,
                ajax: {
                    url: "{{ route('pembayaran.index') }}",
                    data: function(d) {
                        d.status = $('#status').val();
                        d.progres = $('#progres').val();
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
                        data: 'customer',
                        name: 'customer',
                        responsivePriority: 1
                    },
                    {
                        data: 'lokasi_kavling',
                        name: 'lokasi_kavling'
                    },
                    {
                        data: 'kavling_dibeli',
                        name: 'kavling_dibeli'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total_pembayaran',
                        name: 'total_pembayaran',
                        orderable: false,
                        searchable: false,
                        className: 'text-end'
                    },
                    {
                        data: 'sisa_pembayaran',
                        name: 'sisa_pembayaran',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        responsivePriority: 2
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        visible: showActionColumn,
                        className: 'text-center',
                        responsivePriority: 3
                    }
                ],
                columnDefs: [{
                    targets: 0,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                }]
            });

            $('#status, #progres').change(function() {
                table.ajax.reload();
            });
        });
    </script>
@endpush
