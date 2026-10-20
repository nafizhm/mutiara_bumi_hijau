<?php
namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\KavlingPeta;
use App\Models\LokasiKavling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;
use Yajra\DataTables\Facades\DataTables;

class KavlingController extends Controller
{
    public function index(Request $request)
    {
        $permissions = HakAksesController::getUserPermissions();

        $lokasiList = LokasiKavling::all();

        if ($request->ajax()) {
            $data = KavlingPeta::with('lokasi')
                ->when($request->id_lokasi, function ($query) use ($request) {
                    $query->where('id_lokasi', $request->id_lokasi);
                })
                ->orderBy('id', 'asc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('panjang', function ($row) {
                    return '
                <p>Panjang: <strong>' . e($row->panjang) . ' m</strong></p>
            ';
                })
                ->addColumn('lebar', function ($row) {
                    return '
                <p>Lebar: <strong>' . e($row->lebar) . ' m</strong></p>
            ';
                })
                ->addColumn('luas', function ($row) {
                    return '
                <p>luas tanah: <strong>' . $row->luas_tanah . ' m</strong></p>
            ';
                })
                ->editColumn('harga', function ($row) {
                    return '
                <div class="d-flex justify-content-between harga-format w-100">
                    <span>Rp.</span>
                    <span>' . number_format($row->hrg_jual, 0, ',', '.') . '</span>
                </div>';
                })
                ->addColumn('action', function ($row) use ($permissions) {
                    $btn = '<div class="text-center">';

                    if ($permissions['edit']) {
                        $btn .= '<a href="' . route('kavling.edit', $row->id) . '" class="edit btn btn-warning btn-sm mx-1">Edit</a>';
                    }


                    return $btn;
                })
                ->rawColumns(['panjang', 'lebar', 'luas', 'harga', 'action'])
                ->make(true);
        }

