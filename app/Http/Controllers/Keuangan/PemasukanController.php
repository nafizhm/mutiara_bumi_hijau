<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Bank;
use App\Models\KategoriTransaksi;
use App\Models\Pemasukan;
use App\Models\Piutang;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class PemasukanController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $permissions = HakAksesController::getUserPermissions();
        $canPayPiutang = \App\Services\ReceivablePayment::canPay($request->user()?->id);

        if ($request->ajax()) {
            $data = Pemasukan::with(['piutang', 'bank', 'kategori'])->orderBy('tanggal', 'desc')->orderBy('id', 'desc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('tanggal', function ($row) {
                    return Carbon::parse($row->tanggal)->translatedFormat('j F Y');
                })
                ->editColumn('nominal', function ($row) {
                    return '
                    <div class="d-flex justify-content-between harga-format w-100">
                        <span>Rp.</span>
                        <span>'.number_format($row->nominal, 0, ',', '.').'</span>
                    </div>';
                })
                ->addColumn('rekening', function ($row) {
                    $bank = $row->bank;

                    return $bank ? $bank->nama : 'Bank Tidak Diketahui';
                })
                ->addColumn('id_kategori_transaksi', function ($row) {
                    $kategori = $row->kategori;
                    $nama = $kategori ? $kategori->kategori : 'Tidak Diketahui';

                    return $nama;
                })
                ->filter(function ($query) use ($request) {
                    if ($request->has('search') && $request->input('search.value') != '') {
                        $search = $request->input('search.value');

                        $bankIds = Bank::where('nama', 'like', "%$search%")->pluck('id');
                        $kategoriIds = KategoriTransaksi::where('kategori', 'like', "%$search%")->pluck('id');

                        $query->where(function ($q) use ($bankIds, $kategoriIds, $search) {
                            $q->whereIn('id_bank', $bankIds)
                                ->orWhereIn('id_kategori_transaksi', $kategoriIds)
                                ->orWhere('keterangan', 'like', "%$search%");
                        });
                    }
                })
                ->filterColumn('tanggal', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->WhereDate('tanggal', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('action', function ($row) use ($permissions, $canPayPiutang) {
                    $editUrl = route('pemasukan.edit', $row->id);
                    $detailUrl = route('pemasukan.show', $row->id);
                    $deleteUrl = route('pemasukan.destroy', $row->id);

                    $disabledKategori = (bool) ($row->id_hutang || $row->id_mutasi || $row->id_customer || in_array($row->id_kategori_transaksi, [25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36]));

                    $btn = '<div class="d-flex flex-wrap justify-content-center gap-1">';

                    if ($row->id_hutang != 0 || $row->id_customer != 0 || $row->id_mutasi != 0) {
                        $btn .= '<button class="btn btn-primary btn-sm mx-1 detail-button"
            data-id="'.e($row->id).'"
            data-url="'.e($detailUrl).'">
            Detail
        </button>';
                    } else {
                        if ($permissions['edit']) {
                            $btn .= '<button class="btn btn-primary btn-sm mx-1 edit-button '.($disabledKategori ? 'disabled' : '').'"
                data-id="'.e($row->id).'"
                data-url="'.($disabledKategori ? '#' : e($editUrl)).'" '.($disabledKategori ? 'disabled' : '').'>Edit</button>';
                        }
                    }

                    if ($permissions['hapus']) {
                        $btn .= '<form action="'.($disabledKategori ? '#' : e($deleteUrl)).'" method="POST" style="display:inline;">
        '.csrf_field().method_field('DELETE').'
        <button type="submit" class="delete-button btn btn-danger btn-sm mx-1 '.($disabledKategori ? 'disabled' : '').'" '.($disabledKategori ? 'disabled' : '').'>
            Hapus
        </button>
        </form>';
                    }

                    if ($row->piutang) {
                        $btn .= '<button type="button" class="btn btn-sm btn-outline-primary piutang-history-button" data-id="'.$row->id_piutang.'">Rincian Piutang</button>';
                    }
                    if ($row->piutang && ! $row->piutang->id_customer && $row->piutang->sisa_bayar > 0 && $canPayPiutang) {
                        $btn .= '<button type="button" class="btn btn-sm btn-success mx-1 piutang-pay-button" data-id="'.$row->id_piutang.'">Bayar Piutang</button>';
                    }
                    $btn .= '</div>';

                    return $btn;
                })

                ->rawColumns(['action', 'tanggal', 'nominal', 'id_kategori_transaksi'])
                ->make(true);
        }

        $kategoriTransaksi = KategoriTransaksi::where('jenis_kategori', 'PEMASUKAN')
            ->whereNotIn('kategori', array_diff(\App\Services\FinanceCategories::SYSTEM, ['Pembayaran Piutang']))
            ->whereNotIn('id', [25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36])
            ->get();

        $kategoriTransaksiDetail = KategoriTransaksi::where('jenis_kategori', 'PEMASUKAN')
            ->whereIn('id', [25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36])
            ->get();

        $PiutangList = Piutang::nonSales()->where('sisa_bayar', '>', 0)->orderByDesc('id')->get();
        $piutangCategoryId = KategoriTransaksi::where('kategori', 'Pembayaran Piutang')->where('jenis_kategori', 'PEMASUKAN')->value('id');

        $bankList = Bank::all();

        return view('admin.keuangan.pemasukan.index', compact('permissions', 'kategoriTransaksi', 'kategoriTransaksiDetail', 'PiutangList', 'bankList', 'canPayPiutang', 'piutangCategoryId'));
    }

    public function edit($id)
    {
        $list = Pemasukan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $list,
        ]);
    }

    public function show($id)
    {
        $list = Pemasukan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $list,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage($request->user()?->id, 'pemasukan.index', 'tambah'), 403);
        $service = app(\App\Services\ReceivablePayment::class);
        $input = $service->validate($request);
        $categoryId = KategoriTransaksi::where('kategori', 'Pembayaran Piutang')->where('jenis_kategori', 'PEMASUKAN')->value('id');
        if ($request->filled('id_piutang') || ($categoryId && (int) $request->id_kategori_transaksi === (int) $categoryId)) {
            abort_unless(\App\Services\ReceivablePayment::canPay($request->user()?->id), 403);
            $request->validate(['id_piutang' => 'required|integer|exists:piutang,id']);
            $service->pay((int) $request->id_piutang, $input);
        } else {
            $request->validate(['id_kategori_transaksi' => 'required|integer|exists:kategori_transaksi,id']);
            $category = \App\Services\FinanceCategories::ordinary((int) $request->id_kategori_transaksi, 'PEMASUKAN');
            Pemasukan::create($input + ['id_kategori_transaksi' => $category->id]);
        }

        return response()->json(['status' => 'success']);
    }

    public function update(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage($request->user()?->id, 'pemasukan.index', 'edit'), 403);
        $data = Pemasukan::findOrFail($id);
        $this->assertEditable($data);
        $service = app(\App\Services\ReceivablePayment::class);
        $input = $service->validate($request);
        if ($data->id_piutang) {
            if ($request->filled('id_piutang') && (int) $request->id_piutang !== (int) $data->id_piutang) {
                throw \Illuminate\Validation\ValidationException::withMessages(['id_piutang' => 'Pembayaran tidak dapat dipindahkan ke piutang lain.']);
            }
            $service->amend((int) $id, $input);
        } else {
            if ($request->filled('id_kategori_transaksi')) {
                $input['id_kategori_transaksi'] = \App\Services\FinanceCategories::ordinary((int) $request->id_kategori_transaksi, 'PEMASUKAN')->id;
            }
            $data->update($input);
        }

        return response()->json(['status' => 'success']);
    }

    public function destroy($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pemasukan.index', 'hapus'), 403);
        $data = Pemasukan::findOrFail($id);
        $this->assertEditable($data);
        if ($data->id_piutang) {
            app(\App\Services\ReceivablePayment::class)->cancel((int) $id);
        } else {
            $data->delete();
        }
        if ($data->lampiran && is_file(public_path('assets/keuangan/pemasukan/'.basename($data->lampiran)))) {
            unlink(public_path('assets/keuangan/pemasukan/'.basename($data->lampiran)));
        }

        return response()->json(['status' => 'success']);
    }

    private function assertEditable(Pemasukan $data): void
    {
        if ($data->id_customer || $data->id_hutang || $data->id_mutasi || $data->piutang?->id_customer || in_array($data->id_kategori_transaksi, range(25, 36))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['nominal' => 'Transaksi ini dikelola melalui menu asalnya.']);
        }
    }
}
