<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Customer;
use App\Models\UploadFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadFileController extends Controller
{
    public function index()
    {
        $data = UploadFile::first();
        $permissions = HakAksesController::getUserPermissions();

        return view('admin.customer.upload_file.index', compact('data', 'permissions'));
    }

    public function getFileNasabah($id)
    {
        $files = UploadFile::where('id_customer', $id)->get()->map(function ($file) {
            $file->download_url = route('nasabah.file.download', $file->id);
            return $file;
        });

        if ($files->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Data tidak ditemukan',
                'data' => []
            ], 200);
        }

        return response()->json(
            [
                'success' => true,
                'message' => 'Data ditemukan',
                'data'    => $files
            ],
            200
        );
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'id_customer' => 'required|exists:customer,id',
            'tanggal'    => 'required|date',
            'nama_file'  => 'required|string|max:255',
            'keterangan' => 'nullable|string|max:2000',
            'lampiran'   => 'required|file|mimes:jpg,jpeg,png,webp,pdf|extensions:jpg,jpeg,png,webp,pdf|max:10240',
        ], [
            'id_customer.required'  => 'Customer wajib diisi.',
            'id_customer.exists'    => 'Customer tidak ditemukan.',
            'tanggal.required'      => 'Tanggal wajib diisi.',
            'tanggal.date'          => 'Tanggal tidak valid.',
            'nama_file.required'    => 'Nama file wajib diisi.',
            'lampiran.required'     => 'Lampiran wajib diunggah.',
            'lampiran.file'         => 'Lampiran harus berupa file.',
            'lampiran.mimes'        => 'Hanya mendukung jpg, jpeg, png, webp, atau pdf.',
        ]);

        DB::beginTransaction();

        try {
            $folder = 'berkas_user';
            $file = $request->file('lampiran');
            $path = $file->store($folder, 'local');
            if (!$path) {
                throw new \RuntimeException('Gagal menyimpan lampiran.');
            }
            $finalName = basename($path);

            UploadFile::create([
                'tanggal'     => $request->tanggal,
                'id_customer' => $request->id_customer,
                'folder'      => $folder,
                'nama_file'   => $request->nama_file,
                'keterangan'  => $request->keterangan,
                'lampiran'    => $finalName,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data Berhasil Disimpan!'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($path) && $path) {
                Storage::disk('local')->delete($path);
            }
            report($e);
            return response()->json(['success' => false, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function downloadFile($id)
    {
        $file = UploadFile::findOrFail($id);
        abort_unless($file->folder === 'berkas_user' && basename($file->lampiran) === $file->lampiran, 404);
        $path = $file->folder.'/'.$file->lampiran;
        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $legacy = public_path($path);
        abort_unless(is_file($legacy), 404);
        return response()->file($legacy, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function deleteFile($id)
    {
        $file = UploadFile::find($id);

        if (!$file) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan!'], 404);
        }

        DB::beginTransaction();
        try {
            abort_unless($file->folder === 'berkas_user' && basename($file->lampiran) === $file->lampiran, 404);
            Storage::disk('local')->delete($file->folder.'/'.$file->lampiran);
            $legacy = public_path($file->folder.'/'.$file->lampiran);
            if (is_file($legacy)) {
                unlink($legacy);
            }
            $file->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Data Berhasil Dihapus!'], 200);
    }

    public function getNasabah(Request $request)
    {
        $search = $request->get('q');
        $nasabah = Customer::where('stt_arsip', 0)->where(function ($q) use ($search) {
            $q->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('kode_customer', 'like', "%$search%");
        })->select('id', 'nama_lengkap', 'kode_customer')
            ->get();

        if (!$nasabah) {
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Data tidak ditemukan',
                    'data'    => []
                ],
                200
            );
        }

        return response()->json(
            [
                'success' => true,
                'message' => 'Data ditemukan',
                'data'    => $nasabah
            ],
            200
        );
    }

    public function getNasabahDetails($id)
    {
        $nasabah = Customer::with(['lokasiKavling', 'kavling'])
            ->where('id', $id)
            ->first();

        if (!$nasabah) {
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Data tidak ditemukan',
                    'data'    => []
                ]
            );
        }

        $data = [
            'no_ktp' => $nasabah->no_ktp,
            'lokasi_perumahan' => $nasabah->lokasiKavling ? $nasabah->lokasiKavling->nama_kavling : null,
            'no_telp' => $nasabah->no_wa,
            'lokasi_kav_blok' => $nasabah->kavlingPeta ? $nasabah->kavlingPeta->kode_kavling : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Data ditemukan',
            'data' => $data
        ]);
    }
}
