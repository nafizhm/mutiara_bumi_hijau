@extends('admin.layout')
@section('content')
    <style>
        .legend {
            position: fixed;
            top: 80px;
            right: 40px;
            padding: 15px;
            font-size: 14px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 220px;
            z-index: 1000;
            transform: translateX(0);
            opacity: 1;
            transition: all 0.3s ease;
        }

        .legend.hidden {
            transform: translateX(100%);
            opacity: 0;
            pointer-events: none;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .legend-color {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            border-radius: 50%;
            border: 2px solid #333;
            flex-shrink: 0;
        }

        .show-btn {
            position: fixed;
            top: 100px;
            right: 70px;
            padding: 8px 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            font-size: 14px;
            z-index: 1001;
            transform: translateX(300px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .show-btn.visible {
            transform: translateX(0);
            opacity: 1;
        }

        .show-btn:hover {
            transform: translateX(0) scale(1.05);
        }

        .toggle-btn {
            width: 100%;
            padding: 8px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.2s ease;
        }

        .toggle-btn:hover {
            background-color: #5a6268;
        }

        .svg-container {
            width: 100%;
            height: 100vh;
            overflow: hidden;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            position: relative;
            background-color: #f8f9fa;
        }

        .svg-container svg {
            width: 100%;
            height: 100%;
            cursor: grab;
            transition: transform 0.1s ease-out;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tab-content {
            padding: 20px 0;
        }

        #modalDetail .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }

        .demo-siteplan {
            height: 400px;
            background: linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
                linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 18px;
            border-radius: 8px;
        }
    </style>

    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Lokasi Kavling</h5>
                </div>
                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="lokasiTab" role="tablist">
                        @foreach ($lokasiKavling as $index => $kav)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $index == 0 ? 'active' : '' }}" id="lokasi-{{ $kav->id }}-tab"
                                    data-bs-toggle="tab" href="#lokasi-{{ $kav->id }}" role="tab"
                                    aria-controls="lokasi-{{ $kav->id }}"
                                    aria-selected="{{ $index == 0 ? 'true' : 'false' }}"
                                    onclick="loadSiteplanData({{ $kav->id }})">
                                    {{ $kav->nama_kavling }}
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="lokasiTabContent">
                        @foreach ($lokasiKavling as $index => $kav)
                            <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}"
                                id="lokasi-{{ $kav->id }}" role="tabpanel"
                                aria-labelledby="lokasi-{{ $kav->id }}-tab" data-id-lokasi="{{ $kav->id }}">

                                @if ($index == 0)
                                    <div class="siteplan-content"></div>
                                @else
                                    <div class="siteplan-content">
                                        <div class="text-center py-4">
                                            <p>Click to load siteplan data</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <button class="show-btn btn btn-primary" id="show-btn" onclick="toggleLegend()">
                <i class="fas fa-info-circle me-1"></i> Show Legend
            </button>

            <div class="legend" id="legend">
                <h6 class="mb-3 fw-bold text-primary">Status Progress</h6>

                @foreach ($legend as $item)
                    <div class="legend-item">
                        <div class="legend-color me-2" style="background-color: {{ $item->warna }};"></div>
                        <span class="text-black">{{ $item->status_progres }}</span>
                    </div>
                @endforeach

                <button class="toggle-btn btn btn-secondary mt-2" onclick="toggleLegend()">
                    <i class="fas fa-times me-1"></i> Hide Legend
                </button>
            </div>
        </section>
    </div>

    <div class="modal fade text-left" id="modalDetail" tabindex="-1" role="dialog" aria-labelledby="modalDetailLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" data-bs-focus="false">

        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title white" id="myModalLabel160">Detail Data Kavling
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form id="testPesanForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" id="id_lokasi" name="id_lokasi" value="">

                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-cetak">
                                <i class="fas fa-print"></i> Cetak Data
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-tabs mb-3" id="tabDetail" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link active" id="page-1-tab" data-bs-toggle="pill" href="#page-1"
                                            role="tab" aria-controls="page-1" aria-selected="true">
                                            Data Unit Tanah Kavling
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" id="page-2-tab" data-bs-toggle="pill" href="#page-2"
                                            role="tab" aria-controls="page-2" aria-selected="false">
                                            Data User
                                        </a>
                                    </li>
                                    {{-- <li class="nav-item" role="presentation">
                                        <a class="nav-link" id="page-3-tab" data-bs-toggle="pill" href="#page-3"
                                            role="tab" aria-controls="page-3" aria-selected="false">
                                            Tagihan & Pembayaran
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" id="page-4-tab" data-bs-toggle="pill" href="#page-4"
                                            role="tab" aria-controls="page-4" aria-selected="false">
                                            Foto Unit
                                        </a>
                                    </li> --}}
                                </ul>
                                <div class="tab-content" id="tabDetailContent">
                                    <div class="tab-pane fade show active" id="page-1">
                                        <!-- Lokasi Tanah Kavling -->

                                        <div class="row mb-3">
                                            <label for="lokasi_id" class="col-sm-3 col-form-label">Lokasi
                                                Kavling</label>
                                            <div class="col-sm-3">
                                                <input name="kode_kavling" id="lokasi_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="kode_kavling_id" class="col-sm-3 col-form-label">Lokasi
                                                Tanah Kavling</label>
                                            <div class="col-sm-3">
                                                <input name="kode_kavling" id="kode_kavling_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <!-- Panjang Tanah dan Lebar Tanah -->
                                        <div class="row mb-3">
                                            <label for="panjang_kanan_id" class="col-sm-3 col-form-label">Panjang
                                                Tanah</label>
                                            <div class="col-sm-3">
                                                <input name="panjang_kanan" id="panjang_kanan_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                            <label for="lebar_depan_id" class="col-sm-2 col-form-label">Lebar
                                                Tanah</label>
                                            <div class="col-sm-2">
                                                <input name="lebar_depan" id="lebar_depan_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <!-- Luas Tanah -->
                                        <div class="row mb-3">
                                            <label for="luas_tanah_id" class="col-sm-3 col-form-label">Luas Tanah</label>
                                            <div class="col-sm-3">
                                                <input name="luas_tanah" id="luas_tanah_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>


                                        <!-- Harga Jual -->
                                        <div class="row mb-3">
                                            <label for="harga_id" class="col-sm-3 col-form-label">Harga Jual</label>
                                            <div class="col-sm-3">
                                                <input name="harga" id="harga_id" class="form-control" type="text"
                                                    readonly>
                                            </div>
                                        </div>


                                        <!-- Keterangan -->
                                        <div class="row mb-3">
                                            <label for="keterangan_id" class="col-sm-3 col-form-label">Keterangan</label>
                                            <div class="col-sm-3">
                                                <input name="keterangan" id="keterangan_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <hr>

                                        <!-- ID Tanah Kavling -->
                                        <div class="row mb-3">
                                            <label for="id_rumah_sikumbang_id" class="col-sm-3 col-form-label">ID
                                                Tanah Kavling</label>
                                            <div class="col-sm-3">
                                                <input name="id_rumah_sikumbang" id="id_rumah_sikumbang_id"
                                                    class="form-control" type="text" readonly>
                                            </div>
                                        </div>


                                        <!-- No. Sertipikat -->
                                        <div class="row mb-3">
                                            <label for="no_sertifikat_id" class="col-sm-3 col-form-label">No.
                                                Sertipikat</label>
                                            <div class="col-sm-3">
                                                <input name="no_sertifikat" id="no_sertifikat_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="page-2">
                                        <!-- Nama Lengkap -->
                                        <div class="row mb-3">
                                            <label for="nama_lengkap_id" class="col-sm-3 col-form-label">Nama
                                                Lengkap</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="nama_lengkap_id"
                                                    name="nama_lengkap" readonly>
                                            </div>
                                        </div>

                                        <!-- No. KTP -->
                                        <div class="row mb-3">
                                            <label for="no_ktp_id" class="col-sm-3 col-form-label">No. KTP</label>
                                            <div class="col-sm-3">
                                                <input name="no_ktp" id="no_ktp_id" class="form-control" type="text"
                                                    readonly>
                                            </div>
                                            <label for="no_ktp_p_id" class="col-sm-2 col-form-label">No. KTP
                                                Pasangan</label>
                                            <div class="col-sm-3">
                                                <input name="no_ktp_p" id="no_ktp_p_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <!-- Tempat Lahir -->
                                        <div class="row mb-3">
                                            <label for="tempat_lahir_id" class="col-sm-3 col-form-label">Tempat
                                                Lahir</label>
                                            <div class="col-sm-3">
                                                <input name="tempat_lahir" id="tempat_lahir_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                            <label for="tgl_lahir_id" class="col-sm-2 col-form-label">Tanggal
                                                Lahir</label>
                                            <div class="col-sm-3">
                                                <input name="tgl_lahir" id="tgl_lahir_id" class="form-control"
                                                    type="date" readonly>
                                            </div>
                                        </div>

                                        <!-- Jenis Kelamin -->
                                        <div class="row mb-3">
                                            <label for="jenis_kelamin_id" class="col-sm-3 col-form-label">Jenis
                                                Kelamin</label>
                                            <div class="col-sm-3">
                                                <input type="text" class="form-control" id="jenis_kelamin_id"
                                                    name="jenis_kelamin" readonly>
                                            </div>
                                        </div>

                                        <!-- Alamat KTP -->
                                        <div class="row mb-3">
                                            <label for="alamat_id" class="col-sm-3 col-form-label">Alamat KTP</label>
                                            <div class="col-sm-8">
                                                <textarea name="alamat" id="alamat_id" class="form-control" rows="2" readonly></textarea>
                                            </div>
                                        </div>

                                        <!-- Alamat Domisili -->
                                        <div class="row mb-3">
                                            <label for="alamat_domisili_id" class="col-sm-3 col-form-label">Alamat
                                                Domisili</label>
                                            <div class="col-sm-8">
                                                <textarea name="alamat_domisili" id="alamat_domisili_id" class="form-control" rows="2" readonly></textarea>
                                            </div>
                                        </div>

                                        <!-- No. Telp / WA -->
                                        <div class="row mb-3">
                                            <label for="no_telp_id" class="col-sm-3 col-form-label">No. Telp / WA</label>
                                            <div class="col-sm-3">
                                                <input name="no_telp" id="no_telp_id" class="form-control"
                                                    type="text" readonly>
                                            </div>
                                        </div>

                                        <!-- NPWP -->
                                        <div class="row mb-3">
                                            <label for="npwp_id" class="col-sm-3 col-form-label">NPWP</label>
                                            <div class="col-sm-3">
                                                <input name="npwp" id="npwp_id" class="form-control" type="text"
                                                    readonly>
                                            </div>
                                        </div>

                                        <!-- Jenis Pembelian -->
                                        <div class="row mb-3">
                                            <label for="jenis_pembelian_id" class="col-sm-3 col-form-label">Jenis
                                                Pembelian</label>
                                            <div class="col-sm-3">
                                                <input name="jenis_pembelian" id="jenis_pembelian_id"
                                                    class="form-control" type="text" readonly>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="row mb-3">
                                            <label class="col-sm-3 col-form-label">Foto KTP</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoKtpLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto KTP
                                                </a>
                                                <span id="noFotoKtp" style="display: none;">-</span>
                                            </div>
                                            <label class="col-sm-3 col-form-label">Foto Pemohon</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoPemohonLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto Pemohon
                                                </a>
                                                <span id="noFotoPemohon" style="display: none;">-</span>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-sm-3 col-form-label">Foto KK</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoKkLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto KK
                                                </a>
                                                <span id="noFotoKk" style="display: none;">-</span>
                                            </div>
                                            <label class="col-sm-3 col-form-label">Foto KTP Pasangan</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoKtpPasanganLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto KTP Pasangan
                                                </a>
                                                <span id="noFotoKtpPasangan" style="display: none;">-</span>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-sm-3 col-form-label">Foto NPWP</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoNpwpLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto NPWP
                                                </a>
                                                <span id="noFotoNpwp" style="display: none;">-</span>
                                            </div>
                                            <label class="col-sm-3 col-form-label">Foto BPJS</label>
                                            <div class="col-sm-3">
                                                <a href="#" class="view-photo-link" id="fotoBpjsLink"
                                                    target="_blank" style="display: none;">
                                                    <i class="fas fa-external-link-alt"></i> Lihat Foto BPJS
                                                </a>
                                                <span id="noFotoBpjs" style="display: none;">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="page-3">
                                        <h3>Tagihan & Pembayaran</h3>
                                        <hr>
                                        <div id="tagihan-pembayaran-wrapper">
                                            {{-- Tabel Tagihan --}}
                                            <h5>- Tagihan - </h5>
                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr class="table-primary">
                                                        <th>No</th>
                                                        <th>Jenis Tagihan</th>
                                                        <th>Harga</th>
                                                        <th>Catatan</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbody-tagihan"></tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="2" class="text-right">Total</th>
                                                        <th id="total-tagihan">Rp 0</th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            {{-- Tabel Pembayaran --}}
                                            <h5>- Pemasukan - </h5>
                                            <table class="table table-bordered table-sm mt-3">
                                                <thead>
                                                    <tr class="table-success">
                                                        <th>No</th>
                                                        <th>Tanggal</th>
                                                        <th>Jenis</th>
                                                        <th>Harga</th>
                                                        <th>Keterangan</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbody-pembayaran"></tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="3" class="text-right">Total</th>
                                                        <th id="total-pembayaran">Rp 0</th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            {{-- Tabel Pengeluaran --}}
                                            <h5>- Pengeluaran - </h5>
                                            <table class="table table-bordered table-sm mt-3">
                                                <thead>
                                                    <tr class="table-danger">
                                                        <th>No</th>
                                                        <th>Tanggal</th>
                                                        <th>Jenis</th>
                                                        <th>Harga</th>
                                                        <th>Keterangan</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbody-pengeluaran"></tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="3" class="text-right">Total</th>
                                                        <th id="total-pengeluaran">Rp 0</th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            <div id="sisa-bayar-wrapper" class="mt-3">
                                                <table class="table table-bordered table-sm">
                                                    <tr class="table-warning">
                                                        <th colspan="2" class="text-center">Sisa Bayar</th>
                                                    </tr>
                                                    <tr>
                                                        <td><strong id="sisa-bayar">Rp 0</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="page-4">
                                        <h3>Foto Unit</h3>
                                        <hr>
                                        <div id="foto">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Keluar</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).on('click', '#btn-cetak', function() {
            const data = {
                _token: $('meta[name="csrf-token"]').attr('content'),
                kode_kavling: $('#kode_kavling_id').val(),
                status: 'Terjual',
                lokasi: $('#id_lokasi').val(),
                blok: $('#kode_kavling_id').val(),
                luas_tanah: $('#luas_tanah_id').val(),
                harga: $('#harga_id').val()?.replace(/[^0-9]/g, ''),
                no_sertifikat: $('#no_sertifikat_id').val(),
                nama_customer: $('#nama_lengkap_id').val(),
                no_ktp: $('#no_ktp_id').val(),
                tempat_lahir: $('#tempat_lahir_id').val(),
                tgl_lahir: $('#tgl_lahir_id').val(),
                alamat: $('#alamat_id').val(),
                no_hp: $('#no_telp_id').val(),
                pekerjaan: $('#pekerjaan_id').val() || $('#jenis_pembelian_id').val(),
                marketing: '-',
                foto: '',
            };

            if (!data.kode_kavling || !data.nama_customer) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Data tidak lengkap. Pastikan detail sudah dimuat.',
                });
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('st-penjualan.cetak') }}';
            form.target = '_blank';

            for (const key in data) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key] || '';
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        let loadedSiteplans = [];

        $(document).ready(function() {
            const firstLocationId = $('[data-id-lokasi]').first().data('id-lokasi');
            if (firstLocationId) {
                loadSiteplanData(firstLocationId);
            }

            $('.select-jk').select2({
                theme: "bootstrap4",
                minimumResultsForSearch: Infinity,
                placeholder: "Pilih Jenis Kelamin",
                width: '100%',
            });

        });

        function loadSiteplanData(idLokasi) {
            if (loadedSiteplans.includes(idLokasi)) {
                return;
            }

            const tabPane = $(`#lokasi-${idLokasi}`);
            const contentDiv = tabPane.find('.siteplan-content');

            if (!contentDiv.find('.loading-spinner').length) {
                contentDiv.html(`
                    <div class="loading-spinner text-center d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status"></div>
                        <span class="ml-2">Memuat Siteplan...</span>
                    </div>
                `);
            }

            $.ajax({
                url: `{{ route('st-penjualan.load', ['id_lokasi' => ':id']) }}`.replace(':id', idLokasi),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        contentDiv.html(response.html);
                        loadedSiteplans.push(idLokasi);

                        const svgElement = tabPane.find('svg').get(0);
                        const svgContainer = tabPane.find('.svg-container').get(0);
                        const resetButton = tabPane.find('.reset-button').get(0);
                        if (svgElement && svgContainer && resetButton) {
                            initializeSVGControls(svgElement, svgContainer, resetButton);
                        }
                    } else {
                        audio.play();
                        toastr.error("Gagal memuat siteplan.", "GAGAL!", {
                            progressBar: true,
                            timeOut: 3500,
                            positionClass: "toast-bottom-right",
                        });
                    }
                },
                error: function(xhr, status, error) {
                    contentDiv.html(`
                        <div class="alert alert-danger">
                            Gagal memuat siteplan. Silakan coba lagi.
                            <br><button class="btn btn-sm btn-primary mt-2" onclick="loadSiteplanData(${idLokasi})">Retry</button>
                        </div>
                    `);
                    toastr.error("Gagal memuat siteplan.", "GAGAL!", {
                        progressBar: true,
                        timeOut: 3500,
                        positionClass: "toast-bottom-right",
                    });
                }
            });
        }

        function initializeSVGInteractions(container) {
            container.find('.reset-button').on('click', function() {
                const svg = container.find('svg');
                if (svg.length) {
                    svg.css('transform', '');
                }
            });
        }

        function loadFotoKavling(id_kavling) {
            $.ajax({
                url: '{{ url('/admin/st-penjualan/foto') }}/' + id_kavling,
                type: 'GET',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#foto').html('<div class="row">' + response.html + '</div>');

                    }
                },
                error: function() {
                    toastr.error('Gagal memuat foto kavling.');
                }
            });
        }

        function toggleLegend() {
            const legend = document.getElementById('legend');
            const showBtn = document.getElementById('show-btn');

            legend.classList.toggle('hidden');

            if (legend.classList.contains('hidden')) {
                showBtn.classList.add('visible');
            } else {
                showBtn.classList.remove('visible');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const legend = document.getElementById('legend');
            const showBtn = document.getElementById('show-btn');

            legend.classList.remove('hidden');
            showBtn.classList.remove('visible');
        });

        $('#modalDetail').on('hidden.bs.modal', function() {
            $('#testPesanForm')[0].reset();
        });

        $(document).on('click', '.detail-button', function() {
            var url = $(this).data('url');
            var id_kavling = $(this).data('id-kavling');
            $('#modalDetail').data('id-kavling', id_kavling);

            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#id_kavling').val(id_kavling);
                        $('#kode_kavling_id').val(response.kavling.kode_kavling ?? '-');
                        $('#panjang_kanan_id').val(response.kavling.panjang_kanan ?? '-');
                        $('#lebar_depan_id').val(response.kavling.lebar_depan ?? '-');
                        $('#luas_tanah_id').val(response.kavling.luas_tanah ?? '-');
                        $('#harga_id').val(response.kavling.hrg_jual ?? '-');
                        $('#keterangan_id').val(response.kavling.keterangan ?? '-');
                        $('#id_rumah_sikumbang_id').val(response.kavling.id_rumah_sikumbang ?? '-');
                        $('#no_sertifikat_id').val(response.kavling.no_sertifikat ?? '-');

                        if (response.customer) {
                            $('#nama_lengkap_id').val(response.customer.nama_lengkap ?? '-');
                            $('#no_ktp_id').val(response.customer.no_ktp ?? '-');
                            $('#no_ktp_p_id').val(response.customer.no_ktp_p ?? '-');
                            $('#tempat_lahir_id').val(response.customer.tempat_lahir ?? '-');
                            $('#tgl_lahir_id').val(response.customer.tgl_lahir ?? '-');
                            $('#jenis_kelamin_id').val(response.customer.jenis_kelamin ?? '-');
                            $('#alamat_id').val(response.customer.alamat ?? '-');
                            $('#alamat_domisili_id').val(response.customer.alamat_domisili ?? '-');
                            $('#no_telp_id').val(response.customer.no_telp ?? '-');
                            $('#npwp_id').val(response.customer.npwp ?? '-');
                            $('#jenis_pembelian_id').val(response.customer.jenis_pembelian ?? '-');
                        }
                        $('#id_lokasi').val(response.kavling.id_lokasi);

                        $('#lokasi_id').val(response.lokasi);

                        if (response.listrikAir) {
                            updatePhotoLinks(response.listrikAir);
                        } else {
                            updatePhotoLinks({});
                        }

                        if (response.files) {
                            updateCustomerPhotoLinks(response.files);
                        } else {
                            hideAllCustomerPhotoLinks();
                        }
                    } else {
                        alert('Gagal mendapatkan data.');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                },
            });
        });

        function updateCustomerPhotoLinks(files) {
            $('.view-photo-link').hide();
            $('[id^="noFoto"]').hide();

            if (files.ktp) {
                $('#fotoKtpLink').attr('href', files.ktp).show();
            } else {
                $('#noFotoKtp').show();
            }

            if (files.pemohon) {
                $('#fotoPemohonLink').attr('href', files.pemohon).show();
            } else {
                $('#noFotoPemohon').show();
            }

            if (files.kk) {
                $('#fotoKkLink').attr('href', files.kk).show();
            } else {
                $('#noFotoKk').show();
            }

            if (files.ktp_pasangan) {
                $('#fotoKtpPasanganLink').attr('href', files.ktp_pasangan).show();
            } else {
                $('#noFotoKtpPasangan').show();
            }

            if (files.npwp) {
                $('#fotoNpwpLink').attr('href', files.npwp).show();
            } else {
                $('#noFotoNpwp').show();
            }

            if (files.bpjs) {
                $('#fotoBpjsLink').attr('href', files.bpjs).show();
            } else {
                $('#noFotoBpjs').show();
            }
        }

        function hideAllCustomerPhotoLinks() {
            $('.view-photo-link').hide();
            $('[id^="noFoto"]').show();
        }

        $('#modalDetail').on('shown.bs.modal', function() {
            const id_kavling = $(this).data('id-kavling');
            if (!id_kavling) return;

            loadFotoKavling(id_kavling);
            loadTagihan(id_kavling);
        });

        function loadTagihan(id_kavling) {
            $.ajax({
                url: '{{ url('/admin/st-penjualan/tagihan') }}/' + id_kavling,
                type: 'GET',
                success: function(res) {
                    if (res.status === 'success') {
                        let htmlTagihan = '',
                            htmlPembayaran = '',
                            htmlPengeluaran = '';
                        let totalTagihan = 0,
                            totalPembayaran = 0,
                            totalPengeluaran = 0;

                        if (res.tagihan && res.tagihan.length) {
                            res.tagihan.forEach((item, i) => {
                                const jumlah = Number(item.jumlah ?? item.jumlah_tagihan ?? 0);
                                totalTagihan += jumlah;

                                htmlTagihan += `
                                <tr>
                                    <td>${i + 1}</td>
                                    <td>${item.jenis ?? item.jenis_tagihan ?? '-'}</td>
                                    <td>Rp ${numberFormat(jumlah)}</td>
                                    <td>${item.catatan ?? ''}</td>
                                </tr>
                            `;
                            });
                        } else {
                            htmlTagihan =
                                '<tr><td colspan="4" class="text-center">Tidak ada tagihan.</td></tr>';
                        }

                        if (res.pembayaran && res.pembayaran.length) {
                            res.pembayaran.forEach((item, i) => {
                                const jumlah = Number(item.jumlah ?? 0);
                                totalPembayaran += jumlah;

                                htmlPembayaran += `
                                <tr>
                                    <td>${i + 1}</td>
                                    <td>${formatTanggalIndo(item.tanggal)}</td>
                                    <td>${item.jenis_pembayaran?.nama_jenis ?? '-'}</td>
                                    <td>Rp ${numberFormat(jumlah)}</td>
                                    <td>${item.keterangan ?? '-'}</td>
                                </tr>
                            `;
                            });
                        } else {
                            htmlPembayaran =
                                '<tr><td colspan="5" class="text-center">Tidak ada pembayaran.</td></tr>';
                        }

                        if (res.pengeluaran && res.pengeluaran.length) {
                            res.pengeluaran.forEach((item, i) => {
                                const jumlah = Number(item.jumlah ?? 0);
                                totalPengeluaran += jumlah;

                                htmlPengeluaran += `
                                <tr>
                                    <td>${i + 1}</td>
                                    <td>${formatTanggalIndo(item.tanggal)}</td>
                                    <td>${item.jenis_pembayaran?.nama_jenis ?? '-'}</td>
                                    <td>Rp ${numberFormat(jumlah)}</td>
                                    <td>${item.keterangan ?? '-'}</td>
                                </tr>
                            `;
                            });
                        } else {
                            htmlPengeluaran =
                                '<tr><td colspan="5" class="text-center">Tidak ada pengeluaran.</td></tr>';
                        }

                        const sisaBayar = totalTagihan - totalPembayaran;
                        $('#sisa-bayar').text(`Rp ${numberFormat(sisaBayar)}`);

                        $('#tbody-tagihan').html(htmlTagihan);
                        $('#total-tagihan').text('Rp ' + numberFormat(totalTagihan));

                        $('#tbody-pembayaran').html(htmlPembayaran);
                        $('#total-pembayaran').text('Rp ' + numberFormat(totalPembayaran));

                        $('#tbody-pengeluaran').html(htmlPengeluaran);
                        $('#total-pengeluaran').text('Rp ' + numberFormat(totalPengeluaran));

                    } else {
                        $('#tagihan-pembayaran-wrapper').html(
                            `<div class="alert alert-danger">${res.message ?? 'Gagal mengambil data tagihan'}</div>`
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $('#tagihan-pembayaran-wrapper').html(
                        '<div class="alert alert-danger">Gagal mengambil data</div>'
                    );
                }
            });
        }

        function numberFormat(x) {
            if (!x) return 0;
            return Number(x).toLocaleString('id-ID');
        }

        function formatTanggalIndo(tanggal) {
            if (!tanggal) return '-';

            const bulanIndo = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            const d = new Date(tanggal);
            const tgl = String(d.getDate()).padStart(2, '0');
            const bln = bulanIndo[d.getMonth()];
            const thn = d.getFullYear();

            return `${tgl}  ${bln}  ${thn}`;
        }
    </script>
    <script src={{ asset('assets/svg_1.js') }}></script>
@endpush
