<?php
namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Bank;
use App\Models\MutasiSaldo;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class MutasiSaldoController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $permissions = HakAksesController::getUserPermissions();

        if ($request->ajax()) {
            $data = MutasiSaldo::orderBy('id', 'desc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('rekening_asal', function ($row) {
                    $bank = Bank::find($row->rekening_asal);
                    return $bank ? $bank->nama : 'Bank Tidak Diketahui';
                })
                ->addColumn('rekening_tujuan', function ($row) {
                    $bank = Bank::find($row->rekening_tujuan);
                    return $bank ? $bank->nama : 'Bank Tidak Diketahui';
                })
                ->addColumn('nominal', function ($row) {
                    return '
                    <div class="d-flex justify-content-between harga-format w-100">
                        <span>Rp.</span>
                        <span>' . number_format($row->nominal, 0, ',', '.') . '</span>
                    </div>';
                })
                ->addColumn('tanggal', function ($row) {
                    $tanggal = Carbon::parse($row->tanggal)->translatedFormat('d F Y');

                    return $tanggal;
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $editUrl   = route('mutasi-saldo.edit', $row->id);
                    $deleteUrl = route('mutasi-saldo.destroy', $row->id);

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
                    $btn .= '</div>';
                    return $btn;
                })

                ->rawColumns(['action', 'nominal'])
                ->make(true);
        }

        $bankList = Bank::all();

        return view('admin.keuangan.mutasi_saldo.index', compact('permissions', 'bankList'));
    }

    public function edit($id)
    {
        $list = MutasiSaldo::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $list,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'mutasi-saldo.index', 'tambah'), 403);
        $input = $this->transferInput($request, true);
        return \App\Services\FinanceFiles::withUpload($request, 'lampiran', ['mutasi_saldo', 'pemasukan', 'pengeluaran'], function ($filename) use ($input) {
            return DB::transaction(function () use ($input, $filename) {
                $transfer = MutasiSaldo::create($input + ['lampiran' => $filename]);
                $this->syncLegs($transfer);
                return response()->json(['status' => 'success']);
            });
        });
    }

    public function update(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'mutasi-saldo.index', 'edit'), 403);
        $input = $this->transferInput($request, false);
        $transfer = MutasiSaldo::findOrFail($id);
        return \App\Services\FinanceFiles::withUpload($request, 'lampiran', ['mutasi_saldo', 'pemasukan', 'pengeluaran'], function ($filename) use ($input, $id) {
            return DB::transaction(function () use ($input, $id, $filename) {
                $transfer = MutasiSaldo::lockForUpdate()->findOrFail($id);
                $transfer->update($input + ['lampiran' => $filename]);
                $this->syncLegs($transfer);
                return response()->json(['status' => 'success']);
            });
        }, $transfer->lampiran);
    }

    public function destroy($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'mutasi-saldo.index', 'hapus'), 403);
        $filename = DB::transaction(function () use ($id) {
            $transfer = MutasiSaldo::lockForUpdate()->findOrFail($id);
            Pemasukan::where('id_mutasi', $id)->delete();
            Pengeluaran::where('id_mutasi', $id)->delete();
            $transfer->delete();
            return $transfer->lampiran;
        });
        \App\Services\FinanceFiles::remove(['mutasi_saldo', 'pemasukan', 'pengeluaran'], $filename);
        return response()->json(['status' => 'success']);
    }

    private function transferInput(Request $request, bool $creating): array
    {
        $request->merge(['id_bank' => $request->rekening_asal]);
        $input = app(\App\Services\ReceivablePayment::class)->validate($request);
        $request->validate(['rekening_asal' => 'required|integer|exists:bank,id',
            'rekening_tujuan' => 'required|integer|exists:bank,id|different:rekening_asal',
            'lampiran' => ($creating ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,pdf,webp|max:2048']);
        return ['tanggal' => $input['tanggal'], 'nominal' => $input['nominal'], 'rekening_asal' => $request->rekening_asal,
            'rekening_tujuan' => $request->rekening_tujuan, 'keterangan' => $input['keterangan'] ?? ''];
    }

    private function syncLegs(MutasiSaldo $transfer): void
    {
        $common = ['tanggal' => $transfer->tanggal, 'nominal' => $transfer->nominal,
            'keterangan' => $transfer->keterangan ?? '', 'lampiran' => $transfer->lampiran ?? ''];
        Pemasukan::updateOrCreate(['id_mutasi' => $transfer->id], $common + ['id_bank' => $transfer->rekening_tujuan,
            'id_kategori_transaksi' => \App\Services\FinanceCategories::id('Mutasi Masuk', 'PEMASUKAN')]);
        Pengeluaran::updateOrCreate(['id_mutasi' => $transfer->id], $common + ['id_bank' => $transfer->rekening_asal,
            'id_kategori_transaksi' => \App\Services\FinanceCategories::id('Mutasi Keluar', 'PENGELUARAN')]);
    }
}