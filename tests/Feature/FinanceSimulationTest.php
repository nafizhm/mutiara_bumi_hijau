<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashFlow;
use App\Services\FinanceAudit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FinanceSimulationTest extends TestCase
{
    private string $testPublic;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);
        DB::purge('sqlite');
        $this->testPublic=storage_path('framework/testing/finance-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->testPublic);
        $this->app->usePublicPath($this->testPublic);
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('username');$t->string('password');});
        Schema::create('menu',function(Blueprint $t){$t->id();$t->string('route_name');$t->string('title')->default('');$t->string('icon')->default('');foreach(['id_parent','urutan','lihat','tambah','edit','hapus'] as $c)$t->integer($c)->default(0);});
        Schema::create('hak_akses',function(Blueprint $t){$t->id();$t->integer('id_user');$t->integer('id_menu');foreach(['lihat','beranda','tambah','edit','hapus'] as $c)$t->integer($c)->default(1);});
        Schema::create('bank',function(Blueprint $t){$t->id();$t->string('nama');$t->string('no_rek')->nullable();$t->string('pemilik_rek')->nullable();});
        Schema::create('kategori_transaksi',function(Blueprint $t){$t->id();$t->string('kode')->default('');$t->string('kategori');$t->string('jenis_kategori');$t->integer('stt_fix')->default(0);});
        foreach(['hutang','piutang'] as $table) Schema::create($table,function(Blueprint $t)use($table){
            $t->id();$t->integer('id_bank');$t->date('tanggal_'.$table);$t->string('deskripsi');$t->string('lampiran')->nullable();$t->date('tgl_pelunasan')->nullable();
            foreach(['nominal','terbayar','sisa_bayar'] as $c)$t->integer($c)->default(0);$t->integer('status')->default(1);
            if($table==='piutang'){$t->integer('id_customer')->nullable();$t->integer('id_kategori_transaksi')->nullable();}
        });
        foreach(['pemasukan','pengeluaran'] as $table) Schema::create($table,function(Blueprint $t)use($table){
            $t->id();$t->date('tanggal');$t->integer('id_bank');$t->integer('nominal');$t->integer('id_kategori_transaksi');
            $t->string('keterangan')->default('');$t->string('lampiran')->default('');
            foreach(['id_hutang','id_piutang','id_mutasi'] as $c)$t->integer($c)->nullable();
            if($table==='pemasukan'){foreach(['id_customer','id_lokasi','id_metode'] as $c)$t->integer($c)->nullable();$t->string('no_kwitansi')->nullable();}
            else foreach(['id_po','id_proyek_bangunan_detail','id_proyek_jalan_detail','id_proyek_saluran_detail'] as $c)$t->integer($c)->default(0);
        });
        Schema::create('mutasi_saldo',function(Blueprint $t){$t->id();$t->date('tanggal');foreach(['rekening_asal','rekening_tujuan','nominal'] as $c)$t->integer($c);$t->string('lampiran')->nullable();$t->string('keterangan')->nullable();});
        Schema::create('customer',function(Blueprint $t){
            $t->id();foreach(['kode_customer','nama_lengkap','no_ktp','no_ktp_p','tempat_lahir','no_telp','no_wa','jenis_kelamin','email','npwp','alamat','alamat_domisili','pekerjaan','ket_cashback','jenis_pembelian'] as $c)$t->string($c)->nullable();
            foreach(['id_lokasi','id_status_progres','id_marketing','id_bank','discount','pembayaran_booking','dp_kredit','inhouse_tenor','inhouse_perbulan'] as $c)$t->integer($c)->nullable();
            foreach(['tgl_lahir','tgl_batas_booking','inhouse_jatuh_tempo'] as $c)$t->date($c)->nullable();$t->integer('stt_arsip')->default(0);
        });
        Schema::create('lokasi_kavling',function(Blueprint $t){$t->id();$t->string('nama_kavling');$t->string('nama_singkat');});
        Schema::create('kavling_peta',function(Blueprint $t){$t->id();$t->integer('id_lokasi');$t->string('kode_kavling');$t->integer('hrg_jual');});
        Schema::create('transaksi_kavling',function(Blueprint $t){$t->id();$t->integer('id_customer');$t->integer('id_kavling');$t->date('tgl_terima');$t->integer('hrg_rumah');});
        Schema::create('persyaratan_legal',function(Blueprint $t){$t->id();$t->integer('id_customer');});
        Schema::create('metode_bayar',function(Blueprint $t){$t->id();$t->string('jenis_bayar');});
        DB::table('users')->insert(['id'=>1,'username'=>'finance-tester','password'=>bcrypt('password')]);
        foreach(['piutang.index','pemasukan.index','pengeluaran.index','hutang.index','mutasi-saldo.index','pembayaran.index','bank.index','kategori-transaksi.index','customer.index','laporan-arus-kas.index'] as $i=>$route){DB::table('menu')->insert(['id'=>$i+1,'route_name'=>$route]);DB::table('hak_akses')->insert(['id_user'=>1,'id_menu'=>$i+1]);}
        (require database_path('migrations/2026_10_20_000000_create_activity_logs_table.php'))->up();
        DB::table('bank')->insert([['id'=>9,'nama'=>'Bank A'],['id'=>10,'nama'=>'Bank B']]);
        foreach([[25,'Booking','PEMASUKAN'],[26,'DP','PEMASUKAN'],[27,'Pelunasan','PEMASUKAN'],[28,'Cicilan','PEMASUKAN'],[30,'Administrasi','PEMASUKAN'],[102,'Tagihan Lain','PEMASUKAN'],[44,'Pendapatan Lain','PEMASUKAN'],[83,'Gaji','PENGELUARAN'],[37,'Pencairan Hutang','PEMASUKAN'],[103,'Pembayaran Piutang','PEMASUKAN'],[104,'Pemberian Piutang','PENGELUARAN'],[105,'Pembayaran Hutang','PENGELUARAN'],[106,'Mutasi Masuk','PEMASUKAN'],[107,'Mutasi Keluar','PENGELUARAN']] as [$id,$label,$type])DB::table('kategori_transaksi')->insert(['id'=>$id,'kategori'=>$label,'jenis_kategori'=>$type]);
        DB::table('lokasi_kavling')->insert(['id'=>1,'nama_kavling'=>'Lokasi Uji','nama_singkat'=>'UJI']);
        DB::table('metode_bayar')->insert(['id'=>2,'jenis_bayar'=>'Transfer']);
        for($i=1;$i<=6;$i++)DB::table('kavling_peta')->insert(['id'=>$i,'id_lokasi'=>1,'kode_kavling'=>'A-'.$i,'hrg_jual'=>1000000]);
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        if(isset($this->testPublic) && is_dir($this->testPublic)
            && str_starts_with(realpath($this->testPublic),realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR)
            && str_starts_with(basename($this->testPublic),'finance-')) File::deleteDirectory($this->testPublic);
        parent::tearDown();
    }

    private function input(int $amount,int $category,int $bank=9,string $date='2026-01-10'):array
    { return ['tanggal'=>$date,'nominal'=>$amount,'id_kategori_transaksi'=>$category,'id_bank'=>$bank,'keterangan'=>'Simulasi keuangan']; }

    private function loan(string $type,int $amount=500000):int
    {
        $this->post('/admin/keuangan/'.$type,['tanggal_'.$type=>'2026-01-03','nominal'=>$amount,'id_bank'=>9,'deskripsi'=>'Dokumen simulasi','lampiran'=>UploadedFile::fake()->create('bukti.pdf',1,'application/pdf')])->assertOk();
        return (int)DB::table($type)->max('id');
    }

    private function assertBalanced(int $expected):void
    {
        $flow=app(CashFlow::class);
        $total=$flow->report(2026,0,0);
        $this->assertSame($expected,$total['saldo_akhir']);
        $this->assertSame($total['saldo_awal']+$total['total_pemasukan']-$total['total_pengeluaran'],$total['saldo_akhir']);
        $this->assertSame($expected,$flow->report(2026,0,9)['saldo_akhir']+$flow->report(2026,0,10)['saldo_akhir']);
        $this->assertSame([],app(FinanceAudit::class)->issues());
    }

    public function test_full_finance_lifecycle_remains_balanced_across_all_menus():void
    {
        $this->postJson('/admin/keuangan/pemasukan',$this->input(1000000,44))->assertOk();
        $income=(int)DB::table('pemasukan')->max('id');
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(100000,83))->assertOk();
        $ordinary=(int)DB::table('pengeluaran')->max('id');
        $debt=$this->loan('hutang');
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(100000,105)+['id_hutang'=>$debt])->assertOk();
        $debtPayment=(int)DB::table('pengeluaran')->max('id');
        $receivable=$this->loan('piutang',200000);
        $this->postJson('/admin/keuangan/piutang/'.$receivable.'/bayar',$this->input(50000,103))->assertOk();
        $receipt=(int)DB::table('pemasukan')->max('id');
        $this->post('/admin/keuangan/mutasi-saldo',['tanggal'=>'2026-01-11','rekening_asal'=>9,'rekening_tujuan'=>10,'nominal'=>300000,'lampiran'=>UploadedFile::fake()->create('mutasi.pdf',1,'application/pdf')])->assertOk();
        $transfer=(int)DB::table('mutasi_saldo')->max('id');
        $this->assertBalanced(1150000);
        $this->assertSame(850000,app(CashFlow::class)->report(2026,0,9)['saldo_akhir']);
        $this->putJson('/admin/keuangan/hutang/'.$debt,['tanggal_hutang'=>'2026-01-03','nominal'=>700000,'id_bank'=>9,'deskripsi'=>'Hutang diubah'])->assertOk();
        $this->assertDatabaseHas('hutang',['id'=>$debt,'terbayar'=>100000,'sisa_bayar'=>600000]);
        $this->putJson('/admin/keuangan/pengeluaran/'.$debtPayment,$this->input(150000,105))->assertOk();
        $this->putJson('/admin/keuangan/pemasukan/'.$receipt,$this->input(75000,103))->assertOk();
        $this->putJson('/admin/keuangan/mutasi-saldo/'.$transfer,['tanggal'=>'2026-01-11','rekening_asal'=>9,'rekening_tujuan'=>10,'nominal'=>200000])->assertOk();
        $this->assertBalanced(1325000);
        $this->assertSame(1125000,app(CashFlow::class)->report(2026,0,9)['saldo_akhir']);
        $this->deleteJson('/admin/keuangan/pengeluaran/'.$debtPayment)->assertOk();
        $this->deleteJson('/admin/keuangan/pemasukan/'.$receipt)->assertOk();
        $this->deleteJson('/admin/keuangan/mutasi-saldo/'.$transfer)->assertOk();
        $this->assertBalanced(1400000);
        $this->deleteJson('/admin/keuangan/hutang/'.$debt)->assertOk();
        $this->deleteJson('/admin/keuangan/piutang/'.$receivable)->assertOk();
        $this->assertBalanced(900000);
        $this->deleteJson('/admin/keuangan/pemasukan/'.$income)->assertOk();
        $this->deleteJson('/admin/keuangan/pengeluaran/'.$ordinary)->assertOk();
        $this->assertBalanced(0);
    }

    public function test_debt_settlement_and_invalid_edits_do_not_corrupt_payments():void
    {
        $debt=$this->loan('hutang');
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(100000,105)+['id_hutang'=>$debt])->assertOk();
        $first=(int)DB::table('pengeluaran')->max('id');
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(400000,105,10,'2026-01-12')+['id_hutang'=>$debt])->assertOk();
        $this->assertDatabaseHas('hutang',['id'=>$debt,'status'=>2,'terbayar'=>500000,'sisa_bayar'=>0,'tgl_pelunasan'=>'2026-01-12']);
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(1,105)+['id_hutang'=>$debt])->assertUnprocessable();
        $this->putJson('/admin/keuangan/hutang/'.$debt,['tanggal_hutang'=>'2026-01-03','nominal'=>499999,'id_bank'=>9,'deskripsi'=>'Tidak valid'])->assertUnprocessable();
        $this->deleteJson('/admin/keuangan/hutang/'.$debt)->assertUnprocessable();
        $this->deleteJson('/admin/keuangan/pengeluaran/'.$first)->assertOk();
        $this->assertDatabaseHas('hutang',['id'=>$debt,'status'=>1,'terbayar'=>400000,'sisa_bayar'=>100000,'tgl_pelunasan'=>null]);
        $this->assertBalanced(100000);
    }

    public function test_failed_transfer_rolls_back_both_legs_and_removes_new_files():void
    {
        $this->withoutExceptionHandling();
        DB::listen(function($event){if(str_starts_with(strtolower($event->sql),'insert into "pengeluaran"'))throw new \RuntimeException('Simulated second-leg failure');});
        try{
            $this->post('/admin/keuangan/mutasi-saldo',['tanggal'=>'2026-01-11','rekening_asal'=>9,'rekening_tujuan'=>10,'nominal'=>300000,'lampiran'=>UploadedFile::fake()->create('mutasi.pdf',1,'application/pdf')]);
            $this->fail('Expected transfer failure');
        }catch(\RuntimeException $e){$this->assertSame('Simulated second-leg failure',$e->getMessage());}
        $this->assertSame(0,DB::transactionLevel());
        $this->assertSame(0,DB::table('mutasi_saldo')->count());
        $this->assertSame(0,DB::table('pemasukan')->count());
        $this->assertSame(0,DB::table('pengeluaran')->count());
        $this->assertCount(0,File::allFiles($this->testPublic));
    }

    public function test_cashflow_filters_opening_balance_and_exports_use_same_ledger():void
    {
        $this->postJson('/admin/keuangan/pemasukan',$this->input(200000,44,9,'2025-12-31'))->assertOk();
        $this->postJson('/admin/keuangan/pemasukan',$this->input(100000,44))->assertOk();
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(50000,83))->assertOk();
        $report=app(CashFlow::class)->report(2026,1,9);
        $this->assertSame(200000,$report['saldo_awal']);
        $this->assertSame(250000,$report['saldo_akhir']);
        $this->assertSame([300000,250000],$report['rows']->pluck('saldo_value')->all());
        $query='?tahun=2026&bulan=1&rekening=9';
        $this->getJson('/admin/keuangan/laporan-arus-kas/filter'.$query,['X-Requested-With'=>'XMLHttpRequest'])->assertOk()->assertJsonPath('saldo_awal',200000)->assertJsonPath('saldo_akhir',250000)->assertJsonPath('total_pemasukan',100000)->assertJsonPath('total_pengeluaran',50000);
        $empty=app(CashFlow::class)->report(2026,2,9);
        $this->assertSame(250000,$empty['saldo_awal']);$this->assertSame(250000,$empty['saldo_akhir']);
        $pdf=$this->get('/admin/keuangan/laporan-arus-kas/export-pdf'.$query)->assertOk();
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        $excel=$this->get('/admin/keuangan/laporan-arus-kas/export-excel'.$query)->assertOk();
        $path=$this->testPublic.'/cashflow.xlsx';file_put_contents($path,$excel->streamedContent());
        $sheet=IOFactory::load($path)->getActiveSheet();
        $this->assertSame('Saldo Awal',$sheet->getCell('D8')->getValue());
        $this->assertSame(200000,$sheet->getCell('G8')->getValue());
        $this->assertSame('Saldo Akhir',$sheet->getCell('D11')->getValue());
        $this->assertSame(250000,$sheet->getCell('G11')->getValue());
    }

    private function customerInput(int $lot,string $method):array
    {
        return ['tgl_terima'=>'2026-01-03','nama_lengkap'=>'Customer Uji '.$lot,'no_ktp'=>'000000000000000'.$lot,
            'tempat_lahir'=>'Kota Uji','tgl_lahir'=>'1990-01-01','no_telp'=>'0000000000'.$lot,'alamat'=>'Alamat Uji','email'=>'test'.$lot.'@example.com',
            'id_lokasi'=>1,'id_kavling'=>[$lot],'id_status_progres'=>1,'id_bank'=>9,'hrg_jual'=>1000000,'jenis_pembelian'=>$method,'discount'=>0,
            'pembayaran_booking'=>100000,'tgl_batas_booking'=>'2026-02-01','dp_kredit'=>200000,'inhouse_tenor'=>10,'inhouse_perbulan'=>80000,'inhouse_jatuh_tempo'=>'2026-02-03'];
    }

    public function test_booking_credit_and_cash_purchases_reconcile_with_sales_payments():void
    {
        foreach(['Booking','Kredit','Cash Keras'] as $i=>$method){
            $this->postJson('/admin/customer/customer',$this->customerInput($i+1,$method))->assertOk()->assertJsonPath('success',true);
            $id=(int)DB::table('customer')->max('id');$bill=DB::table('piutang')->where('id_customer',$id)->first();
            $initial=match($method){'Booking'=>100000,'Kredit'=>200000,'Cash Keras'=>1000000};
            $this->assertSame($initial,(int)$bill->terbayar);
            $this->assertSame(1000000-$initial,(int)$bill->sisa_bayar);
            if($bill->sisa_bayar>0){
                $this->postJson('/admin/pembayaran/tambah-pemasukan/'.$id,['tanggal_pembayaran'=>'2026-01-11','nominal_bayar'=>$bill->sisa_bayar,'id_bank'=>10,'id_metode'=>2,'id_kategori_transaksi'=>28,'keterangan_pembayaran'=>'Pelunasan'])->assertOk()->assertJsonPath('success',true);
                $receipt=DB::table('pemasukan')->where('id_customer',$id)->orderByDesc('id')->first();
                $this->assertSame((int)$bill->id,(int)$receipt->id_piutang);
                $this->assertSame(10,(int)$receipt->id_bank);$this->assertSame(2,(int)$receipt->id_metode);
                $this->assertSame(1,DB::table('pemasukan')->where('no_kwitansi',$receipt->no_kwitansi)->count());
            }
            $this->assertDatabaseHas('piutang',['id'=>$bill->id,'status'=>2,'terbayar'=>1000000,'sisa_bayar'=>0]);
        }
        $this->assertBalanced(3000000);
        $this->postJson('/admin/customer/customer',$this->customerInput(1,'Booking'))->assertUnprocessable();
        $this->assertSame(3,DB::table('customer')->count());
        $receipt=DB::table('pemasukan')->where('id_customer',1)->orderByDesc('id')->first();
        $this->deleteJson('/admin/pembayaran/delete-pemasukan/'.$receipt->id)->assertOk();
        $this->assertDatabaseHas('piutang',['id_customer'=>1,'terbayar'=>100000,'sisa_bayar'=>900000,'status'=>1]);
        $this->assertBalanced(2100000);
    }

    public function test_sales_payment_uses_edit_permission_consistently(): void
    {
        $this->postJson('/admin/customer/customer', $this->customerInput(1, 'Booking'))->assertOk();
        $id = (int) DB::table('customer')->max('id');
        $menuId = DB::table('menu')->where('route_name', 'pembayaran.index')->value('id');
        $permission = DB::table('hak_akses')->where('id_user', 1)->where('id_menu', $menuId);
        $permission->update(['tambah' => 0, 'edit' => 1]);
        $payload = ['tanggal_pembayaran' => '2026-01-11', 'nominal_bayar' => 100000,
            'id_bank' => 9, 'id_kategori_transaksi' => 28];
        $this->postJson('/admin/pembayaran/tambah-pemasukan/'.$id, $payload)
            ->assertOk()->assertJsonPath('success', true);
        $permission->update(['tambah' => 1, 'edit' => 0]);
        $count = DB::table('pemasukan')->count();
        $this->postJson('/admin/pembayaran/tambah-pemasukan/'.$id, $payload)->assertForbidden();
        $this->assertSame($count, DB::table('pemasukan')->count());
    }

    public function test_sales_payment_accepts_no_attachment_and_no_description(): void
    {
        $this->postJson('/admin/customer/customer', $this->customerInput(1, 'Booking'))->assertOk();
        $id = (int) DB::table('customer')->max('id');
        $this->postJson('/admin/pembayaran/tambah-pemasukan/'.$id, [
            'tanggal_pembayaran' => '2026-01-11', 'nominal_bayar' => '100.000',
            'id_bank' => 9, 'id_kategori_transaksi' => 28,
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('pemasukan', ['id_customer' => $id, 'nominal' => 100000, 'lampiran' => '']);
        $this->assertDatabaseHas('piutang', ['id_customer' => $id, 'terbayar' => 200000, 'sisa_bayar' => 800000]);
    }

    public function test_sales_payment_rejects_oversized_attachment_without_recording_payment(): void
    {
        $this->postJson('/admin/customer/customer', $this->customerInput(1, 'Booking'))->assertOk();
        $id = (int) DB::table('customer')->max('id');
        $count = DB::table('pemasukan')->count();
        $this->postJson('/admin/pembayaran/tambah-pemasukan/'.$id, [
            'tanggal_pembayaran' => '2026-01-11', 'nominal_bayar' => 100000,
            'id_bank' => 9, 'id_kategori_transaksi' => 28,
            'file' => UploadedFile::fake()->create('bukti.pdf', 2049, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonPath('errors.file.0', 'File bukti pembayaran terlalu besar. Maksimal 2 MB.');
        $this->assertSame($count, DB::table('pemasukan')->count());
        $this->assertDatabaseHas('piutang', ['id_customer' => $id, 'terbayar' => 100000, 'sisa_bayar' => 900000]);
    }

    public function test_wrong_customer_invoice_and_protected_origins_cannot_be_modified():void
    {
        foreach([1,2] as $lot)$this->postJson('/admin/customer/customer',$this->customerInput($lot,'Booking'))->assertOk();
        $this->postJson('/admin/pembayaran/tambah-tagihan/1',['nominal'=>100000,'id_kategori'=>30,'deskripsi'=>'Tagihan tambahan'])->assertOk();
        $bill=DB::table('piutang')->where('id_customer',1)->where('id_kategori_transaksi',30)->value('id');
        $payload=['tanggal_pembayaran'=>now('Asia/Jakarta')->toDateString(),'nominal_bayar'=>100000,'id_bank'=>9,'id_metode'=>2,'id_kategori_transaksi'=>102,'id_tagihan'=>$bill,'keterangan_pembayaran'=>'Biaya tambahan'];
        $this->postJson('/admin/pembayaran/tambah-pemasukan/2',$payload)->assertUnprocessable();
        $this->postJson('/admin/pembayaran/tambah-pemasukan/1',$payload)->assertOk();
        $this->deleteJson('/admin/pembayaran/delete-tagihan/'.$bill)->assertUnprocessable();
        $this->deleteJson('/admin/master/bank/9')->assertUnprocessable();
        $this->deleteJson('/admin/keuangan/kategori-transaksi/25')->assertUnprocessable();
        $debt=$this->loan('hutang');$origin=DB::table('pemasukan')->where('id_hutang',$debt)->value('id');
        $this->deleteJson('/admin/keuangan/pemasukan/'.$origin)->assertUnprocessable();
        $this->assertBalanced(800000);
    }

    public function test_reconciliation_links_only_unambiguous_legacy_sales_receipts():void
    {
        config(['finance.backup_directory'=>$this->testPublic.'/backups']);
        $this->postJson('/admin/customer/customer',$this->customerInput(1,'Booking'))->assertOk();
        $bill=DB::table('piutang')->where('id_customer',1)->first();
        $receipt=DB::table('pemasukan')->insertGetId(['tanggal'=>'2026-01-10','nominal'=>200000,'id_customer'=>1,'id_bank'=>9,'id_kategori_transaksi'=>28]);
        DB::table('piutang')->where('id',$bill->id)->update(['terbayar'=>300000,'sisa_bayar'=>700000]);
        $audit=app(FinanceAudit::class);
        $this->assertNotEmpty($audit->issues());
        $result=$audit->repair();
        $this->assertSame(1,$result['linked_receipts']);
        $this->assertFileExists($result['backup']);
        $this->assertDatabaseHas('pemasukan',['id'=>$receipt,'id_piutang'=>$bill->id,'nominal'=>200000]);
        $this->assertBalanced(300000);
        $other=(array)$bill;unset($other['id']);$other['terbayar']=0;$other['sisa_bayar']=$other['nominal'];
        DB::table('piutang')->insert($other);
        DB::table('pemasukan')->insert(['tanggal'=>'2026-01-11','nominal'=>10000,'id_customer'=>1,'id_bank'=>9,'id_kategori_transaksi'=>28]);
        $this->assertSame(0,$audit->repair()['linked_receipts']);
        $this->assertNotEmpty(array_filter($audit->issues(),fn($issue)=>$issue['type']==='pembayaran_tanpa_tagihan'));
    }

    public function test_validation_blocks_invalid_accounts_directions_and_negative_amounts():void
    {
        foreach([0,-1,'invalid'] as $amount){
            $this->postJson('/admin/keuangan/pemasukan',$this->input(1,44)+[])->assertOk();
            $input=$this->input(1,83);$input['nominal']=$amount;
            $this->postJson('/admin/keuangan/pengeluaran',$input)->assertUnprocessable();
        }
        $this->postJson('/admin/keuangan/pemasukan',$this->input(100,83))->assertNotFound();
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(100,44))->assertNotFound();
        $this->postJson('/admin/keuangan/pengeluaran',$this->input(100,83,999))->assertUnprocessable();
        $this->post('/admin/keuangan/mutasi-saldo',['tanggal'=>'2026-01-11','rekening_asal'=>9,'rekening_tujuan'=>9,'nominal'=>100,'lampiran'=>UploadedFile::fake()->create('invalid.pdf',1,'application/pdf')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->getJson('/admin/keuangan/laporan-arus-kas/filter?tahun=2026&bulan=13&rekening=0')->assertUnprocessable();
        $this->assertBalanced(3);
    }

    public function test_multilot_discount_and_optional_purchase_fields_are_calculated_once():void
    {
        $input=$this->customerInput(1,'Booking');
        $input['id_kavling']=[1,2];$input['discount']=101;$input['hrg_jual']=2000000;
        foreach(['dp_kredit','inhouse_tenor','inhouse_perbulan','inhouse_jatuh_tempo'] as $key)$input[$key]='';
        $this->postJson('/admin/customer/customer',$input)->assertOk();
        $this->assertDatabaseHas('piutang',['id_customer'=>1,'nominal'=>1999899,'terbayar'=>100000,'sisa_bayar'=>1899899]);
        $cash=$this->customerInput(3,'Cash Keras');
        foreach(['dp_kredit','inhouse_tenor','inhouse_perbulan','inhouse_jatuh_tempo','pembayaran_booking','tgl_batas_booking'] as $key)$cash[$key]='';
        $this->postJson('/admin/customer/customer',$cash)->assertOk();
        $this->assertDatabaseHas('piutang',['id_customer'=>2,'nominal'=>1000000,'terbayar'=>1000000,'sisa_bayar'=>0,'status'=>2]);
        $credit=$this->customerInput(4,'Kredit');$credit['discount']=1;$credit['inhouse_tenor']=3;
        $this->postJson('/admin/customer/customer',$credit)->assertOk();
        $this->assertDatabaseHas('customer',['id'=>3,'inhouse_perbulan'=>266667]);
        $this->assertBalanced(1300000);
    }

    public function test_category_crud_uses_the_shared_transaction_categories(): void
    {
        $payload = ['kode' => 'TEST-CAT', 'kategori' => 'Kategori Uji', 'jenis_kategori' => 'PEMASUKAN'];
        $this->postJson(route('kategori-transaksi.store'), $payload)->assertOk();
        $category = DB::table('kategori_transaksi')->where('kode', 'TEST-CAT')->first();
        $this->getJson(route('kategori-transaksi.show', $category->id))->assertOk()->assertJsonPath('data.kategori', 'Kategori Uji');
        $this->getJson(route('kategori-transaksi.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonStructure(['data']);
        $this->postJson(route('kategori-transaksi.store'), $payload)->assertUnprocessable();
        $payload['kategori'] = 'Kategori Diperbarui';
        $this->putJson(route('kategori-transaksi.update', $category->id), $payload)->assertOk();
        $this->assertDatabaseHas('kategori_transaksi', ['id' => $category->id, 'kategori' => 'Kategori Diperbarui']);
        $this->deleteJson(route('kategori-transaksi.destroy', $category->id))->assertOk();
        $this->assertDatabaseMissing('kategori_transaksi', ['id' => $category->id]);
        $this->deleteJson(route('kategori-transaksi.destroy', 37))->assertUnprocessable();
        $this->putJson(route('kategori-transaksi.update', 37), $payload)->assertUnprocessable();
    }

    public function test_additional_customer_receivable_preserves_original_balance_and_is_payable(): void
    {
        $this->postJson('/admin/customer/customer', $this->customerInput(1, 'Booking'))->assertOk();
        $original = DB::table('piutang')->where('id_customer', 1)->first();
        $payload = ['tanggal_piutang' => '2026-01-15', 'nominal' => 250000, 'deskripsi' => 'Kelebihan tanah', 'id_kategori_transaksi' => 102];
        $this->postJson(route('piutang.additional', $original->id), $payload)->assertCreated();
        $extra = DB::table('piutang')->where('deskripsi', 'Kelebihan tanah')->first();
        $this->assertSame(1, (int) $extra->id_customer);
        $this->assertSame(250000, (int) $extra->sisa_bayar);
        $this->assertSame((int) $original->nominal, (int) DB::table('piutang')->where('id', $original->id)->value('nominal'));
        $this->getJson(route('piutang.show', $original->id))->assertOk()->assertJsonPath('can_additional', true);
        $view = file_get_contents(resource_path('views/admin/pembayaran/detail.blade.php'));
        $this->assertStringContainsString('- Pembayaran -', $view);
        $this->assertStringNotContainsString('Tambah Tagihan', $view);
        $payload['nominal'] = -1;
        $this->postJson(route('piutang.additional', $original->id), $payload)->assertUnprocessable();
        DB::table('hak_akses')->update(['tambah' => 0]);
        $this->postJson(route('piutang.additional', $original->id), $payload)->assertForbidden();
    }

    public function test_menu_permissions_block_direct_requests_before_mutating_data(): void
    {
        DB::table('hak_akses')->update(['tambah' => 0, 'edit' => 0, 'hapus' => 0]);
        $this->postJson(route('bank.store'), ['nama' => 'Unauthorized'])->assertForbidden();
        $this->putJson(route('bank.update', 9), ['nama' => 'Unauthorized'])->assertForbidden();
        $this->deleteJson(route('bank.destroy', 9))->assertForbidden();
        $this->assertDatabaseHas('bank', ['id' => 9, 'nama' => 'Bank A']);
        DB::table('hak_akses')->update(['lihat' => 0]);
        $this->getJson(route('bank.index'))->assertForbidden();
        $this->getJson(route('nasabah.file.download', 1))->assertForbidden();
    }

    public function test_receivable_recap_counts_partial_payments_in_the_paid_total():void
    {
        $id=$this->loan('piutang',1000000);
        $this->postJson('/admin/keuangan/piutang/'.$id.'/bayar',$this->input(250000,103))->assertOk();
        $response=$this->getJson('/admin/keuangan/piutang-rekap/filter?tahun=2026&bulan=0&lokasi=0&status=0',['X-Requested-With'=>'XMLHttpRequest'])->assertOk();
        $this->assertArrayNotHasKey('error',$response->json());
        $response->assertJsonPath('total_piutang',1000000)->assertJsonPath('total_lunas',250000)->assertJsonPath('total_belum_lunas',750000);
        $query='?tahun=2026&bulan=0&lokasi=0&status=0';
        $pdf=$this->get('/admin/keuangan/piutang-rekap/export-pdf'.$query)->assertOk();
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        $excel=$this->get('/admin/keuangan/piutang-rekap/export-excel'.$query)->assertOk();
        $file=$this->testPublic.'/piutang.xlsx';file_put_contents($file,$excel->streamedContent());
        $sheet=IOFactory::load($file)->getActiveSheet();
        $this->assertSame(1000000,$sheet->getCell('D6')->getValue());
        $this->assertSame(250000,$sheet->getCell('E6')->getValue());
        $this->assertSame(750000,$sheet->getCell('F6')->getValue());
    }
}
