<?php
namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Bank;
use App\Models\Hutang;
use App\Models\KategoriTransaksi;
use App\Models\Pengeluaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PengeluaranController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $permissions = HakAksesController::getUserPermissions();

        if ($request->ajax()) {
            $data = Pengeluaran::orderBy('tanggal', 'desc')->orderBy('id', 'desc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('tanggal', function ($row) {
                    return Carbon::parse($row->tanggal)->translatedFormat('j F Y');
                })
                ->editColumn('nominal', function ($row) {
                    return '
                    <div class="d-flex justify-content-between harga-format w-100">
                        <span>Rp.</span>
                        <span>' . number_format($row->nominal, 0, ',', '.') . '</span>
                    </div>';
                })
                ->addColumn('rekening', function ($row) {
                    $bank = Bank::find($row->id_bank);
                    return $bank ? $bank->nama : 'Bank Tidak Diketahui';
                })
                ->addColumn('id_kategori_transaksi', function ($row) {
                    $kategori = KategoriTransaksi::find($row->id_kategori_transaksi);
                    $nama     = $kategori ? $kategori->kategori : 'Tidak Diketahui';
                    if ($row->keterangan) {
                        return '<div><strong>' . $nama . '</strong><br><small>' . e($row->keterangan) . '</small></div>';
                    }
                    return $nama;
                })
                ->filter(function ($query) use ($request) {
                    if ($request->has('search') && $request->input('search.value') != '') {
                        $search = $request->input('search.value');

                        $kategoriIds = KategoriTransaksi::where('kategori', 'like', "%$search%")->pluck('id');
                        $bankIds     = Bank::where('nama', 'like', "%$search%")->pluck('id');

                        $query->where(function ($q) use ($kategoriIds, $bankIds, $search) {
                            $q->whereIn('id_kategori_transaksi', $kategoriIds)
                                ->orWhereIn('id_bank', $bankIds)
                                ->orWhere('keterangan', 'like', "%$search%");
                        });
                    }
                })
                ->filterColumn('tanggal', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->WhereDate('tanggal', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $editUrl   = route('pengeluaran.edit', $row->id);
                    $detailUrl = route('pengeluaran.show', $row->id);
                    $deleteUrl = route('pengeluaran.destroy', $row->id);

                    $btn = '<div class="d-flex justify-content-center">';
                    if ($row->id_piutang != 0 || $row->id_proyek_bangunan_detail != 0 || $row->id_proyek_jalan_detail != 0 || $row->id_proyek_saluran_detail != 0 || $row->id_po != 0 || $row->id_mutasi != 0) {
                        $btn .= '<button class="btn btn-primary btn-sm mx-1 detail-button" data-id="' . e($row->id) . '"
                                data-url="' . e($detailUrl) . '">
                                Detail
                                </button>';
                    } else {
                        if ($permissions['edit']) {
                            $btn .= '<button class="btn btn-primary btn-sm mx-1 edit-button"
                                data-id="' . e($row->id) . '"
                                data-url="' . e($editUrl) . '">Edit</button>';

                        }
                    }
                    if ($permissions['hapus'] && !$row->id_piutang && !$row->id_mutasi && !$row->id_po && !$row->id_proyek_bangunan_detail && !$row->id_proyek_jalan_detail && !$row->id_proyek_saluran_detail) {
                        $btn .= '<form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">
                    ' . csrf_field() . method_field('DELETE') . '
                    <button type="submit" class="delete-button btn btn-danger btn-sm mx-1">
                        Hapus
                    </button>
                    </form>';
                    }
                    $btn .= '</div>';
                    return $btn;
                })

                ->rawColumns(['action', 'tanggal', 'nominal', 'id_kategori_transaksi'])
                ->make(true);
        }

        $kategoriTransaksi = KategoriTransaksi::where('jenis_kategori', 'PENGELUARAN')
            ->whereNotIn('kategori', array_diff(\App\Services\FinanceCategories::SYSTEM, ['Pembayaran Hutang']))
            ->get();

        $kategoriTransaksiDetail = KategoriTransaksi::where('jenis_kategori', 'PENGELUARAN')
            
            ->get();

        $HutangList = Hutang::where('sisa_bayar', '>', 0)->get();
        $debtCategoryId = \App\Services\FinanceCategories::id('Pembayaran Hutang', 'PENGELUARAN');

        $bankList = Bank::all();

        return view('admin.keuangan.pengeluaran.index', compact('permissions', 'kategoriTransaksi', 'kategoriTransaksiDetail', 'HutangList', 'bankList', 'debtCategoryId'));
    }

   public function edit($id)
    {
        $list = Pengeluaran::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $list,
        ]);
    }

    public function show($id)
    {
        $list = Pengeluaran::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $list,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pengeluaran.index', 'tambah'), 403);
        $input = app(\App\Services\ReceivablePayment::class)->validate($request);
        $input['keterangan'] = $input['keterangan'] ?? '';
        $input['lampiran'] = '';
        $category = \App\Services\FinanceCategories::id('Pembayaran Hutang', 'PENGELUARAN');
        if ($request->filled('id_hutang') || (int) $request->id_kategori_transaksi === $category) {
            $request->validate(['id_hutang' => 'required|integer|exists:hutang,id']);
            app(\App\Services\DebtPayment::class)->pay((int) $request->id_hutang, $input);
        } else {
            $request->validate(['id_kategori_transaksi' => 'required|integer|exists:kategori_transaksi,id']);
            $category = \App\Services\FinanceCategories::ordinary((int) $request->id_kategori_transaksi, 'PENGELUARAN');
            Pengeluaran::create($input + ['id_kategori_transaksi' => $category->id]);
        }
        return response()->json(['status' => 'success']);
    }

    public function update(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pengeluaran.index', 'edit'), 403);
        $expense = Pengeluaran::findOrFail($id);
        $this->assertEditable($expense);
        $input = app(\App\Services\ReceivablePayment::class)->validate($request);
        $input['keterangan'] = $input['keterangan'] ?? '';
        if ($expense->id_hutang) {
            if ($request->filled('id_hutang') && (int) $request->id_hutang !== (int) $expense->id_hutang) {
                throw \Illuminate\Validation\ValidationException::withMessages(['id_hutang' => 'Pembayaran tidak dapat dipindah ke hutang lain.']);
            }
            app(\App\Services\DebtPayment::class)->amend((int) $id, $input);
        } else {
            if ($request->filled('id_kategori_transaksi')) $input['id_kategori_transaksi'] = \App\Services\FinanceCategories::ordinary((int) $request->id_kategori_transaksi, 'PENGELUARAN')->id;
            $expense->update($input);
        }
        return response()->json(['status' => 'success']);
    }

    public function destroy($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pengeluaran.index', 'hapus'), 403);
        $expense = Pengeluaran::findOrFail($id);
        $this->assertEditable($expense);
        if ($expense->id_hutang) app(\App\Services\DebtPayment::class)->cancel((int) $id);
        else $expense->delete();
        \App\Services\FinanceFiles::remove(['pengeluaran'], $expense->lampiran);
        return response()->json(['status' => 'success']);
    }

    private function assertEditable(Pengeluaran $expense): void
    {
        if ($expense->id_piutang || $expense->id_mutasi || $expense->id_po || $expense->id_proyek_bangunan_detail || $expense->id_proyek_jalan_detail || $expense->id_proyek_saluran_detail) {
            throw \Illuminate\Validation\ValidationException::withMessages(['nominal' => 'Transaksi ini dikelola melalui menu asalnya.']);
        }
    }
}