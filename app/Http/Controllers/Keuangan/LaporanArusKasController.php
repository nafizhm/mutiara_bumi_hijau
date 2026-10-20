<?php
namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\CashFlow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;
use Yajra\DataTables\Facades\DataTables;

class LaporanArusKasController extends Controller
{
    public function index()
    {
        $monthList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $rekeningList = Bank::all();
        $first = collect([DB::table('pemasukan')->min('tanggal'), DB::table('pengeluaran')->min('tanggal')])->filter()->min();
        $oldYear = $first ? min(Carbon::parse($first)->year, now('Asia/Jakarta')->year) : now('Asia/Jakarta')->year;
        return view('admin.keuangan.laporan_arus_kas.index', compact('monthList', 'rekeningList', 'oldYear'));
    }

    public function filter(Request $request)
    {
        [$year,$month,$bank] = $this->filters($request);
        $report = app(CashFlow::class)->report($year,$month,$bank);
        $totals = array_diff_key($report, ['rows'=>true]);
        return DataTables::of($report['rows'])->addIndexColumn()
            ->editColumn('tanggal', fn($r)=>Carbon::parse($r->tanggal)->locale('id')->translatedFormat('d M Y'))
            ->addColumn('debit', fn($r)=>$r->debit_value ? $this->money($r->debit_value) : '-')
            ->addColumn('kredit', fn($r)=>$r->kredit_value ? $this->money($r->kredit_value) : '-')
            ->addColumn('saldo', fn($r)=>$this->money($r->saldo_value))->with($totals)->make(true);
    }

    public function exportPdf(Request $request)
    {
        [$year,$month,$bank] = $this->filters($request);
        $report = app(CashFlow::class)->report($year,$month,$bank);
        $pdf = new TCPDF();
        $pdf->SetMargins(10,10,10);
        $pdf->AddPage('L','A4');
        $pdf->SetFont('helvetica','',9);
        $html = '<h3>Laporan Arus Kas</h3><p>'.e($this->period($year,$month)).' · '.e($bank ? Bank::findOrFail($bank)->nama : 'Semua Rekening').'</p>';
        $html .= '<p>Saldo awal: '.$this->money($report['saldo_awal']).' | Pemasukan: '.$this->money($report['total_pemasukan']).' | Pengeluaran: '.$this->money($report['total_pengeluaran']).' | Saldo akhir: '.$this->money($report['saldo_akhir']).'</p>';
        $html .= '<table border="1" cellpadding="4"><thead><tr><th>Tanggal</th><th>Rekening</th><th>Kategori / Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead><tbody>';
        foreach($report['rows'] as $row) $html .= '<tr><td>'.e($row->tanggal).'</td><td>'.e($row->rekening).'</td><td>'.e($row->kategori).' / '.e($row->keterangan).'</td><td>'.$this->money($row->debit_value).'</td><td>'.$this->money($row->kredit_value).'</td><td>'.$this->money($row->saldo_value).'</td></tr>';
        if($report['rows']->isEmpty()) $html .= '<tr><td colspan="6">Tidak ada transaksi pada periode ini.</td></tr>';
        $html .= '</tbody></table><p>Mutasi antar rekening tidak dihitung sebagai arus kas eksternal pada pilihan Semua Rekening.</p>';
        $pdf->writeHTML($html);
        return response($pdf->Output('arus-kas.pdf','S'),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="arus-kas.pdf"']);
    }

    public function exportExcel(Request $request)
    {
        [$year,$month,$bank] = $this->filters($request);
        $report = app(CashFlow::class)->report($year,$month,$bank);
        $sheetFile = new Spreadsheet();
        $sheet = $sheetFile->getActiveSheet();
        $sheet->setCellValue('A1','Laporan Arus Kas');
        $sheet->setCellValue('A2',$this->period($year,$month));
        $sheet->setCellValue('A3',$bank ? Bank::findOrFail($bank)->nama : 'Semua Rekening');
        $sheet->fromArray(['Tanggal','Rekening','Kategori','Keterangan','Debit','Kredit','Saldo'],null,'A5');
        $i=6;
        foreach($report['rows'] as $row){
            foreach(['A'=>$row->tanggal,'B'=>$row->rekening,'C'=>$row->kategori,'D'=>$row->keterangan??''] as $col=>$text) $sheet->setCellValueExplicit($col.$i,(string)$text,DataType::TYPE_STRING);
            foreach(['E'=>$row->debit_value,'F'=>$row->kredit_value,'G'=>$row->saldo_value] as $col=>$value) $sheet->setCellValueExplicit($col.$i,$value,DataType::TYPE_NUMERIC);
            $i++;
        }
        foreach(['Saldo Awal'=>'saldo_awal','Total Pemasukan'=>'total_pemasukan','Total Pengeluaran'=>'total_pengeluaran','Saldo Akhir'=>'saldo_akhir'] as $label=>$key){$sheet->setCellValue('D'.$i,$label);$sheet->setCellValue('G'.$i,$report[$key]);$i++;}
        $sheet->getStyle('E6:G'.$i)->getNumberFormat()->setFormatCode('#,##0');
        foreach(range('A','G') as $col)$sheet->getColumnDimension($col)->setAutoSize(true);
        $writer = new Xlsx($sheetFile);
        return response()->streamDownload(fn()=>$writer->save('php://output'),'laporan-arus-kas.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function filters(Request $request): array
    {
        $request->merge(['tahun'=>$request->input('tahun') ?: now('Asia/Jakarta')->year,'bulan'=>$request->input('bulan') === '' ? 0 : $request->input('bulan',0),'rekening'=>$request->input('rekening') ?: 0]);
        $data=$request->validate(['tahun'=>'required|integer|min:1900|max:9999','bulan'=>'required|integer|min:0|max:12','rekening'=>'required|integer|min:0']);
        if($data['rekening']>0) Bank::findOrFail($data['rekening']);
        return [(int)$data['tahun'],(int)$data['bulan'],(int)$data['rekening']];
    }

    private function money($amount): string { return 'Rp '.number_format($amount,0,',','.'); }
    private function period(int $year,int $month): string { return $month ? Carbon::create($year,$month,1)->locale('id')->translatedFormat('F Y') : 'Tahun '.$year; }
}
