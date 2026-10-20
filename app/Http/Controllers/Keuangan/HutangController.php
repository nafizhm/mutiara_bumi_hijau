<?php
namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Bank;
use App\Models\Hutang;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class HutangController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $permissions = HakAksesController::getUserPermissions();

        if ($request->ajax()) {
            $data = Hutang::orderBy('id', 'desc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('tanggal_hutang', function ($row) {
                    return Carbon::parse($row->tanggal_hutang)->translatedFormat('j F Y');
                })
                ->editColumn('nominal', function ($row) {
                    return '
                    <div class="d-flex justify-content-between harga-format w-100">
                        <span>Rp.</span>
                        <span>' . number_format($row->nominal, 0, ',', '.') . '</span>
                    </div>';
                })
                ->addColumn('lampiran', function ($row) {
                    if ($row->lampiran) {
                        return '<button class="btn btn-sm btn-success show-lampiran" data-file="' . e($row->lampiran) . '" data-toggle="modal" data-target="#modallampiran">Lihat</button>';
                    }
                    return '-';
                })
                ->editColumn('status', function ($row) {
                    $badge = match ($row->status) {
                        1 => '<span class="badge bg-danger">Belum Lunas</span>',
                        2       => '<span class="badge bg-success">Sudah Lunas</span>',
                        default => '<span class="badge bg-warning">Status Tidak Dikenal</span>',
                    };

                    if ($row->status == 1) {
                        $sisaBayar = number_format($row->sisa_bayar, 0, ',', '.');
                        return $badge . '<br><small class="text-muted">Sisa Bayar: Rp. ' . $sisaBayar . '</small>';
                    }

                    return $badge;
                })
                ->addColumn('tgl_pelunasan', function ($row) {
                    return $row->tgl_pelunasan
                    ? Carbon::parse($row->tgl_pelunasan)->translatedFormat('j F Y')
                    : '';
                })
                ->filterColumn('tanggal_hutang', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->WhereDate('tanggal_hutang', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $editUrl   = route('hutang.edit', $row->id);
                    $deleteUrl = route('hutang.destroy', $row->id);

                    $btn = '<div class="d-flex justify-content-center">';
                    if ($permissions['edit']) {
                        $btn .= '<button class="btn btn-primary btn-sm mx-1 edit-button"
                                data-id="' . e($row->id) . '"
                                data-url="' . e($editUrl) . '">Edit</button>';
                    }

                    if ($permissions['hapus']) {
                        $btn .= '<form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">
                    ' . csrf_field() . method_field('DELETE') . '
                    <button type="submit" class="delete-button btn btn-danger btn-sm mx-1">
                        Hapus
                    </button>
                    </form>';
                    }
                    if ($row->sisa_bayar > 0 && \App\Services\ReceivablePayment::canManage(auth()->id(), 'pengeluaran.index', 'tambah')) {
                        $btn .= '<a class="btn btn-success btn-sm mx-1" href="' . e(route('pengeluaran.index', ['bayar_hutang' => $row->id])) . '">Bayar</a>';
                    }
                    $btn .= '</div>';
                    return $btn;
                })
                ->rawColumns(['action', 'tanggal_hutang', 'nominal', 'status', 'tgl_bayar_hutang'])
                ->make(true);
        }

        $bankList = Bank::all();

        return view('admin.keuangan.hutang.index', compact('permissions', 'bankList'));
    }

    public function edit($id)
    {
        $list = Hutang::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $list,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'hutang.index', 'tambah'), 403);
        $input = $this->documentInput($request, true);
        return \App\Services\FinanceFiles::withUpload($request, 'lampiran', ['pemasukan'], function ($filename) use ($input) {
            return DB::transaction(function () use ($input, $filename) {
                $debt = Hutang::create($input + ['lampiran' => $filename, 'status' => 1, 'terbayar' => 0, 'sisa_bayar' => $input['nominal']]);
                Pemasukan::create(['id_hutang' => $debt->id, 'id_bank' => $input['id_bank'], 'tanggal' => $input['tanggal_hutang'],
                    'nominal' => $input['nominal'], 'lampiran' => $filename,
                    'id_kategori_transaksi' => \App\Services\FinanceCategories::id('Pencairan Hutang', 'PEMASUKAN'), 'keterangan' => $input['deskripsi']]);
                return response()->json(['status' => 'success']);
            });
        });
    }

    public function update(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'hutang.index', 'edit'), 403);
        $input = $this->documentInput($request, false);
        $debt = Hutang::findOrFail($id);
        return \App\Services\FinanceFiles::withUpload($request, 'lampiran', ['pemasukan'], function ($filename) use ($input, $id) {
            return DB::transaction(function () use ($input, $id, $filename) {
                $debt = Hutang::lockForUpdate()->findOrFail($id);
                if (Pengeluaran::where('id_hutang', $id)->where('tanggal', '<', $input['tanggal_hutang'])->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['tanggal_hutang' => 'Tanggal hutang tidak boleh setelah pembayaran yang sudah tercatat.']);
                }
                $debt->update($input + ['lampiran' => $filename]);
                app(\App\Services\DebtPayment::class)->sync($debt);
                Pemasukan::updateOrCreate(['id_hutang' => $id], ['id_bank' => $input['id_bank'], 'tanggal' => $input['tanggal_hutang'],
                    'nominal' => $input['nominal'], 'lampiran' => $filename, 'keterangan' => $input['deskripsi'],
                    'id_kategori_transaksi' => \App\Services\FinanceCategories::id('Pencairan Hutang', 'PEMASUKAN')]);
                return response()->json(['status' => 'success']);
            });
        }, $debt->lampiran);
    }

    public function destroy($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'hutang.index', 'hapus'), 403);
        $filename = DB::transaction(function () use ($id) {
            $debt = Hutang::lockForUpdate()->findOrFail($id);
            if ($debt->terbayar > 0 || Pengeluaran::where('id_hutang', $id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['nominal' => 'Hutang yang memiliki pembayaran tidak dapat dihapus.']);
            }
            Pemasukan::where('id_hutang', $id)->delete();
            $debt->delete();
            return $debt->lampiran;
        });
        \App\Services\FinanceFiles::remove(['pemasukan'], $filename);
        return response()->json(['status' => 'success']);
    }

    private function documentInput(Request $request, bool $creating): array
    {
        $request->merge(['tanggal' => $request->tanggal_hutang]);
        $input = app(\App\Services\ReceivablePayment::class)->validate($request);
        $request->validate(['deskripsi' => 'required|string|max:1000', 'lampiran' => ($creating ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,pdf,webp|max:2048']);
        return ['tanggal_hutang' => $input['tanggal'], 'nominal' => $input['nominal'], 'id_bank' => $input['id_bank'], 'deskripsi' => $request->deskripsi];
    }
    public function getSisaBayar($id)
    {
        $hutang = Hutang::find($id);

        if (! $hutang) {
            return response()->json(['sisa_bayar' => 0], 404);
        }

        return response()->json(['sisa_bayar' => $hutang->sisa_bayar]);
    }
}
