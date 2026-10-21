<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalSettings
{
    public static function menus()
    {
        return Menu::whereIn('route_name', array_values(MenuAccess::menus()))
            ->where(fn ($q) => $q->where('edit', 1)->orWhere('hapus', 1))
            ->orderBy('id_parent')->orderBy('urutan')->get();
    }

    public static function rules(): array
    {
        // Preserve existing behavior until the migration is installed.
        if (!Schema::hasTable('approval_settings')) return [];
        return DB::table('approval_settings')->get()->keyBy('menu')->map(fn ($row) => [
            'edit' => (bool) $row->edit, 'hapus' => (bool) $row->hapus,
        ])->all();
    }

    public static function required(?string $menu, string $ability): bool
    {
        if (!$menu || !in_array($ability, ['edit', 'hapus'], true)) return false;
        if (!Schema::hasTable('approval_settings')) return true;
        return (bool) (self::rules()[$menu][$ability] ?? false);
    }
}
