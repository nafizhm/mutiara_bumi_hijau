<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Bank;
use App\Models\LokasiKavling;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use App\Models\Piutang;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;
use Yajra\DataTables\Facades\DataTables;

class PiutangController extends Controller
{
    public function index(Request $request)
    {
        $permissions = HakAksesController::getUserPermissions();
        $canPayPiutang = \App\Services\ReceivablePayment::canPay($request->user()?->id);
        if ($request->ajax()) {
            $data = Piutang::with('customer:id,nama_lengkap')->withCount('payments')->orderByDesc('id');
            if ($request->filled('tanggal')) {
                $data->whereDate('tanggal_piutang', $request->tanggal);
            }
            if ($request->input('status') === 'lunas') {
                $data->where('sisa_bayar', '<=', 0);
            }
            if ($request->input('status') === 'belum') {
                $data->where('sisa_bayar', '>', 0);
            }
            if ($request->input('sumber') === 'lainnya') {
                $data->nonSales();
            }
            if ($request->input('sumber') === 'penjualan') {
                $data->where('id_customer', '>', 0);
            }

            return DataTables::of($data)->addIndexColumn()
                ->editColumn('tanggal_piutang', fn ($row) => $row->tanggal_piutang ? Carbon::parse($row->tanggal_piutang)->locale('id')->translatedFormat('d M Y') : '-')
                ->addColumn('informasi', function ($row) {
                    $name = $row->customer?->nama_lengkap;
                    $type = $row->id_customer ? 'Penjualan kavling' : 'Piutang lainnya';

                    return '<div class="fw-semibold">'.e($name ?: $row->deskripsi).'</div>'
                        .($name ? '<div class="small text-muted">'.e($row->deskripsi).'</div>' : '')
                        .'<span class="badge bg-light text-dark mt-1">'.$type.'</span>';
                })
                ->filterColumn('informasi', fn ($q, $keyword) => $q->where(fn ($q) => $q->where('deskripsi', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('nama_lengkap', 'like', "%{$keyword}%"))))
                ->editColumn('nominal', fn ($row) => 'Rp '.number_format($row->nominal, 0, ',', '.'))
                ->editColumn('terbayar', fn ($row) => 'Rp '.number_format($row->terbayar, 0, ',', '.'))
                ->editColumn('sisa_bayar', fn ($row) => 'Rp '.number_format(max(0, $row->sisa_bayar), 0, ',', '.'))
                ->editColumn('status', fn ($row) => $row->sisa_bayar <= 0 ? '<span class="badge bg-success">Lunas</span>' : '<span class="badge bg-warning text-dark">Belum lunas</span>')
                ->addColumn('action', function ($row) use ($permissions, $canPayPiutang) {
                    $buttons = '<div class="d-flex flex-wrap justify-content-center gap-1"><button type="button" class="btn btn-sm btn-outline-primary piutang-history-button" data-id="'.$row->id.'">Detail</button>';
                    if (! $row->id_customer && $row->sisa_bayar > 0 && $canPayPiutang) {
                        $buttons .= '<button type="button" class="btn btn-sm btn-primary piutang-pay-button" data-id="'.$row->id.'">Bayar</button>';
                    }
                    if ($permissions['edit']) {
                        $buttons .= '<button class="btn btn-sm btn-outline-secondary edit-button" data-url="'.e(route('piutang.edit', $row->id)).'">Edit</button>';
                    }
                    if (! $row->id_customer && ! $row->payments_count && ! $row->terbayar && $permissions['hapus']) {
                        $buttons .= '<form action="'.e(route('piutang.destroy', $row->id)).'" method="POST">'.csrf_field().method_field('DELETE').'<button type="submit" class="btn btn-sm btn-outline-danger delete-button">Hapus</button></form>';
                    }

                    return $buttons.'</div>';
                })->rawColumns(['informasi', 'status', 'action'])->make(true);
        }
        $bankList = Bank::all();

        return view('admin.keuangan.piutang.index', compact('permissions', 'bankList', 'canPayPiutang'));
    }

