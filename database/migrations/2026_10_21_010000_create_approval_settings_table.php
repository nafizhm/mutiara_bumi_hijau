<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->string('menu')->primary();
            $table->boolean('edit')->default(false);
            $table->boolean('hapus')->default(false);
        });
        foreach (\App\Services\ApprovalSettings::menus() as $menu) {
            DB::table('approval_settings')->updateOrInsert(['menu' => $menu->route_name], [
                'edit' => (bool) $menu->edit, 'hapus' => (bool) $menu->hapus,
            ]);
        }
    }

    public function down(): void { Schema::dropIfExists('approval_settings'); }
};
