<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username');
            $table->string('action', 15)->index();
            $table->string('table_name')->nullable();
            $table->string('record_reference')->nullable();
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('operations')->default(1);
            $table->timestamp('created_at')->index();
        });
        $menuId = DB::table('menu')->insertGetId([
            'id_parent' => 0, 'title' => 'Log Aktivitas', 'route_name' => 'activity-logs.index',
            'icon' => 'bi bi-clock-history', 'urutan' => 10, 'lihat' => 1,
            'tambah' => 0, 'edit' => 0, 'hapus' => 0,
        ]);
        // Initially grant access only to accounts that can manage users.
        $usersMenu = DB::table('menu')->where('route_name', 'pengguna.index')->value('id');
        $userIds = DB::table('hak_akses')->where('id_menu', $usersMenu)->where('lihat', 1)->pluck('id_user')->unique();
        foreach ($userIds as $userId) {
            DB::table('hak_akses')->insert([
                'id_user' => $userId, 'id_menu' => $menuId, 'lihat' => 1,
                'beranda' => 0, 'tambah' => 0, 'edit' => 0, 'hapus' => 0,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('menu')->where('route_name', 'activity-logs.index')->pluck('id');
        DB::table('hak_akses')->whereIn('id_menu', $ids)->delete();
        DB::table('role_user')->whereIn('id_menu', $ids)->delete();
        DB::table('menu')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('activity_logs');
    }
};