        return view('admin.master.kavling.index', compact('permissions', 'lokasiList'));
    }

    public function edit($id)
    {
        $data = KavlingPeta::with('lokasi')->findOrFail($id);
        return view('admin.master.kavling.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->merge([
            'hrg_jual'  => str_replace('.', '', $request->hrg_jual),
        ]);

        $request->validate([
            'panjang'  => 'required|numeric',
            'lebar'    => 'required|numeric',
            'luas_tanah'     => 'required|numeric',
            'hrg_jual'       => 'required|string',
            'keterangan'     => 'nullable|string|max:100',
        ], [
            'panjang.required'  => 'Panjang wajib diisi.',
            'panjang.numeric'   => 'Panjang harus berupa angka.',


            'lebar.required'    => 'Lebar wajib diisi.',
            'lebar.numeric'     => 'Lebar harus berupa angka.',


            'luas_tanah.required'     => 'Luas tanah wajib diisi.',
            'luas_tanah.numeric'      => 'Luas tanah harus berupa angka.',


            'hrg_jual.required'       => 'Harga jual wajib diisi.',
            'hrg_jual.string'         => 'Harga jual harus berupa teks.',

            'keterangan.string'       => 'Keterangan harus berupa teks.',
            'keterangan.max'          => 'Keterangan maksimal 100 karakter.',

        ]);

        $data = KavlingPeta::find($id);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        try {
            $data->update([
                'panjang'      => $request->panjang,
                'lebar'        => $request->lebar,
                'luas_tanah'         => $request->luas_tanah,
                'hrg_jual'           => $request->hrg_jual,
                'keterangan'         => $request->keterangan ?? '',
                'id_rumah_sikumbang' => '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
            ], 500);
        }
    }

    public function importExcel(Request $request)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'kavling.index', 'edit'), 403);
        $request->validate([
            'id_lokasi' => 'required|integer|exists:lokasi_kavling,id',
            'file' => 'required|file|mimes:xlsx|max:5120',
        ]);
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
        $reader->setReadDataOnly(false);
        $book = $reader->load($request->file('file')->getRealPath());
        try {
            $sheet = $book->getActiveSheet();
            $headers = ['No', 'Lokasi / Cluster', 'Kode Kavling', 'Panjang (m)', 'Lebar (m)', 'Luas Tanah (m?)', 'Harga Jual (Rp)', 'Keterangan'];
            $actual = $sheet->rangeToArray('A4:H4', null, false, false)[0];
            if ($actual !== $headers) {
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Format tidak sesuai. Gunakan file Cetak Excel terbaru tanpa mengubah header.']);
            }
            abort_if($sheet->getHighestDataRow() > 5004, 422, 'Maksimal 5000 baris per upload.');
            $location = LokasiKavling::findOrFail($request->id_lokasi);
            $updates = [];
            for ($line = 5; $line <= $sheet->getHighestDataRow(); $line++) {
                $values = $sheet->rangeToArray('A'.$line.':H'.$line, null, false, false)[0];
                if (!array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                    continue;
                }
                foreach (range('A', 'H') as $column) {
                    if ($sheet->getCell($column.$line)->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Baris {$line}: rumus Excel tidak diperbolehkan."]);
                    }
                }
                $input = ['lokasi' => trim((string) $values[1]), 'kode' => trim((string) $values[2]),
                    'panjang' => $values[3], 'lebar' => $values[4], 'luas_tanah' => $values[5],
                    'hrg_jual' => $values[6], 'keterangan' => $values[7] ?? ''];
                $validator = validator($input, ['kode' => 'required|string|max:100',
                    'panjang' => 'required|numeric|min:0', 'lebar' => 'required|numeric|min:0',
                    'luas_tanah' => 'required|numeric|min:0', 'hrg_jual' => 'required|integer|min:0|max:2147483647',
                    'keterangan' => 'nullable|string|max:255']);
                if ($validator->fails()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Baris {$line}: ".implode(' ', $validator->errors()->all())]);
                }
                if ($input['lokasi'] !== $location->nama_kavling || isset($updates[$input['kode']])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Baris {$line}: lokasi berbeda dari pilihan atau kode kavling duplikat."]);
                }
                $updates[$input['kode']] = $input;
            }
            if (!$updates) {
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'File tidak berisi data kavling.']);
            }
            DB::transaction(function () use ($updates, $location) {
                foreach ($updates as $code => $input) {
                    $matches = KavlingPeta::where('id_lokasi', $location->id)->where('kode_kavling', $code)->lockForUpdate()->get();
                    if ($matches->count() !== 1) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['file' => "Kode {$code} tidak ditemukan atau tidak unik pada lokasi pilihan. Tidak ada perubahan disimpan."]);
                    }
                    unset($input['kode'], $input['lokasi']);
                    $matches->first()->update($input);
                }
            });
            return response()->json(['success' => true, 'message' => count($updates).' kavling berhasil diperbarui.']);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function exportRows($id_lokasi)
    {
        return KavlingPeta::with('lokasi')->when((int) $id_lokasi !== 0, fn ($query) => $query->where('id_lokasi', $id_lokasi))
            ->orderBy('id_lokasi')->orderBy('kode_kavling')->get();
    }

    private function exportLocation($id_lokasi): string
    {
        return (int) $id_lokasi === 0 ? 'Semua Lokasi' : LokasiKavling::findOrFail($id_lokasi)->nama_kavling;
    }

    public function cetakPdf(Request $request, $id_lokasi)
    {
        $data = $this->exportRows($id_lokasi);
        $location = $this->exportLocation($id_lokasi);
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetTitle('Data Kavling');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->MultiCell(0, 8, 'DATA KAVLING '.mb_strtoupper($location), 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Dicetak: '.now('Asia/Jakarta')->format('d/m/Y H:i').' WIB', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 8);
        $headers = ['No', 'Lokasi / Cluster', 'Kode Kavling', 'Panjang (m)', 'Lebar (m)', 'Luas Tanah (m?)', 'Harga Jual (Rp)', 'Keterangan'];
        $widths = [4, 18, 11, 9, 9, 11, 16, 22];
        $html = '<table border="1" cellpadding="5" cellspacing="0" width="100%"><thead><tr style="background-color:#e8edf3;font-weight:bold;">';
        foreach ($headers as $index => $header) {
            $html .= '<th width="'.$widths[$index].'%" align="center">'.e($header).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data as $index => $row) {
            $values = [$index + 1, $row->lokasi?->nama_kavling ?? '-', $row->kode_kavling ?? '-',
                $row->panjang, $row->lebar, $row->luas_tanah,
                number_format((float) $row->hrg_jual, 0, ',', '.'), $row->keterangan ?? '-'];
            $html .= '<tr nobr="true">';
            foreach ($values as $column => $value) {
                $html .= '<td width="'.$widths[$column].'%" align="'.($column === 6 ? 'right' : ($column === 1 || $column === 7 ? 'left' : 'center')).'">'.e((string) $value).'</td>';
            }
            $html .= '</tr>';
        }
        if ($data->isEmpty()) {
            $html .= '<tr><td colspan="8" align="center">Tidak ada data kavling.</td></tr>';
        }
        $pdf->writeHTML($html.'</tbody></table>', true, false, true, false, '');
        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Data-Kavling-'.Str::slug($location).'.pdf"',
        ]);
    }

    public function cetakExcel(Request $request, $id_lokasi = 0)
    {
        $data = $this->exportRows($id_lokasi);
        $location = $this->exportLocation($id_lokasi);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Kavling');
        $sheet->mergeCells('A1:H1')->setCellValue('A1', 'DATA KAVLING '.mb_strtoupper($location));
        $sheet->mergeCells('A2:H2')->setCellValue('A2', 'Dicetak: '.now('Asia/Jakarta')->format('d/m/Y H:i').' WIB');
        $sheet->getStyle('A1:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->fromArray(['No', 'Lokasi / Cluster', 'Kode Kavling', 'Panjang (m)', 'Lebar (m)', 'Luas Tanah (m?)', 'Harga Jual (Rp)', 'Keterangan'], null, 'A4');
        $sheet->getStyle('A4:H4')->getFont()->setBold(true);
        $sheet->getStyle('A4:H4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EDF3');
        foreach ($data as $index => $row) {
            $line = $index + 5;
            $sheet->setCellValue('A'.$line, $index + 1);
            foreach (['B' => $row->lokasi?->nama_kavling ?? '-', 'C' => $row->kode_kavling ?? '-', 'H' => $row->keterangan ?? ''] as $column => $value) {
                $sheet->setCellValueExplicit($column.$line, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->setCellValue('D'.$line, (float) $row->panjang);
            $sheet->setCellValue('E'.$line, (float) $row->lebar);
            $sheet->setCellValue('F'.$line, (float) $row->luas_tanah);
            $sheet->setCellValue('G'.$line, (float) $row->hrg_jual);
        }
        $last = max(4, $data->count() + 4);
        $sheet->getStyle('A4:H'.$last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A4:H'.$last)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('G5:G'.max(5, $last))->getNumberFormat()->setFormatCode('#,##0');
        foreach (['A' => 6, 'B' => 28, 'C' => 18, 'D' => 14, 'E' => 14, 'F' => 18, 'G' => 23, 'H' => 45] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:H'.$last);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(4, 4)->setPrintArea('A1:H'.$last);
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Data-Kavling-'.Str::slug($location).'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
