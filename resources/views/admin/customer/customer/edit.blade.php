@extends('admin.layout')
@section('content')
    <div class="page-content">
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-content-center justify-content-between">
                        <h3 class="font-weight-bold text-xl">Form Edit Customer</h3>
                    </div>
                </div>
                <form id="formData">
                    @csrf
                    <div class="card-body">
                        <input type="hidden" name="primary_id" id="primary_id" value="{{ $cust->id }}">

                        <div class="row mb-3">
                            <label for="tgl_terima" class="col-sm-2 col-form-label">Tanggal</label>
                            <div class="col-sm-2">
                                <input type="date" name="tgl_terima" id="tgl_terima" class="form-control"
                                    value="{{ $cust->tgl_terima }}">
                            </div>

                            <label for="nama_lengkap" class="col-sm-2 col-form-label">Nama Lengkap</label>
                            <div class="col-sm-6">
                                <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control"
                                    value="{{ $cust->nama_lengkap }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="no_ktp" class="col-sm-2 col-form-label">No. KTP</label>
                            <div class="col-sm-4">
                                <input type="number" name="no_ktp" id="no_ktp" class="form-control"
                                    value="{{ $cust->no_ktp }}">
                            </div>

                            <label for="no_ktp_p" class="col-sm-2 col-form-label">No. KTP Pasangan</label>
                            <div class="col-sm-4">
                                <input type="number" name="no_ktp_p" id="no_ktp_p" class="form-control"
                                    value="{{ $cust->no_ktp_p }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="tempat_lahir" class="col-sm-2 col-form-label">Tempat Lahir</label>
                            <div class="col-sm-4">
                                <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-control"
                                    value="{{ $cust->tempat_lahir }}">
                            </div>

                            <label for="tgl_lahir" class="col-sm-2 col-form-label">Tanggal Lahir</label>
                            <div class="col-sm-4">
                                <input type="date" name="tgl_lahir" id="tgl_lahir" class="form-control"
                                    value="{{ $cust->tgl_lahir }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="no_telp" class="col-sm-2 col-form-label">No. Telp / WA</label>
                            <div class="col-sm-4">
                                <input type="number" name="no_telp" id="no_telp" class="form-control"
                                    value="{{ $cust->no_telp }}">
                            </div>

                            <label for="jenis_kelamin" class="col-sm-2 col-form-label">Jenis Kelamin</label>
                            <div class="col-sm-4">
                                <select name="jenis_kelamin" id="jenis_kelamin" class="form-control select-jk">
                                    <option value=""></option>
                                    <option value="Laki-laki" {{ $cust->jenis_kelamin == 'Laki-laki' ? 'selected' : '' }}>
                                        Laki-laki
                                    </option>
                                    <option value="Perempuan" {{ $cust->jenis_kelamin == 'Perempuan' ? 'selected' : '' }}>
                                        Perempuan
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-sm-2 col-form-label">Email</label>
                            <div class="col-sm-4">
                                <input type="email" name="email" id="email" class="form-control"
                                    value="{{ $cust->email }}">
                            </div>

                            <label for="npwp" class="col-sm-2 col-form-label">NPWP</label>
                            <div class="col-sm-4">
                                <input type="text" name="npwp" id="npwp" class="form-control"
                                    value="{{ $cust->npwp }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="alamat" class="col-sm-2 col-form-label">Alamat KTP</label>
                            <div class="col-sm-10">
                                <textarea name="alamat" id="alamat" class="form-control" rows="3">{{ $cust->alamat }}</textarea>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="alamat_domisili" class="col-sm-2 col-form-label">Alamat Domisili</label>
                            <div class="col-sm-10">
                                <textarea name="alamat_domisili" id="alamat_domisili" class="form-control" rows="3">{{ $cust->alamat_domisili }}</textarea>
                            </div>
                        </div>

                        <div class="row mb-5">
                            <label for="pekerjaan" class="col-sm-2 col-form-label">Pekerjaan</label>
                            <div class="col-sm-4">
                                <input type="text" name="pekerjaan" id="pekerjaan" class="form-control"
                                    value="{{ $cust->pekerjaan }}">
                            </div>
                        </div>

                        <hr>

                        @php
                            $kodeKavling = $cust->transaksiKavling
                                ->map(fn($item) => $item->kavling->kode_kavling ?? '')
                                ->filter()
                                ->implode(', ');

                            $totalHargaRumah = $cust->transaksiKavling->sum('hrg_rumah');
                        @endphp

                        <div class="row mb-3 mt-5">
                            <label class="col-sm-2 col-form-label">Lokasi Perumahan</label>
                            <div class="col-sm-4">
                                <input type="text" name="lokasi_perumahan" id="lokasi_perumahan" class="form-control"
                                    value="{{ $cust->lokasi->nama_kavling ?? '-' }}" readonly>
                            </div>

                            <label class="col-sm-2 col-form-label">Blok / Kode Kavling</label>
                            <div class="col-sm-4">
                                <input type="text" name="kode_kavling" id="kode_kavling" class="form-control"
                                    value="{{ $kodeKavling }}" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-2 col-form-label">Harga Jual</label>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control format-number"
                                        value="{{ number_format($totalHargaRumah, 0, ',', '.') }}" readonly>
                                </div>
                            </div>

                            <label class="col-sm-2 col-form-label">Discount</label>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control format-number" id="discount"
                                        name="discount" value="{{ number_format($cust->discount ?? 0, 0, ',', '.') }}" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="id_marketing" class="col-sm-2 col-form-label">Marketing</label>
                            <div class="col-sm-4">
                                <select name="id_marketing" id="id_marketing" class="form-control select-marketing">
                                    <option value=""></option>
                                    @foreach ($marketing as $m)
                                        <option value="{{ $m->id }}"
                                            {{ $cust->id_marketing == $m->id ? 'selected' : '' }}>{{ $m->nama_marketing }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <label for="id_status_progres" class="col-sm-2 col-form-label">Status Penjualan</label>
                            <div class="col-sm-4">
                                <select name="id_status_progres" id="id_status_progres"
                                    class="form-control select-status-progres">
                                    <option value=""></option>
                                    @foreach ($progres as $p)
                                        <option value="{{ $p->id }}"
                                            {{ $cust->id_status_progres == $p->id ? 'selected' : '' }}>
                                            {{ $p->status_progres }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="ket_cashback" class="col-sm-2 col-form-label">Keterangan</label>
                            <div class="col-sm-10">
                                <textarea name="ket_cashback" id="ket_cashback" class="form-control" rows="3">{{ $cust->ket_cashback ?? '' }}</textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="id_bank" class="col-sm-2 col-form-label">Rekening Pembayaran</label>
                            <div class="col-sm-4">
                                <select class="form-select select-bank" name="id_bank" id="id_bank" disabled>
                                    <option value=""></option>
                                    @foreach ($bankList as $item)
                                        <option value="{{ $item->id }}"
                                            {{ $cust->id_bank == $item->id ? 'selected' : '' }}>{{ $item->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <label for="jenis_pembelian" class="col-sm-2 col-form-label">Jenis Pembelian</label>
                            <div class="col-sm-4">
                                <select name="jenis_pembelian" id="jenis_pembelian"
                                    class="form-control select-jenis-pembelian" disabled>
                                    <option value=""></option>
                                    <option value="Booking" {{ $cust->jenis_pembelian == 'Booking' ? 'selected' : '' }}>
                                        Booking Fee</option>
                                    <option value="Cash Keras"
                                        {{ $cust->jenis_pembelian == 'Cash Keras' ? 'selected' : '' }}>Cash Keras</option>
                                    <option value="Kredit" {{ $cust->jenis_pembelian == 'Kredit' ? 'selected' : '' }}>
                                        Kredit</option>
                                </select>
                            </div>
                        </div>

                        <!-- BOOKING ==================================> -->
                        <hr class="hr-transaksi" style="display: none;">
                        <div id="trx_booking" style="display: none;">

                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Pembayaran Booking</label>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control format-number" id="pembayaran_booking"
                                            name="pembayaran_booking" value="{{ number_format($cust->pembayaran_booking ?? 0, 0, ',', '.') }}" disabled>
                                    </div>
                                </div>
                                <label class="col-sm-2 col-form-label">Batas Booking</label>
                                <div class="col-sm-3">
                                    <input name="tgl_batas_booking" id="tgl_batas_booking" class="form-control"
                                        type="date" value="{{ $cust->tgl_batas_booking ?? '' }}" disabled>
                                </div>
                            </div>
                        </div>

                        <!-- KREDIT ==================================> -->
                        <hr class="hr-transaksi" style="display: none;">
                        <div id="trx_kredit" style="display: none;">
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">DP</label>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" class="form-control format-number" id="dp_kredit"
                                            name="dp_kredit" value="{{ number_format($cust->dp_kredit ?? 0, 0, ',', '.') }}" disabled>
                                    </div>
                                </div>
                                <label class="col-sm-2 col-form-label">Tenor (Jumlah Bulan)</label>
                                <div class="col-sm-4">
                                    <input type="number" name="inhouse_tenor" id="inhouse_tenor"
                                        class="form-control format-number" value="{{ $cust->inhouse_tenor ?? '' }}" disabled>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Cicilan Per Bulan</label>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" name="inhouse_perbulan" id="inhouse_perbulan"
                                            class="form-control format-number" value="{{ number_format($cust->inhouse_perbulan ?? 0, 0, ',', '.') }}" disabled>
                                    </div>
                                </div>
                                <label class="col-sm-2 col-form-label">Jatuh Tempo</label>
                                <div class="col-sm-4">
                                    <input type="date" name="inhouse_jatuh_tempo" class="form-control" value="{{ $cust->inhouse_jatuh_tempo ?? '' }}" disabled>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                aria-hidden="true"></span>
                            <span class="button-text">Simpan</span>
                        </button>
                    </div>
                </form>
        </section>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select-jk').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Jenis Kelamin',
                minimumResultsForSearch: Infinity,
                allowClear: true,
            });
            $('.select-lokasi').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Lokasi',
                allowClear: true,
            });
            $('.select-kavling').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Kavling',
                allowClear: true,
            });
            $('.select-marketing').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Marketing',
                allowClear: true,
            });
            $('.select-bank').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Rekening Pembayaran',
                allowClear: true,
            })
            $('.select-status-progres').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Status Penjualan',
                allowClear: true,
            });
            $('.select-jenis-pembelian').select2({
                dropdownParent: $('body'),
                width: '100%',
                placeholder: 'Pilih Jenis Pembelian',
                allowClear: true,
            });

            if ($('body').hasClass('dark')) {
                $('.select2-container').addClass('select2-dark');
            }


            const routeGetKavling = "{{ route('customer.getKavling', ':id') }}";
            const routeGetHarga = "{{ route('customer.getHargaKavling', ':ids') }}";

            function parseNumber(value) {
                return parseInt(String(value).replace(/\./g, '').replace(/[^\d]/g, '')) || 0;
            }

            function formatNumber(value) {
                return new Intl.NumberFormat('id-ID').format(value || 0);
            }

            function hitungCicilan() {
                const jenisPembelian = $('#jenis_pembelian').val();

                if (jenisPembelian !== 'Kredit') {
                    $('#inhouse_perbulan').val('');
                    return;
                }

                const hargaJual = parseNumber($('#hrg_jual').val());
                const discount = parseNumber($('#discount').val());
                const dpKredit = parseNumber($('#dp_kredit').val());
                const tenor = parseInt($('#inhouse_tenor').val()) || 0;

                const total = hargaJual - discount - dpKredit;

                if (tenor > 0 && total > 0) {
                    const cicilan = Math.floor(total / tenor);
                    $('#inhouse_perbulan').val(formatNumber(cicilan));
                } else {
                    $('#inhouse_perbulan').val('');
                }
            }

            $('#id_lokasi').on('change', function() {
                let idLokasi = $(this).val();

                $('#id_kavling')
                    .html('<option value="">Loading...</option>')
                    .trigger('change');

                $('#hrg_jual').val('');
                $('#inhouse_perbulan').val('');

                if (idLokasi) {
                    const urlKavling = routeGetKavling.replace(':id', idLokasi);

                    $.get(urlKavling, function(data) {
                        let options = '<option value=""></option>';

                        data.forEach(function(item) {
                            options +=
                                `<option value="${item.id}">${item.kode_kavling}</option>`;
                        });

                        $('#id_kavling').html(options).trigger('change');
                    });
                }
            });

            $('#id_kavling').on('change', function() {
                let idKavling = $(this).val();

                if (idKavling && idKavling.length > 0) {
                    let idsString = idKavling.join(',');

                    let urlHarga = routeGetHarga.replace(':ids', idsString);

                    $.get(urlHarga, function(data) {
                        $('#hrg_jual').val(data.formatted);
                        hitungCicilan();
                    });
                } else {
                    $('#hrg_jual').val('');
                    hitungCicilan();
                }
            });

            $('#discount, #dp_kredit').on('input', function() {
                hitungCicilan();
            });

            $('#inhouse_tenor').on('input', function() {
                hitungCicilan();
            });

            $('#jenis_pembelian').on('change', function() {
                hitungCicilan();
            });
        });

        function hideAllTransactionForms() {
            $('#trx_booking').hide();
            $('#trx_kredit').hide();
            $('.hr-transaksi').hide();
        }

        function showTransactionForm() {
            let selected = $('#jenis_pembelian').val();

            hideAllTransactionForms();

            if (selected === 'Booking') {
                $('#trx_booking').prev('.hr-transaksi').show();
                $('#trx_booking').show();
            }

            if (selected === 'Kredit') {
                $('#trx_kredit').prev('.hr-transaksi').show();
                $('#trx_kredit').show();
            }
        }

        $(document).ready(function() {
            showTransactionForm();

            $('#jenis_pembelian').on('change', function() {
                showTransactionForm();
            });
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

            let url = '{{ route('customer.update', ['customer' => ':id']) }}'.replace(':id',
                id);

            let method = 'PUT';

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
                    localStorage.setItem('customerSuccess', 'Data customer berhasil diperbarui!');
                    window.location.href = "{{ route('customer.index') }}";
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
                    }

                    spinner.addClass('d-none');
                    btnText.text('Simpan');
                    submitBtn.prop('disabled', false);
                }
            });
        });
    </script>
@endpush
