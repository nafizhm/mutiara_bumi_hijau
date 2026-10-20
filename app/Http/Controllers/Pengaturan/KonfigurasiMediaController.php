<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\KonfigurasiMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class KonfigurasiMediaController extends Controller
{
    public function index(Request $request)
    {
        $permissions = HakAksesController::getUserPermissions();

        if ($request->ajax()) {
            $data = KonfigurasiMedia::query();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('gambar', function ($row) {
                    $url = asset('config_media/' . $row->nama_file);

                    return '<img src="' . $url . '" class="img-fluid" style="max-width:100px; max-height:50px;">';
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $editUrl   = route('pengaturan-media.edit', $row->id);
                    $fileUrl = asset('config_media/' . $row->nama_file);

                    $previewBtn = '<button class="btn btn-sm btn-warning preview-button"
                        data-id="' . $row->id . '"
                        data-file="' . $fileUrl . '"
                        data-nama="' . $row->nama_file . '"
                        data-bs-toggle="modal"
                        data-bs-target="#previewModal">
                        Preview
                    </button>';

                    $uploadBtn = '';
                    if (isset($permissions['edit']) && $permissions['edit'] == 1) {
                        $uploadBtn = '<button class="btn btn-sm btn-primary edit-button"
                            data-id="' . e($row->id) . '"
                            data-url="' . e($editUrl) . '"
                            data-bs-toggle="modal"
                            data-bs-target="#modalForm">
                            Update
                        </button>';
                    }

                    return $previewBtn . ' ' . $uploadBtn;
                })
                ->rawColumns(['gambar', 'action'])
                ->make(true);
        }

        $data = KonfigurasiMedia::first();
        $permissions = HakAksesController::getUserPermissions();

        return view('admin.pengaturan.pengaturan_media.index', compact('data', 'permissions'));
    }

    public function edit($id)
    {
        $konfigurasiMedia = KonfigurasiMedia::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $konfigurasiMedia
        ], 200);
    }

    public function update(Request $request)
    {
        $id = $request->input('id');
        $konfigurasiMedia = KonfigurasiMedia::findOrFail($id);

        if (!$konfigurasiMedia) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $request->validate(
            [
                'nama_file' => 'required|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:3072|dimensions:max_width=6000,max_height=6000',
            ],
            [
                'nama_file.required' => 'Foto wajib diisi',
                'nama_file.image' => 'Format gambar tidak didukung',
                'nama_file.max' => 'Ukuran gambar maksimal 3MB',
            ]
        );

        $file = $request->file('nama_file');
        $bytes = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            throw \Illuminate\Validation\ValidationException::withMessages(['nama_file' => 'Gambar tidak dapat dibaca.']);
        }
        $directory = public_path('config_media');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory, 0755);
        $filename = Str::uuid().'.webp';
        $path = $directory.'/'.$filename;
        $oldName = $konfigurasiMedia->nama_file;
        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            if (!imagewebp($image, $path, 85)) {
                throw new \RuntimeException('Gagal menyimpan gambar.');
            }
            DB::transaction(fn () => $konfigurasiMedia->update([
                'nama_file' => $filename,
                'jenis_data' => $konfigurasiMedia->jenis_data === 'kwitansi' ? 'background login' : $konfigurasiMedia->jenis_data,
            ]));
        } catch (\Throwable $exception) {
            if (is_file($path)) unlink($path);
            report($exception);
            return response()->json(['success' => false, 'message' => 'Upload gagal. Silakan coba lagi.'], 500);
        } finally {
            imagedestroy($image);
        }
        if ($oldName && basename($oldName) === $oldName && is_file($directory.'/'.$oldName)) {
            unlink($directory.'/'.$oldName);
        }
        return response()->json(['success' => true, 'message' => 'Media berhasil diperbarui.']);
    }
}
