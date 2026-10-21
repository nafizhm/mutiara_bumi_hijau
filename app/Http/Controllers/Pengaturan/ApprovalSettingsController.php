<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Services\ApprovalSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalSettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canApproveChanges(), 403);
        return view('admin.pengaturan.approval_settings', [
            'menus' => ApprovalSettings::menus(), 'rules' => ApprovalSettings::rules(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->canApproveChanges(), 403);
        $data = $request->validate([
            'settings' => 'sometimes|array', 'settings.*' => 'array:edit,hapus',
            'settings.*.edit' => 'sometimes|boolean', 'settings.*.hapus' => 'sometimes|boolean',
        ], [
            'settings.array' => 'Format pengaturan persetujuan tidak valid. Muat ulang halaman lalu coba kembali.',
            'settings.*.array' => 'Format pilihan menu tidak valid. Muat ulang halaman lalu coba kembali.',
            'settings.*.edit.boolean' => 'Pilihan persetujuan edit tidak valid.',
            'settings.*.hapus.boolean' => 'Pilihan persetujuan hapus tidak valid.',
        ]);
        DB::transaction(function () use ($data) {
            foreach (ApprovalSettings::menus() as $menu) {
                $selected = $data['settings'][$menu->id] ?? [];
                DB::table('approval_settings')->updateOrInsert(['menu' => $menu->route_name], [
                    'edit' => $menu->edit && !empty($selected['edit']),
                    'hapus' => $menu->hapus && !empty($selected['hapus']),
                ]);
            }
        });
        return redirect()->route('approval-settings.index')->with('success', 'Pengaturan persetujuan berhasil disimpan.');
    }
}
