<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChangeApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\RecordActivity::class);
        Schema::create('role', function (Blueprint $t) { $t->id(); $t->string('role'); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('username'); $t->integer('id_role'); $t->string('status')->default('AKTIF'); });
        Schema::create('menu', function (Blueprint $t) { $t->id(); $t->string('route_name'); });
        Schema::create('hak_akses', function (Blueprint $t) {
            $t->id(); $t->integer('id_user'); $t->integer('id_menu');
            foreach (['lihat', 'edit', 'hapus', 'tambah'] as $field) $t->integer($field)->default(1);
        });
        Schema::create('bank', function (Blueprint $t) { $t->id(); $t->string('nama'); $t->string('no_rek'); $t->string('pemilik_rek'); });
        (require database_path('migrations/2026_10_21_000000_create_change_requests_table.php'))->up();
        DB::table('role')->insert([['id' => 2, 'role' => 'Admin'], ['id' => 4, 'role' => 'Super Admin']]);
        DB::table('users')->insert([['id' => 1, 'username' => 'admin', 'id_role' => 2], ['id' => 2, 'username' => 'super', 'id_role' => 4]]);
        DB::table('menu')->insert(['id' => 1, 'route_name' => 'bank.index']);
        DB::table('hak_akses')->insert(['id_user' => 1, 'id_menu' => 1]);
        DB::table('bank')->insert(['id' => 1, 'nama' => 'Lama', 'no_rek' => '123', 'pemilik_rek' => 'Pemilik']);
        $this->actingAs(User::find(1));
    }

    private function submitEdit(): void
    {
        $this->putJson(route('bank.update', 1), ['nama' => 'Baru', 'no_rek' => '123', 'pemilik_rek' => 'Pemilik', 'approval_reason' => 'Koreksi nama rekening'])
            ->assertStatus(202)->assertJsonPath('pending_approval', true);
    }

    public function test_edit_waits_for_approval_and_can_only_be_applied_once(): void
    {
        $this->submitEdit();
        $this->assertDatabaseHas('bank', ['nama' => 'Lama']);
        $this->assertStringNotContainsString('Koreksi', DB::table('change_requests')->value('payload'));
        $this->actingAs(User::find(2));
        $this->post(route('approvals.review', 1), ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('bank', ['nama' => 'Baru']);
        $this->assertDatabaseHas('change_requests', ['status' => 'approved', 'reviewer_id' => 2]);
        $this->post(route('approvals.review', 1), ['decision' => 'approved'])->assertStatus(409);
    }

    public function test_reason_is_required_and_admin_cannot_approve(): void
    {
        $this->deleteJson(route('bank.destroy', 1))->assertUnprocessable();
        $this->assertSame(0, ChangeRequest::count());
        $this->submitEdit();
        $this->postJson(route('approvals.review', 1), ['decision' => 'approved'])->assertForbidden();
        $this->get(route('approvals.index'))->assertForbidden();
        $this->assertDatabaseHas('bank', ['nama' => 'Lama']);
    }

    public function test_delete_is_queued_and_reason_script_renders(): void
    {
        $html = view('admin.approvals.reason-script')->render();
        $this->assertStringContainsString('const specialPosts', $html);
        $this->deleteJson(route('bank.destroy', 1), ['approval_reason' => 'Rekening sudah tidak dipakai'])->assertStatus(202);
        $this->assertDatabaseHas('bank', ['id' => 1]);
        $this->assertDatabaseHas('change_requests', ['action' => 'hapus', 'status' => 'pending']);
        $this->actingAs(User::find(2));
        $this->post(route('approvals.review', 1), ['decision' => 'rejected'])->assertRedirect();
        $this->assertDatabaseHas('bank', ['id' => 1]);
    }

    public function test_rejection_keeps_data_and_revoked_permissions_prevent_approval(): void
    {
        $this->submitEdit();
        $this->actingAs(User::find(2));
        $this->post(route('approvals.review', 1), ['decision' => 'rejected'])->assertRedirect();
        $this->assertDatabaseHas('bank', ['nama' => 'Lama']);
        $this->actingAs(User::find(1));
        $this->submitEdit();
        DB::table('hak_akses')->update(['edit' => 0]);
        $this->actingAs(User::find(2));
        $this->postJson(route('approvals.review', 2), ['decision' => 'approved'])->assertForbidden();
        $this->assertDatabaseHas('change_requests', ['id' => 2, 'status' => 'pending']);
        $this->assertDatabaseHas('bank', ['nama' => 'Lama']);
    }

    public function test_pending_status_covers_both_edit_and_delete_and_clears_after_review(): void
    {
        $this->submitEdit();
        $response = $this->getJson(route('approvals.statuses'))->assertOk()->assertJsonCount(1, 'data');
        $urls = $response->json('data.0.urls');
        $this->assertContains(route('bank.edit', 1, false), $urls);
        $this->assertContains(route('bank.destroy', 1, false), $urls);
        $this->assertArrayNotHasKey('payload', $response->json('data.0'));
        $this->deleteJson(route('bank.destroy', 1), ['approval_reason' => 'Hapus rekening yang sama'])
            ->assertStatus(202)->assertJsonPath('pending_approval', true);
        $this->assertSame(1, ChangeRequest::count());
        $this->actingAs(User::find(2));
        $this->getJson(route('approvals.statuses'))->assertJsonCount(1, 'data');
        $this->post(route('approvals.review', 1), ['decision' => 'rejected'])->assertRedirect();
        $this->getJson(route('approvals.statuses'))->assertJsonCount(0, 'data');
        $this->actingAs(User::find(1));
        $this->submitEdit();
        $this->actingAs(User::find(2));
        $this->post(route('approvals.review', 2), ['decision' => 'approved'])->assertRedirect();
        $this->getJson(route('approvals.statuses'))->assertJsonCount(0, 'data');
    }

    public function test_pending_status_does_not_expose_other_menus_or_hide_other_records(): void
    {
        $this->submitEdit();
        DB::table('bank')->insert(['id' => 3, 'nama' => 'Lain', 'no_rek' => '456', 'pemilik_rek' => 'Pemilik']);
        $this->putJson(route('bank.update', 3), ['nama' => 'Lain baru', 'approval_reason' => 'Perbaiki rekening lain'])->assertStatus(202);
        $this->getJson(route('approvals.statuses'))->assertJsonCount(2, 'data');
        DB::table('hak_akses')->update(['lihat' => 0]);
        $this->getJson(route('approvals.statuses'))->assertOk()->assertJsonCount(0, 'data');
    }
}
