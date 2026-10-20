<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\KavlingPeta;
use App\Models\ProspekNasabah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ProspekController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $program = ProspekNasabah::with('marketing');

            $permissions = HakAksesController::getUserPermissions();

            return DataTables::of($program)
                ->addIndexColumn()
                ->editColumn('nama_lengkap', function ($row) {
                    return '
                    <div>
                        <p>' . e($row->nama_lengkap) . '</p>
                        <p class="badge bg-info">' . e($row->marketing->nama_marketing) . '</p>
                    </div>
                ';
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $editUrl = route('prospek.edit', $row->id);
                    $deleteUrl = route('prospek.destroy', $row->id);

                    $actions = '<div class="text-center">';

                    if ($permissions['edit']) {
                        $actions .= '<button class="btn btn-primary btn-sm edit-button" data-id="' . e($row->id) . '"
                            data-url="' . e($editUrl) . '" data-toggle="modal" data-target="#modalForm">
                            Edit
                        </button>';
                    }

                    if ($permissions['hapus']) {
                        $actions .= '
                        <form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">
                            ' . csrf_field() . method_field('DELETE') . '
                            <button type="submit" class="delete-button btn btn-danger btn-sm ms-1">
                                Hapus
                            </button>
                        </form>';
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['action', 'nama_lengkap'])
                ->make(true);
        }

        $permissions = HakAksesController::getUserPermissions();

        return view('admin.customer.prospek.index', compact('permissions'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'penghasilan' => str_replace('.', '', $request->penghasilan)
        ]);

        $rules = [
            'tgl_terima'         => 'required',
            'nama_lengkap'       => 'required|string|max:20',
            'no_wa'              => 'required|numeric',
            'usia'               => 'required',
            'pekerjaan'          => 'required',
            'penghasilan'        => 'required|numeric',
            'sumber_informasi'   => 'required',
            'rangking'           => 'required',
            'id_marketing'       => 'required|exists:marketing,id',
            'keterangan_belum'   => 'required',
        ];

        $messages = [
            'tgl_terima.required'         => 'Tanggal wajib diisi.',
            'nama_lengkap.required'       => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'            => 'Nama lengkap maksimal 20 karakter.',
            'no_wa.required'              => 'Nomor telepon wajib diisi.',
            'usia.required'               => 'Usia wajib dipilih.',
            'pekerjaan.required'          => 'Pekerjaan wajib diisi.',
            'penghasilan.required'        => 'Penghasilan wajib diisi.',
            'penghasilan.numeric'         => 'penghasilan harus berupa angka.',
            'sumber_informasi.required'   => 'Sumber informasi wajib diisi.',
            'rangking.required'           => 'Rangking wajib dipilih.',
            'id_marketing.required'       => 'Marketing wajib dipilih.',
            'keterangan_belum.required'   => 'Keterangan wajib diisi.',
        ];

        $request->validate($rules, $messages);

        DB::beginTransaction();

        try {
            ProspekNasabah::create([
                'tgl_terima' => $request->tgl_terima,
                'nama_lengkap' => $request->nama_lengkap,
                'no_wa' => $request->no_wa,
                'no_telp' => $request->no_wa,
                'usia' => $request->usia,
                'pekerjaan' => $request->pekerjaan,
                'penghasilan' => $request->penghasilan,
                'sumber_informasi' => $request->sumber_informasi,
                'rangking' => $request->rangking,
                'id_marketing' => $request->id_marketing,
                'id_freelance' => $request->id_freelance,
                'keterangan_belum' => $request->keterangan_belum,
                'no_ktp' => '',
                'email' => '',
                'stt_delete' => '0',
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        $list = ProspekNasabah::with('marketing')->find($id);

        if (!$list) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $list,
            'message' => 'Data ditemukan'
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = ProspekNasabah::find($id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $request->merge([
            'penghasilan' => str_replace('.', '', $request->penghasilan)
        ]);

        $request->validate([
            'tgl_terima' => 'required',
            'nama_lengkap' => 'required|string|max:20',
            'no_wa' => 'required',
            'usia' => 'required',
            'pekerjaan' => 'required',
            'penghasilan' => 'required',
            'sumber_informasi' => 'required',
            'rangking' => 'required',
            'id_marketing' => 'required',
            'keterangan_belum' => 'required',
        ], [
            'tgl_terima.required'         => 'Tanggal wajib diisi.',
            'nama_lengkap.required'       => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'            => 'Nama lengkap maksimal 20 karakter.',
            'no_wa.required'              => 'Nomor telepon wajib diisi.',
            'usia.required'               => 'Usia wajib dipilih.',
            'pekerjaan.required'          => 'Pekerjaan wajib diisi.',
            'penghasilan.required'        => 'Penghasilan wajib diisi.',
            'sumber_informasi.required'   => 'Sumber informasi wajib diisi.',
            'rangking.required'           => 'Rangking wajib dipilih.',
            'id_marketing.required'       => 'Marketing wajib dipilih.',
            'keterangan_belum.required'   => 'Keterangan wajib diisi.',
        ]);

        DB::beginTransaction();
        try {
            $data->update([
                'tgl_terima' => $request->tgl_terima,
                'nama_lengkap' => $request->nama_lengkap,
                'no_wa' => $request->no_wa,
                'no_telp' => $request->no_wa,
                'usia' => $request->usia,
                'pekerjaan' => $request->pekerjaan,
                'penghasilan' => $request->penghasilan,
                'sumber_informasi' => $request->sumber_informasi,
                'rangking' => $request->rangking,
                'id_marketing' => $request->id_marketing,
                'id_freelance' => $request->id_freelance,
                'keterangan_belum' => $request->keterangan_belum,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diubah'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getKavling($idLokasi)
    {
        $data = KavlingPeta::where('id_lokasi', $idLokasi)
            ->select('id_kavling', 'kode_kavling', 'hrg_jual')
            ->get();

        return response()->json($data);
    }

    public function getHargaKavling($id_kavling)
    {
        $data = KavlingPeta::findOrFail($id_kavling);
        $formatted = 'Rp. ' . number_format($data->hrg_jual, 0, ',', '.');

        return response()->json([
            'hrg_jual' => $data->hrg_jual,
            'formatted' => $formatted
        ]);
    }

    public function destroy($id)
    {
        $data = ProspekNasabah::findOrFail($id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $data->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Terjadi Kesalahan', 'error' => $e->getMessage()], 500);
        }
    }
}
