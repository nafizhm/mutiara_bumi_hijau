<div class="modal fade" id="receivableModal" tabindex="-1" aria-labelledby="receivableModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receivableModalTitle">Detail Piutang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="receivableError" class="alert alert-danger d-none" role="alert"></div>
                <div id="receivablePicker" class="mb-3 d-none">
                    <label class="form-label" for="receivableSelect">Piutang di luar penjualan</label>
                    <select class="form-select" id="receivableSelect"><option value="">Pilih piutang</option></select>
                </div>
                <div id="receivableContent" class="d-none">
                    <p class="fw-semibold mb-1" id="receivableDescription"></p>
                    <p class="text-muted small" id="receivableSource"></p>
                    <div class="row g-2 mb-4">
                        <div class="col-12 col-sm-4"><div class="border rounded p-3 h-100"><small class="text-muted">Total piutang</small><div class="fw-semibold" id="receivableTotal"></div></div></div>
                        <div class="col-6 col-sm-4"><div class="border rounded p-3 h-100"><small class="text-muted">Sudah dibayar</small><div class="fw-semibold text-success" id="receivablePaid"></div></div></div>
                        <div class="col-6 col-sm-4"><div class="border rounded p-3 h-100"><small class="text-muted">Sisa tagihan</small><div class="fw-semibold" id="receivableRemaining"></div></div></div>
                    </div>
                    <form id="receivableAdditionalForm" class="border rounded p-3 mb-4 d-none">
                        @csrf
                        <h6>Tambah Piutang Customer</h6>
                        <p class="small text-muted">Untuk kelebihan tanah, biaya surat, atau biaya lainnya. Dicatat sebagai piutang terpisah pada customer yang sama.</p>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Kategori biaya</label><select name="id_kategori_transaksi" id="receivableAdditionalCategory" class="form-select" required></select></div>
                            <div class="col-sm-4"><label class="form-label">Tanggal</label><input type="date" name="tanggal_piutang" class="form-control" value="{{ now('Asia/Jakarta')->toDateString() }}" required></div>
                            <div class="col-sm-4"><label class="form-label">Nominal</label><input type="number" name="nominal" class="form-control" min="1" max="2147483647" step="1" required></div>
                            <div class="col-sm-4"><label class="form-label">Keterangan biaya</label><input type="text" name="deskripsi" class="form-control" maxlength="1000" placeholder="Contoh: Kelebihan tanah" required></div>
                            <div class="col-12 text-end"><button type="submit" class="btn btn-primary">Simpan Tambahan Piutang</button></div>
                        </div>
                    </form>
                    <form id="receivablePaymentForm" class="border rounded p-3 mb-4 d-none">
                        @csrf
                        <h6 class="mb-3">Catat pembayaran piutang</h6>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="receivableDate">Tanggal pembayaran</label>
                                <input type="date" id="receivableDate" name="tanggal" class="form-control" value="{{ now('Asia/Jakarta')->toDateString() }}" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="receivableBank">Rekening penerima</label>
                                <select id="receivableBank" name="id_bank" class="form-select" required>
                                    <option value="">Pilih rekening</option>
                                    @foreach ($bankList as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->nama }}{{ $bank->no_rek ? ' · ' . $bank->no_rek : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="receivableAmount">Nominal pembayaran</label>
                                <div class="input-group"><span class="input-group-text">Rp</span><input type="number" min="1" step="1" id="receivableAmount" name="nominal" class="form-control" inputmode="numeric" required></div>
                                <small class="text-muted">Bisa dibayar sebagian atau langsung lunas.</small>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="receivableNote">Keterangan (opsional)</label>
                                <input type="text" id="receivableNote" name="keterangan" class="form-control" maxlength="1000">
                            </div>
                            <div class="col-12 text-end"><button type="submit" id="receivableSubmit" class="btn btn-primary">Simpan Pembayaran</button></div>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
                        <h6 class="mb-0">Riwayat pembayaran</h6>
                        <button type="button" id="receivableStartPay" class="btn btn-primary btn-sm d-none">Bayar Piutang</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover small align-middle">
                            <thead><tr><th>Tanggal</th><th>Rekening</th><th>Keterangan</th><th class="text-end">Nominal</th></tr></thead>
                            <tbody id="receivableHistory"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function () {
    const modalElement = document.getElementById('receivableModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const detailUrl = @json(route('piutang.show', '__id__'));
    const payUrl = @json(route('piutang.pay', '__id__'));
    let activeId = null, loading = null, saving = false, requestVersion = 0;
    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const showError = message => $('#receivableError').removeClass('d-none').text(message);
    const clearError = () => $('#receivableError').addClass('d-none').text('');
    function load(id, pay) {
        const version = ++requestVersion;
        if (loading) loading.abort();
        activeId = null;
        clearError();
        $('#receivableContent').addClass('d-none');
        $('#receivableModalTitle').text('Memuat piutang...');
        loading = $.get(detailUrl.replace('__id__', encodeURIComponent(id))).done(function (response) {
            if (version !== requestVersion) return;
            const data = response.data;
            activeId = data.id;
            $('#receivableModalTitle').text(pay ? 'Pembayaran Piutang' : 'Detail Piutang');
            $('#receivableDescription').text(data.deskripsi || '-');
            $('#receivableSource').text((data.id_customer ? 'Penjualan kavling' : 'Piutang di luar penjualan') + (data.customer ? ' · ' + data.customer.nama_lengkap : ''));
            $('#receivableTotal').text(money(data.nominal));
            $('#receivableAdditionalForm').toggleClass('d-none', !response.can_additional);
            const additionalCategories = $('#receivableAdditionalCategory').empty().append($('<option>').val('').text('Pilih kategori biaya'));
            (response.additional_categories || []).forEach(category => additionalCategories.append($('<option>').val(category.id).text(category.kategori)));
            $('#receivablePaid').text(money(data.terbayar));
            $('#receivableRemaining').text(money(data.sisa_bayar));
            $('#receivablePaymentForm')[0].reset();
            $('#receivableAmount').attr('max', Number(data.sisa_bayar)).val('');
            $('#receivableDate').attr('min', data.tanggal_piutang || '');
            $('#receivablePaymentForm').toggleClass('d-none', !pay || !response.can_pay);
            $('#receivableStartPay').toggleClass('d-none', pay || !response.can_pay);
            const history = $('#receivableHistory').empty();
            (data.payments || []).forEach(function (payment) {
                const row = $('<tr>');
                $('<td>').addClass('text-nowrap').text(payment.tanggal ? payment.tanggal.split('-').reverse().join('/') : '-').appendTo(row);
                $('<td>').text(payment.bank ? payment.bank.nama : '-').appendTo(row);
                $('<td>').text([payment.no_kwitansi, payment.keterangan].filter(Boolean).join(' · ') || '-').appendTo(row);
                $('<td>').addClass('text-end text-nowrap fw-semibold').text(money(payment.nominal)).appendTo(row);
                history.append(row);
            });
            if (!(data.payments || []).length) history.append($('<tr>').append($('<td colspan="4">').addClass('text-center text-muted py-4').text('Belum ada pembayaran.')));
            $('#receivableContent').removeClass('d-none');
        }).fail(function (xhr, status) {
            if (status !== 'abort') { $('#receivableModalTitle').text('Detail Piutang'); showError('Data piutang gagal dimuat. Silakan coba lagi.'); }
        });
    }
    function open(id, pay) {
        if (saving) return;
        clearError();
        $('#receivablePicker').addClass('d-none');
        modal.show();
        load(id, pay);
    }
    $(document).on('click', '.piutang-history-button', function () { open($(this).data('id'), false); });
    $(document).on('click', '.piutang-pay-button', function () { open($(this).data('id'), true); });
    $('#receivableStartPay').on('click', function () { $('#receivablePaymentForm').removeClass('d-none'); $(this).addClass('d-none'); $('#receivableDate').trigger('focus'); });
    $(document).on('click', '.piutang-open-pay', function () {
        activeId = null;
        ++requestVersion;
        if (loading) loading.abort();
        clearError();
        $('#receivableContent').addClass('d-none');
        $('#receivableModalTitle').text('Bayar Piutang');
        $('#receivablePicker').removeClass('d-none');
        const select = $('#receivableSelect').empty().append($('<option>').val('').text('Memuat piutang...'));
        modal.show();
        $.get(@json(route('piutang.payable'))).done(function (response) {
            select.empty().append($('<option>').val('').text(response.data.length ? 'Pilih piutang' : 'Tidak ada piutang lain yang belum lunas'));
            response.data.forEach(item => select.append($('<option>').val(item.id).text(item.deskripsi + ' · Sisa ' + money(item.sisa_bayar))));
        }).fail(() => showError('Daftar piutang gagal dimuat. Silakan coba lagi.'));
    });
    $('#receivableSelect').on('change', function () {
        if (this.value) load(this.value, true);
        else { activeId = null; ++requestVersion; if (loading) loading.abort(); $('#receivableContent').addClass('d-none'); }
    });
    $('#receivableAdditionalForm').on('submit', function (event) {
        event.preventDefault();
        if (!activeId || saving) return;
        saving = true;
        const form = $(this), button = form.find('button[type="submit"]');
        button.prop('disabled', true);
        clearError();
        $.ajax({
            url: @json(route('piutang.additional', ['id' => '__id__'])).replace('__id__', encodeURIComponent(activeId)),
            method: 'POST', data: form.serialize()
        }).done(function () {
            form[0].reset();
            toastr.success('Tambahan piutang berhasil dicatat pada customer yang sama.');
            $('.data-table').each(function () { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(null, false); });
        }).fail(function (xhr) {
            showError(xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : (xhr.responseJSON?.message || 'Gagal menambahkan piutang.'));
        }).always(function () { saving = false; button.prop('disabled', false); });
    });
    $('#receivablePaymentForm').on('submit', function (event) {
        event.preventDefault();
        if (!activeId || saving) return;
        saving = true;
        const id = activeId, data = $(this).serialize();
        clearError();
        $('#receivableSubmit').prop('disabled', true).text('Menyimpan...');
        $('#receivableSelect').prop('disabled', true);
        $.ajax({ url: payUrl.replace('__id__', encodeURIComponent(id)), method: 'POST', data: data })
            .done(function () {
                toastr.success('Pembayaran piutang berhasil dicatat.');
                $('.data-table').each(function () { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(null, false); });
                load(id, false);
            }).fail(function (xhr) {
                const errors = xhr.responseJSON?.errors;
                showError(errors ? Object.values(errors).flat().join(' ') : 'Pembayaran gagal disimpan. Silakan coba lagi.');
            }).always(function () {
                saving = false;
                $('#receivableSubmit').prop('disabled', false).text('Simpan Pembayaran');
                $('#receivableSelect').prop('disabled', false);
            });
    });
    $('#receivableModal').on('hide.bs.modal', function (event) { if (saving) event.preventDefault(); });
    $('#receivableModal').on('hidden.bs.modal', function () { ++requestVersion; if (loading) loading.abort(); activeId = null; });
});
</script>
@endpush
