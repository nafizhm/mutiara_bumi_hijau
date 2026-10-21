                <form id="formData" action="{{ route('kavling.update', $data->id) }}">
                    <div class="modal-header"><h5 class="modal-title" id="editKavlingTitle">Edit Kavling</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                    @csrf
                    <div class="modal-body">
                        <div id="editKavlingError" class="alert alert-danger d-none" role="alert"></div>
                        <input type="hidden" name="primary_id" id="primary_id" value="{{ $data->id }}">
                        <div class="row mb-3">
                            <label for="id_lokasi" class="col-sm-3 col-form-label">Lokasi / Cluster</label>
                            <div class="col-sm-6">
                                <input type="text" value="{{ $data->lokasi->nama_kavling ?? '-' }}" class="form-control"
                                    disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="kode_kavling" class="col-sm-3 col-form-label">Kode Kavling</label>
                            <div class="col-sm-6">
                                <input type="text" value="{{ $data->kode_kavling }}" name="kode_kavling"
                                    class="form-control" required disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="panjang" class="col-sm-3 col-form-label">Panjang</label>
                            <div class="col-sm-2">
                                <input type="number" step="any" name="panjang" id="panjang"
                                    value="{{ $data->panjang }}" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="lebar" class="col-sm-3 col-form-label">Lebar</label>
                            <div class="col-sm-2">
                                <input type="number" step="any" value="{{ $data->lebar }}" name="lebar"
                                    id="lebar" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="luas_tanah" class="col-sm-3 col-form-label">Luas Tanah</label>
                            <div class="col-sm-2">
                                <input type="number" step="any" value="{{ $data->luas_tanah }}" name="luas_tanah"
                                    id="luas_tanah" class="form-control">
                            </div>
                        </div>



                        <div class="row mb-3">
                            <label for="hrg_jual" class="col-sm-3 col-form-label">Harga Jual</label>
                            <div class="col-sm-4">
                                <input type="text" value="{{ number_format($data->hrg_jual, 0, ',', '.') }}"
                                    name="hrg_jual" id="hrg_jual" class="form-control format-number">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="keterangan" class="col-sm-3 col-form-label">Keterangan</label>
                            <div class="col-sm-4">
                                <input type="text" value="{{ $data->keterangan }}" name="keterangan" id="keterangan"
                                    class="form-control" maxlength="100">
                            </div>
                        </div>
                        <p class="small text-muted">Perubahan harga otomatis menyesuaikan piutang utama pelanggan aktif yang membeli kavling ini, dengan memperhitungkan diskon dan pembayaran yang sudah masuk.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                aria-hidden="true"></span>
                            <span class="button-text">Simpan</span>
                        </button>
                    </div>
                </form>