    public function edit($id)
    {
        $list = Piutang::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $list,
        ]);
    }

    public function show($id)
    {
        $list = Piutang::with(['customer:id,nama_lengkap', 'payments' => fn ($q) => $q->with('bank:id,nama')->orderBy('tanggal')->orderBy('id')])->findOrFail($id);

        return response()->json([
            'status' => 'success', 'data' => $list,
            'additional_categories' => \App\Models\KategoriTransaksi::where('jenis_kategori', 'PEMASUKAN')->whereNotIn('kategori', \App\Services\FinanceCategories::SYSTEM)->where('id', '>', 36)->get(['id', 'kategori']),
            'can_additional' => (bool) $list->id_customer && \App\Services\ReceivablePayment::canManage(auth()->id(), 'piutang.index', 'tambah'),
            'can_pay' => ! $list->id_customer && $list->sisa_bayar > 0 && \App\Services\ReceivablePayment::canPay(auth()->id()),
        ]);
    }

    public function additional(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'piutang.index', 'tambah'), 403);
        $request->validate([
            'deskripsi' => 'required|string|max:1000',
            'nominal' => 'required|integer|min:1|max:2147483647',
            'tanggal_piutang' => 'required|date_format:Y-m-d',
            'id_kategori_transaksi' => 'required|integer|exists:kategori_transaksi,id',
        ]);
        $bill = DB::transaction(function () use ($request, $id) {
            $source = Piutang::findOrFail($id);
            abort_unless($source->id_customer, 422, 'Tambahan biaya hanya untuk piutang customer penjualan.');
            $customer = \App\Models\Customer::lockForUpdate()->findOrFail($source->id_customer);
            abort_if((bool) $customer->stt_arsip, 422, 'Customer sudah diarsipkan.');
            $category = \App\Models\KategoriTransaksi::where('jenis_kategori', 'PEMASUKAN')
                ->whereNotIn('kategori', \App\Services\FinanceCategories::SYSTEM)->where('id', '>', 36)->find($request->id_kategori_transaksi);
            abort_unless($category, 422, 'Tambahkan kategori pemasukan biaya tambahan terlebih dahulu.');
            return Piutang::create([
                'id_customer' => $customer->id, 'id_bank' => $customer->id_bank,
                'id_kategori_transaksi' => $category->id, 'tanggal_piutang' => $request->tanggal_piutang,
                'deskripsi' => $request->deskripsi, 'nominal' => $request->nominal,
                'terbayar' => 0, 'sisa_bayar' => $request->nominal, 'status' => 1,
            ]);
        });
        return response()->json(['status' => 'success', 'data' => $bill], 201);
    }

    public function pay(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canPay($request->user()?->id), 403);
        $service = app(\App\Services\ReceivablePayment::class);
        $payment = $service->pay((int) $id, $service->validate($request));

        return response()->json(['status' => 'success', 'message' => 'Pembayaran piutang berhasil dicatat.', 'id' => $payment->id]);
    }

    public function payable(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canPay($request->user()?->id), 403);

        return response()->json(['data' => Piutang::nonSales()->where('sisa_bayar', '>', 0)
            ->orderByDesc('id')->get(['id', 'deskripsi', 'sisa_bayar'])]);
    }

    public function store(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'piutang.index','tambah'),403);
        $input=$this->documentInput($request,true);
        return \App\Services\FinanceFiles::withUpload($request,'lampiran',['pengeluaran'],function($filename)use($input){
            return DB::transaction(function()use($input,$filename){
                $receivable=Piutang::create($input+['lampiran'=>$filename,'status'=>1,'terbayar'=>0,'sisa_bayar'=>$input['nominal']]);
                Pengeluaran::create(['id_piutang'=>$receivable->id,'id_bank'=>$input['id_bank'],'tanggal'=>$input['tanggal_piutang'],
                    'nominal'=>$input['nominal'],'lampiran'=>$filename,'keterangan'=>$input['deskripsi'],
                    'id_kategori_transaksi'=>\App\Services\FinanceCategories::id('Pemberian Piutang','PENGELUARAN')]);
                return response()->json(['status'=>'success']);
            });
        });
    }

    public function update(Request $request,$id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'piutang.index','edit'),403);
        $existing=Piutang::findOrFail($id);
        if ($existing->id_customer) {
            $value = (string) $request->nominal;
            if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
                $request->merge(['nominal' => str_replace('.', '', $value)]);
            }
            $input = $request->validate([
                'nominal' => 'required|integer|min:1|max:2147483647',
                'deskripsi' => 'required|string|max:1000',
            ]);
            return DB::transaction(function () use ($existing, $id, $input) {
                $customer = \App\Models\Customer::lockForUpdate()->findOrFail($existing->id_customer);
                abort_if((bool) $customer->stt_arsip, 422, 'Customer sudah diarsipkan.');
                $receivable = Piutang::lockForUpdate()->findOrFail($id);
                if ((int) $input['nominal'] < (int) $receivable->payments()->sum('nominal')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nominal' => 'Nominal piutang tidak boleh lebih kecil dari pembayaran yang sudah tercatat.',
                    ]);
                }
                $receivable->update($input);
                app(\App\Services\ReceivablePayment::class)->sync($receivable);
                return response()->json(['status' => 'success', 'message' => 'Piutang berhasil diperbarui.']);
            });
        }
        $input=$this->documentInput($request,false);
        return \App\Services\FinanceFiles::withUpload($request,'lampiran',['pengeluaran'],function($filename)use($input,$id){
            return DB::transaction(function()use($input,$id,$filename){
                $receivable=Piutang::lockForUpdate()->findOrFail($id);
                if($receivable->payments()->where('tanggal','<',$input['tanggal_piutang'])->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['tanggal_piutang'=>'Tanggal piutang tidak boleh setelah pembayaran yang sudah tercatat.']);
                $receivable->update($input+['lampiran'=>$filename]);
                app(\App\Services\ReceivablePayment::class)->sync($receivable);
                Pengeluaran::where('id_piutang',$id)->update(['id_bank'=>$input['id_bank'],'tanggal'=>$input['tanggal_piutang'],
                    'nominal'=>$input['nominal'],'lampiran'=>$filename,'keterangan'=>$input['deskripsi']]);
                return response()->json(['status'=>'success']);
            });
        },$existing->lampiran);
    }

    public function destroy($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'piutang.index','hapus'),403);
        $filename=DB::transaction(function()use($id){
            $receivable=Piutang::lockForUpdate()->findOrFail($id);
            if($receivable->id_customer || $receivable->terbayar>0 || $receivable->payments()->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['nominal'=>'Piutang penjualan atau yang memiliki pembayaran tidak dapat dihapus.']);
            Pengeluaran::where('id_piutang',$id)->delete();
            $receivable->delete();
            return $receivable->lampiran;
        });
        \App\Services\FinanceFiles::remove(['pengeluaran'],$filename);
        return response()->json(['status'=>'success']);
    }

    private function documentInput(Request $request,bool $creating):array
    {
        $request->merge(['tanggal'=>$request->tanggal_piutang]);
        $input=app(\App\Services\ReceivablePayment::class)->validate($request);
        $request->validate(['deskripsi'=>'required|string|max:1000','lampiran'=>($creating?'required':'nullable').'|file|mimes:jpg,jpeg,png,pdf,webp|max:2048']);
        return ['tanggal_piutang'=>$input['tanggal'],'nominal'=>$input['nominal'],'id_bank'=>$input['id_bank'],'deskripsi'=>$request->deskripsi];
    }
    public function getSisaBayar($id)
    {
        $hutang = Piutang::find($id);

        if (! $hutang) {
            return response()->json(['sisa_bayar' => 0], 404);
        }

        return response()->json(['sisa_bayar' => $hutang->sisa_bayar]);
    }

    public function rekap()
    {
        $monthList = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $lokasiList = LokasiKavling::all();
        $tahunList = DB::table('piutang')->selectRaw('YEAR(tanggal_piutang) as tahun')->pluck('tahun')->unique()->toArray();
        $oldYear = ! empty($tahunList) ? min($tahunList) : now()->year;

        return view('admin.keuangan.piutang.rekap', compact('monthList', 'lokasiList', 'oldYear'));
    }

    private function recapData(Request $request)
    {
        $request->merge(['tahun'=>$request->input('tahun') ?: now('Asia/Jakarta')->year,'bulan'=>$request->input('bulan') ?: 0,'status'=>$request->input('status') ?: 0,'lokasi'=>$request->input('lokasi') ?: '0']);
        $input=$request->validate(['tahun'=>'required|integer|min:1900|max:9999','bulan'=>'required|integer|min:0|max:12','status'=>'required|integer|in:0,1,2','lokasi'=>['required','regex:/^(0|[1-9][0-9]*|all_lokasi|other)$/']]);
        return DB::table('piutang as p')->leftJoin('customer as c','c.id','=','p.id_customer')->leftJoin('lokasi_kavling as l','l.id','=','c.id_lokasi')
            ->whereYear('p.tanggal_piutang',$input['tahun'])
            ->when($input['bulan']>0,fn($q)=>$q->whereMonth('p.tanggal_piutang',$input['bulan']))
            ->when($input['status']==1,fn($q)=>$q->where('p.sisa_bayar','>',0))
            ->when($input['status']==2,fn($q)=>$q->where('p.sisa_bayar','<=',0))
            ->when($input['lokasi']==='all_lokasi',fn($q)=>$q->where('p.id_customer','>',0))
            ->when($input['lokasi']==='other',fn($q)=>$q->where(fn($q)=>$q->whereNull('p.id_customer')->orWhere('p.id_customer',0)))
            ->when(is_numeric($input['lokasi']) && $input['lokasi']>0,fn($q)=>$q->where('c.id_lokasi',$input['lokasi']))
            ->select('p.*','l.nama_kavling')->orderBy('p.tanggal_piutang')->orderBy('p.id')->get();
    }

    public function filter(Request $request)
    {
        $rows=$this->recapData($request);
        return DataTables::of($rows)->addIndexColumn()
            ->editColumn('tanggal_piutang',fn($r)=>Carbon::parse($r->tanggal_piutang)->locale('id')->translatedFormat('d M Y'))
            ->addColumn('nominal_format',fn($r)=>'Rp '.number_format($r->nominal,0,',','.'))
            ->addColumn('status_badge',fn($r)=>$r->sisa_bayar<=0?'<span class="badge bg-success">Lunas</span>':'<span class="badge bg-warning text-dark">Belum lunas</span>')
            ->editColumn('tgl_pelunasan',fn($r)=>$r->tgl_pelunasan?Carbon::parse($r->tgl_pelunasan)->format('d/m/Y'):'-')
            ->addColumn('lokasi',fn($r)=>$r->nama_kavling ?: 'Piutang lainnya')
            ->with(['total_piutang'=>(int)$rows->sum('nominal'),'total_lunas'=>(int)$rows->sum('terbayar'),'total_belum_lunas'=>(int)$rows->sum('sisa_bayar')])
            ->rawColumns(['status_badge'])->make(true);
    }

    public function exportPdf(Request $request)
    {
        $rows=$this->recapData($request);
        $pdf=new TCPDF();$pdf->SetMargins(10,10,10);$pdf->AddPage('L','A4');$pdf->SetFont('helvetica','',9);
        $money=fn($value)=>'Rp '.number_format($value,0,',','.');
        $html='<h3>Rekap Piutang</h3><p>Tahun '.e($request->tahun).' | Bulan '.e($request->bulan ?: 'Semua').'</p>';
        $html.='<p>Total piutang: '.$money($rows->sum('nominal')).' | Sudah dibayar: '.$money($rows->sum('terbayar')).' | Sisa piutang: '.$money($rows->sum('sisa_bayar')).'</p>';
        $html.='<table border="1" cellpadding="4"><thead><tr><th>Tanggal</th><th>Deskripsi</th><th>Lokasi</th><th>Total</th><th>Sudah Dibayar</th><th>Sisa</th><th>Status</th></tr></thead><tbody>';
        foreach($rows as $row)$html.='<tr><td>'.e($row->tanggal_piutang).'</td><td>'.e($row->deskripsi).'</td><td>'.e($row->nama_kavling ?: 'Piutang lainnya').'</td><td>'.$money($row->nominal).'</td><td>'.$money($row->terbayar).'</td><td>'.$money($row->sisa_bayar).'</td><td>'.($row->sisa_bayar<=0?'Lunas':'Belum lunas').'</td></tr>';
        if($rows->isEmpty())$html.='<tr><td colspan="7">Tidak ada data pada periode ini.</td></tr>';
        $pdf->writeHTML($html.'</tbody></table>');
        return response($pdf->Output('rekap-piutang.pdf','S'),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="rekap-piutang.pdf"']);
    }

    public function exportExcel(Request $request)
    {
        $rows=$this->recapData($request);$book=new Spreadsheet();$sheet=$book->getActiveSheet();
        $sheet->setCellValue('A1','Rekap Piutang');$sheet->setCellValue('A2','Tahun '.$request->tahun.' | Bulan '.($request->bulan ?: 'Semua'));
        $sheet->fromArray(['Tanggal','Deskripsi','Lokasi','Total Piutang','Sudah Dibayar','Sisa Piutang','Status'],null,'A4');$i=5;
        foreach($rows as $row){
            foreach(['A'=>$row->tanggal_piutang,'B'=>$row->deskripsi,'C'=>$row->nama_kavling ?: 'Piutang lainnya','G'=>$row->sisa_bayar<=0?'Lunas':'Belum lunas'] as $col=>$value)$sheet->setCellValueExplicit($col.$i,(string)$value,DataType::TYPE_STRING);
            foreach(['D'=>$row->nominal,'E'=>$row->terbayar,'F'=>$row->sisa_bayar] as $col=>$value)$sheet->setCellValueExplicit($col.$i,(int)$value,DataType::TYPE_NUMERIC);
            $i++;
        }
        $sheet->setCellValue('C'.$i,'Total');$sheet->setCellValue('D'.$i,(int)$rows->sum('nominal'));$sheet->setCellValue('E'.$i,(int)$rows->sum('terbayar'));$sheet->setCellValue('F'.$i,(int)$rows->sum('sisa_bayar'));
        $sheet->getStyle('D5:F'.$i)->getNumberFormat()->setFormatCode('#,##0');
        foreach(range('A','G') as $col)$sheet->getColumnDimension($col)->setAutoSize(true);
        $writer=new Xlsx($book);
        return response()->streamDownload(fn()=>$writer->save('php://output'),'rekap-piutang.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
