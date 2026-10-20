<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Pengaturan\HakAksesController;
use App\Models\Customer;
use App\Models\KategoriTransaksi;
use App\Models\KavlingPeta;
use App\Models\KonfigurasiAplikasi;
use App\Models\KonfigurasiMedia;
use App\Models\KonfigurasiWa;
use App\Models\ListPenjualan;
use App\Models\LokasiKavling;
use App\Models\MetodeBayar;
use App\Models\Pemasukan;
use App\Models\Piutang;
use App\Models\TemplatePesan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use TCPDF;
use Yajra\DataTables\Facades\DataTables;

class PembayaranController extends Controller
{
    public function index(Request $request)
    {
        $permissions = HakAksesController::getUserPermissions();

        if ($request->ajax()) {
            $data = Customer::with(['piutangs', 'progres', 'marketing', 'lokasi', 'kavling.lokasi'])
                ->where('stt_arsip', 0)
                ->orderBy('id', 'desc');

            if ($request->status) {
                if ($request->status == 'Lunas') {
                    $data->whereHas('piutangs', function ($q) {
                        $q->select('id_customer') // jangan pakai *
                            ->groupBy('id_customer')
                            ->havingRaw('SUM(sisa_bayar) = 0');
                    });
                } elseif ($request->status == 'Terhutang') {
                    $data->whereHas('piutangs', function ($q) {
                        $q->select('id_customer')
                            ->groupBy('id_customer')
                            ->havingRaw('SUM(sisa_bayar) > 0');
                    });
                }
            }

            if ($request->progres) {
                $data->where('id_status_progres', $request->progres);
            }

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('customer', function ($row) {
                    return '<div class="payment-customer"><strong>' . e($row->nama_lengkap) . '</strong><span class="text-muted d-block mt-1">' . e($row->kode_customer) . ' · ' . e($row->no_telp) . '</span></div>';
                })
                ->filterColumn('customer', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('nama_lengkap', 'like', "%{$keyword}%")
                            ->orWhere('no_telp', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('lokasi_kavling', function ($row) {
                    $locations = $row->kavling->pluck('lokasi.nama_kavling')->filter()->unique();
                    return $locations->isNotEmpty() ? $locations->implode(', ') : ($row->lokasi?->nama_kavling ?? '-');
                })
                ->filterColumn('lokasi_kavling', function ($query, $keyword) {
                    $query->where(function ($query) use ($keyword) {
                        $query->whereHas('kavling.lokasi', fn ($query) => $query->where('nama_kavling', 'like', "%{$keyword}%"))
                            ->orWhereHas('lokasi', fn ($query) => $query->where('nama_kavling', 'like', "%{$keyword}%"));
                    });
                })
                ->addColumn('kavling_dibeli', fn ($row) => $row->kavling->pluck('kode_kavling')->filter()->unique()->implode(', ') ?: '-')
                ->filterColumn('kavling_dibeli', fn ($query, $keyword) => $query->whereHas('kavling', fn ($query) => $query->where('kode_kavling', 'like', "%{$keyword}%")))
                ->addColumn('status', function ($row) {
                    $status = $row->progres->status_progres ?? $row->jenis_pembelian ?? '-';
                    $color = match ($row->jenis_pembelian) {
                        'Cash Keras' => 'success',
                        'Kredit' => 'primary',
                        default => 'secondary',
                    };
                    return '<span class="badge bg-' . $color . '">' . e($status) . '</span>';
                })
                ->addColumn('total_pembayaran', function ($row) {
                    $totalTagihan = $row->piutangs->sum('nominal');
                    $totalBayar   = $row->piutangs->sum('terbayar');
                    return '<div class="payment-money">Rp ' . number_format($totalBayar, 0, ',', '.') . '</div><small class="text-muted">dari Rp ' . number_format($totalTagihan, 0, ',', '.') . '</small>';
                })
                ->addColumn('sisa_pembayaran', function ($row) {
                    $sisa = max($row->piutangs->sum('sisa_bayar'), 0);
                    return $sisa == 0
                        ? '<span class="badge bg-success">Lunas</span>'
                        : '<div class="payment-money text-danger">Rp ' . number_format($sisa, 0, ',', '.') . '</div>';
                })
                ->addColumn('action', function ($row) {
                    $editUrl  = route('pembayaran.show', $row->id);
                    $btn      = '<div class="d-flex justify-content-center">';
                    $btn     .= '<a class="btn btn-success btn-sm" href="' . e($editUrl) . '">Detail</a>';
                    $btn     .= '</div>';

                    return $btn;
                })
                ->rawColumns(['customer', 'status', 'total_pembayaran', 'sisa_pembayaran', 'action'])
                ->make(true);
        }

        $progreslists = ListPenjualan::all(['id', 'status_progres']);

        return view('admin.pembayaran.index', compact('permissions', 'progreslists'));
    }

    public function show($id)
    {
        $customer = Customer::with(['piutangs', 'lokasi', 'kavling'])
            ->findOrFail($id);

        $totalHargaJual = $customer->kavling->sum(function ($kav) {
            return $kav->pivot->hrg_rumah;
        });

        $kodeKavlingGabungan = $customer->kavling->pluck('kode_kavling')->implode(', ');

        $metodeBayar = MetodeBayar::all();
        $bankList = \App\Models\Bank::all();

        $kategoriTransaksiPemasukan = KategoriTransaksi::whereIn('id', [25, 26, 27, 28, 102])
            ->get();

        $kategoriTransaksiTagihan = KategoriTransaksi::whereIn('id', [30])
            ->get();

        $piutang = Piutang::with('kategori')
            ->where('id_customer', $id)
            ->where('id_kategori_transaksi', '!=', 0)
            ->get();

        return view('admin.pembayaran.detail', compact(
            'customer',
            'metodeBayar',
            'bankList',
            'kategoriTransaksiPemasukan',
            'kategoriTransaksiTagihan',
            'piutang',
            'totalHargaJual',
            'kodeKavlingGabungan'
        ));
    }

    public function cetakRekap($id)
    {
        $customer = Customer::with(['pemasukans', 'kavling.lokasi', 'lokasiKavling', 'piutangs'])
            ->findOrFail($id);

        $totalHargaKavling = $customer->kavling->sum(function ($kav) {
            return $kav->pivot->hrg_rumah;
        });

        $kodeKavlingGabungan = $customer->kavling->pluck('kode_kavling')->implode(', ');

        $totalTagihan   = $customer->piutangs->sum('nominal') ?? 0;
        $kavlingPertama = $customer->kavling->first();

        $konfigurasi    = KonfigurasiAplikasi::select('nama_perusahaan', 'telp')->first();
        $namaPerusahaan = $konfigurasi->nama_perusahaan ?? 'PT. MULIA ASRI SENTOSA';
        $telp           = $konfigurasi->telp ?? '081250274777';

        $pemasukanBersih = $customer->pemasukans->filter(function ($p) {
            return ! str_starts_with($p->keterangan, 'Biaya ganti nama');
        });

        $lokasi          = $customer->lokasiKavling;
        $pengaturanMedia = KonfigurasiMedia::where('jenis_data', 'kop surat')->first();
        $logoPath        = null;

        if ($pengaturanMedia && $pengaturanMedia->nama_file) {
            $logoPath = public_path('config_media/' . $pengaturanMedia->nama_file);
        }

        $pdf = new TCPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        if ($logoPath && file_exists($logoPath)) {
            $pdf->Image($logoPath, 0, 5, 210);
        }

        $pdf->SetXY(90, 32);

        $pdf->Ln(10);
        $pdf->SetFont('Times', 'B', 10);
        $pdf->SetTextColor(218, 0, 0);
        $pdf->Cell(190, 8, 'TABEL REKAP PEMBAYARAN', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Times', '', 9);

        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(17, 6, 'Nama', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(45, 6, strtoupper($customer->nama_lengkap), 0, 0);
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(17, 6, 'Lokasi', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(20, 6, $kodeKavlingGabungan, 0, 0);
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(25, 6, 'Total Tagihan', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(30, 6, 'Rp ' . number_format($totalTagihan ?? 0, 0, ',', '.'), 0, 1);

        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(17, 6, 'No. KTP', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(45, 6, $customer->no_ktp ?? '-', 0, 0);
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(17, 6, 'Luas Tanah', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(25, 6, ($kavlingPertama->luas_tanah ?? '-') . ' M2', 0, 0);
        $pdf->Cell(25, 6, 'Luas Bangunan', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(30, 6, ($kavlingPertama->luas_bangunan ?? '-') . ' M2', 0, 1);

        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(17, 6, 'Alamat', 0, 0);
        $pdf->Cell(3, 6, ':', 0, 0);
        $pdf->Cell(100, 6, $customer->alamat, 0, 1);

        $pdf->Ln(2);
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(35, 6, 'Metode Pembayaran', 0, 0);
        $pdf->Cell(35, 6, ': ' . ($customer->jenis_pembelian ?? '-'), 0, 1);

        $pdf->Ln(5);
        $pdf->SetFont('Times', 'B', 9);
        $pdf->SetFillColor(211, 236, 230);
        $pdf->Cell(5, 7, '', 0, 0);
        $pdf->Cell(8, 7, 'No', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Tanggal', 1, 0, 'C', true);
        $pdf->Cell(75, 7, 'Keterangan', 1, 0, 'L', true);
        $pdf->Cell(31, 7, 'Pembayaran', 1, 0, 'C', true);
        $pdf->Cell(31, 7, 'Sisa Pembayaran', 1, 1, 'C', true);

        $pdf->SetFont('Times', '', 9);
        $no   = 1;
        $sisa = $totalTagihan ?? 0;

        foreach ($pemasukanBersih->sortBy('tanggal')->values() as $index => $byr) {
            $sisa        -= $byr->nominal;
            $jumlahBayar  = $byr->nominal;

            $keterangan = explode('#', $byr->keterangan)[0] ?? '';
            $fill       = ($no % 2 == 0) ? [255, 243, 243] : [255, 255, 255];
            $pdf->SetFillColor(...$fill);

            $pdf->Cell(5, 7, '', 0, 0);
            $pdf->Cell(8, 7, $no++, 1, 0, 'C', true);
            $pdf->Cell(30, 7, date('d-m-Y', strtotime($byr->tanggal)), 1, 0, 'C', true);
            $pdf->Cell(75, 7, ($sisa <= 0) ? 'LUNAS' : $keterangan, 1, 0, 'L', true);
            $pdf->Cell(31, 7, 'Rp ' . number_format($jumlahBayar, 0, ',', '.'), 1, 0, 'R', true);
            $pdf->Cell(31, 7, 'Rp ' . number_format(max($sisa, 0), 0, ',', '.'), 1, 1, 'R', true);
        }

        $pdf->Ln(10);
        $pdf->SetFont('', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $catatan = "Catatan:\n"
            . "- Bukti pembayaran dinyatakan sah apabila disertai kwitansi dari tangan pemilik kavling.\n"
            . "- Apabila ada yang mengaku-ngaku petugas kami \"$namaPerusahaan\" meminta/menagih pembayaran angsuran, harap waspada. HATI-HATI PENIPUAN.\n"
            . "- Konsumen dapat menanyakan atau menghubungi informasi resmi \"$namaPerusahaan\" di nomor $telp.";

        $pdf->MultiCell(0, 0, $catatan, 0, 'L');

        $pdf->Ln(15);
        $pdf->SetFont('Times', '', 9);
        $pdf->Cell(60, 6, 'Mengetahui', 0, 0, 'C');
        $pdf->Cell(70, 6, '', 0, 0);
        $pdf->Cell(60, 6, 'Admin', 0, 1, 'C');
        $pdf->Ln(20);

        $namaMengetahui = $lokasi->nama_penandatangan ?? '';
        $namaAdmin      = $lokasi->nama_admin ?? '';
        $pdf->Cell(60, 6, $namaMengetahui, 0, 0, 'C');
        $pdf->Cell(70, 6, '', 0, 0);
        $pdf->Cell(60, 6, $namaAdmin, 0, 1, 'C');

        $pdf->Output();
        exit;
    }

    public function cetak($id)
    {
        $payment = Pemasukan::with(['customer.piutangs', 'customer.lokasiKavling', 'metode', 'kategori'])->findOrFail($id);
        $customer = $payment->customer;
        abort_unless($customer, 404);
        $config = KonfigurasiAplikasi::first();
        $location = $customer->lokasiKavling;
        $paid = Pemasukan::where('id_customer', $customer->id)
            ->where(function ($query) use ($payment) {
                $query->where('tanggal', '<', $payment->tanggal)->orWhere(function ($query) use ($payment) {
                    $query->where('tanggal', $payment->tanggal)->where('id', '<=', $payment->id);
                });
            })->sum('nominal');
        $remaining = max(0, $customer->piutangs->sum('nominal') - $paid);
        $pdf = new TCPDF('L', 'mm', [210, 110.42], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        $pdf->Image(public_path('config_media/master_kwitansi_template.jpg'), 0, 0, 210, 110.42, 'JPG');
        $logo = KonfigurasiMedia::where('jenis_data', 'logo website')->value('nama_file');
        if ($logo && basename($logo) === $logo && is_file(public_path('config_media/'.$logo))) {
            $logoPath = public_path('config_media/'.$logo);
            if (strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) === 'webp') {
                $image = imagecreatefromwebp($logoPath);
                ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
                $pdf->Image('@'.$png, 10, 3, 19, 19, 'PNG');
            } else {
                $pdf->Image($logoPath, 10, 3, 19, 19);
            }
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(34, 4);
        $pdf->MultiCell(160, 6, $config->nama_perusahaan ?? '', 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(34, 12);
        $pdf->MultiCell(160, 4, ($config->alamat ?? '')."\nTelp. ".($config->telp ?? ''), 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(16, 33);
        $pdf->Cell(68, 5, $payment->no_kwitansi ?? '-', 0, 0);
        $pdf->SetXY(57, 39);
        $pdf->MultiCell(141, 5, $customer->nama_lengkap, 0, 'L', false, 1, '', '', true, 0, false, true, 5, 'M', true);
        $pdf->SetFont('helvetica', 'BI', 9);
        $pdf->SetXY(57, 45);
        $pdf->MultiCell(141, 5, trim($this->terbilang($payment->nominal)).' Rupiah', 0, 'L', false, 1, '', '', true, 0, false, true, 5, 'M', true);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(57, 52);
        $pdf->MultiCell(141, 6, $payment->keterangan ?: ($payment->kategori?->kategori ?? 'Pembayaran kavling'), 0, 'L', false, 1, '', '', true, 0, false, true, 11, 'T', true);
        $pdf->SetXY(57, 66);
        $pdf->Cell(141, 5, 'Rp '.number_format($remaining, 0, ',', '.'), 0, 0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(20, 74);
        $pdf->Cell(58, 5, number_format($payment->nominal, 0, ',', '.'), 0, 0);
        $transfer = str_contains(strtolower($payment->metode?->jenis_bayar ?? ''), 'transfer');
        $pdf->SetXY($transfer ? 28 : 11, 82.5);
        $pdf->Cell(4, 4, 'X', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(142, 79);
        $pdf->Cell(58, 5, ($location->kota_penandatangan ?? '').', '.Carbon::parse($payment->tanggal)->format('d/m/Y'), 0, 0, 'C');
        $pdf->SetXY(142, 100);
        $pdf->Cell(58, 5, $location->nama_penandatangan ?? '', 0, 0, 'C');
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $payment->no_kwitansi ?: $payment->id);
        $filename = 'kwitansi_'.$payment->id.'_'.$safe.'.pdf';
        $content = $pdf->Output('', 'S');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(public_path('kwitansi'), 0755);
        file_put_contents(public_path('kwitansi/'.$filename), $content);
        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$filename.'"']);
    }

    private function terbilang($angka)
    {
        $angka = abs($angka);
        $baca  = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $hasil = '';

        if ($angka < 12) {
            $hasil = ' ' . $baca[$angka];
        } elseif ($angka < 20) {
            $hasil = $this->terbilang($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $hasil = $this->terbilang($angka / 10) . ' Puluh' . $this->terbilang($angka % 10);
        } elseif ($angka < 200) {
            $hasil = ' Seratus' . $this->terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $hasil = $this->terbilang($angka / 100) . ' Ratus' . $this->terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $hasil = ' Seribu' . $this->terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $hasil = $this->terbilang($angka / 1000) . ' Ribu' . $this->terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $hasil = $this->terbilang($angka / 1000000) . ' Juta' . $this->terbilang($angka % 1000000);
        }

        return trim($hasil);
    }

    private function normalizePhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace('/\D+/', '', $phoneNumber);

        if (str_starts_with($phoneNumber, '0')) {
            return '62' . substr($phoneNumber, 1);
        }

        if (str_starts_with($phoneNumber, '8')) {
            return '62' . $phoneNumber;
        }

        return $phoneNumber;
    }

    private function sendWaRequest($url, array $payload)
    {
        try {
            if (str_contains($url, '{')) {
                $replace = [];
                foreach ($payload as $key => $value) {
                    $replace['{' . $key . '}'] = rawurlencode((string) $value);
                }

                $response = Http::timeout(30)->acceptJson()->get(strtr($url, $replace));
            } elseif (isset($payload['clientId'])) {
                $response = Http::timeout(30)->acceptJson()->post($url, $payload);
            } else {
                $response = Http::timeout(30)->acceptJson()->post($url, $payload);
            }

            $responseData = $response->json();
            $status       = strtolower((string) data_get($responseData, 'status', ''));
            $successValue = data_get($responseData, 'success', true);
            $okValue      = data_get($responseData, 'ok', true);
            $message      = data_get($responseData, 'message')
                ?? data_get($responseData, 'detail')
                ?? data_get($responseData, 'reason')
                ?? data_get($responseData, 'error')
                ?? data_get($responseData, 'msg')
                ?? $response->body();

            $success = $response->successful()
                && $successValue !== false
                && $okValue !== false
                && ! in_array($status, ['false', 'failed', 'fail', 'error', 'gagal'], true);

            return [
                'success' => $success,
                'message' => $message ?: ($success ? 'Berhasil' : 'Gagal'),
                'data'    => $responseData,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ];
        }
    }

    private function resolveFileApiUrl($url)
    {
        if (str_contains($url, '{url}')) {
            return $url;
        }

        if (str_contains($url, '/messages/text')) {
            return str_replace('/messages/text', '/messages/pdf', $url);
        }

        if (str_contains($url, '/messages/pdf')) {
            return $url;
        }

        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $base = $parts['scheme'] . '://' . $parts['host'];

        if (! empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        if (preg_match('#/clients/[^/]+/?$#', $path)) {
            return rtrim($base, '/') . '/messages/pdf';
        }

        if ($path && $path !== '/') {
            return $url;
        }

        return rtrim($base, '/') . '/messages/pdf';
    }

    private function resolveTextApiUrl($url)
    {
        if (str_contains($url, '{')) {
            return $url;
        }

        if (str_contains($url, '/messages/text')) {
            return $url;
        }

        if (str_contains($url, '/messages/pdf')) {
            return str_replace('/messages/pdf', '/messages/text', $url);
        }

        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($url, '/') . '/messages/text';
        }

        $path = $parts['path'] ?? '';
        $base = $parts['scheme'] . '://' . $parts['host'];

        if (! empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        if (preg_match('#/clients/([^/]+)/?$#', $path)) {
            return rtrim($base, '/') . '/messages/text';
        }

        if ($path && $path !== '/') {
            return $url;
        }

        return rtrim($base, '/') . '/messages/text';
    }

    private function extractClientIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (preg_match('#/clients/([^/]+)/?$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function detailTagihan(Request $request, $id)
    {
        if ($request->ajax()) {
            $tagihanList  = Piutang::where('id_customer', $id)->orderBy('id')->get();
            $totalTagihan = $tagihanList->sum('nominal');
            $firstId      = $tagihanList->first() ? $tagihanList->first()->id : null;

            return DataTables::of($tagihanList)
                ->addIndexColumn()
                ->addColumn('jumlah_tagihan', function ($row) {
                    return '<div class="d-flex justify-content-between">
                        <span>Rp.</span>
                        <span>' . number_format($row->nominal, 0, ',', '.') . '</span>
                    </div>';
                })
                ->addColumn('action', function ($row) use ($id, $firstId) {
                    if (!\App\Services\ReceivablePayment::canManage(auth()->id(), 'pembayaran.index', 'edit')) {
                        return '';
                    }
                    $deleteUrl = route('pembayaran.delete-tagihan', $row->id);

                    if ($row->id == $firstId) {
                        return '<form class="formHargaRumah" data-id="' . $id . '">' .
                        csrf_field() .
                            '<button type="submit" class="btn btn-warning text-white btn-sm ms-1 btn-update-harga">
                            <span class="swal-btn-text">Update</span>
                        </button>' .
                            '</form>';
                    } else {
                        return '<form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="delete-tagihan btn btn-danger btn-sm">Hapus</button></form>';
                    }
                })
                ->rawColumns(['action', 'jumlah_tagihan'])
                ->with('total_tagihan', $totalTagihan)
                ->with('total_tagihan_formatted', number_format($totalTagihan, 0, ',', '.'))
                ->make(true);
        }
    }

    public function tambahTagihan(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'pembayaran.index','edit'),403);
        $amount=(string)$request->nominal;
        if(preg_match('/^\d{1,3}(?:\.\d{3})+$/',$amount)) $request->merge(['nominal'=>str_replace('.','',$amount)]);
        $request->validate(['nominal'=>'required|integer|min:1|max:2147483647','id_kategori'=>'required|integer|exists:kategori_transaksi,id','deskripsi'=>'required|string|max:1000']);
        $category=KategoriTransaksi::where('jenis_kategori','PEMASUKAN')->findOrFail($request->id_kategori);
        DB::transaction(function()use($request,$id,$category){
            $customer=Customer::lockForUpdate()->findOrFail($id);
            abort_if((bool)$customer->stt_arsip,422,'Customer sudah diarsipkan.');
            Piutang::create(['id_customer'=>$id,'id_bank'=>$customer->id_bank,'tanggal_piutang'=>now('Asia/Jakarta')->toDateString(),
                'deskripsi'=>$request->deskripsi,'id_kategori_transaksi'=>$category->id,'nominal'=>$request->nominal,
                'terbayar'=>0,'sisa_bayar'=>$request->nominal,'status'=>1]);
        });
        $totals=$this->paymentTotals((int)$id,'Tagihan berhasil ditambahkan.')->getData(true);
        return response()->json($totals+['total_tagihan_formatted'=>$totals['total_tagihan'],'sisa_bayar_formatted'=>$totals['sisa_bayar']]);
    }

    public function UpdateHargaRumah($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'pembayaran.index','edit'),403);
        DB::transaction(function()use($id){
            $customer=Customer::lockForUpdate()->findOrFail($id);
            $links=$customer->transaksiKavling()->lockForUpdate()->get();
            $lots=KavlingPeta::whereIn('id',$links->pluck('id_kavling'))->orderBy('id')->lockForUpdate()->get();
            $price=(int)$lots->sum('hrg_jual')-(int)$customer->discount;
            if($price<=0 || $lots->contains(fn($lot)=>$lot->hrg_jual<=0)) throw \Illuminate\Validation\ValidationException::withMessages(['nominal'=>'Lengkapi harga master kavling yang valid sebelum memperbarui harga penjualan.']);
            $receivable=Piutang::where('id_customer',$id)->where(fn($q)=>$q->where('id_kategori_transaksi',0)->orWhereNull('id_kategori_transaksi'))->lockForUpdate()->firstOrFail();
            $receivable->update(['nominal'=>$price]);
            app(\App\Services\ReceivablePayment::class)->sync($receivable);
            foreach($lots as $lot) DB::table('transaksi_kavling')->where('id_customer',$id)->where('id_kavling',$lot->id)->update(['hrg_rumah'=>$lot->hrg_jual]);
        });
        $totals=$this->paymentTotals((int)$id,'Harga penjualan berhasil diperbarui.')->getData(true);
        return response()->json($totals+['total_tagihan_formatted'=>$totals['total_tagihan'],'sisa_bayar_formatted'=>$totals['sisa_bayar']]);
    }

    public function DeleteTagihan($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(),'pembayaran.index','edit'),403);
        $customerId=DB::transaction(function()use($id){
            $bill=Piutang::lockForUpdate()->findOrFail($id);
            if(!$bill->id_customer || !$bill->id_kategori_transaksi || $bill->payments()->exists() || $bill->terbayar>0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['nominal'=>'Tagihan utama dan tagihan yang sudah memiliki pembayaran tidak dapat dihapus.']);
            }
            $bill->delete();
            return (int)$bill->id_customer;
        });
        $totals=$this->paymentTotals($customerId,'Tagihan berhasil dihapus.')->getData(true);
        return response()->json($totals+['total_tagihan_formatted'=>$totals['total_tagihan'],'jumlah_bayar_formatted'=>$totals['jumlah_bayar'],'sisa_bayar_formatted'=>$totals['sisa_bayar']]);
    }
    public function detailPemasukan(Request $request, $id)
    {
        Carbon::setLocale('id');

        if ($request->ajax()) {
            $templatePesan = TemplatePesan::where('jenis_pesan', 'kwitansi')->first()
                ?? TemplatePesan::first();

            $data = Pemasukan::with(['kategori', 'customer.kavling'])
                ->where('id_customer', $id)
                ->where('keterangan', 'NOT LIKE', 'Biaya ganti nama%')
                ->get();

            foreach ($data as $item) {
                $deleteUrl              = route('pembayaran.delete-pemasukan', $item->id);
                $cetakUrl               = route('pembayaran.cetak', $item->id);
                $safeFaktur             = preg_replace('/[^A-Za-z0-9_\-]/', '_', $item->no_kwitansi ?: $item->id);
                $pdfFilename            = 'kwitansi_' . $item->id . '_' . $safeFaktur . '.pdf';
                $pdfUrl                 = request()->getSchemeAndHttpHost() . '/kwitansi/' . $pdfFilename;
                $pdfFileExists          = file_exists(public_path('kwitansi/' . $pdfFilename));
                $customer               = $item->customer;
                $kodeKavlingGabungan    = $customer?->kavling?->pluck('kode_kavling')->implode(', ') ?: '-';
                $isiPesan               = $templatePesan->isi_template ?? '';
                $isiPesan               = strtr($isiPesan, [
                    '{nama}'             => $customer->nama_lengkap ?? '-',
                    '{nama_customer}'    => $customer->nama_lengkap ?? '-',
                    '[[nama_customer]]'  => $customer->nama_lengkap ?? '-',
                    '{kode_kavling}'     => $kodeKavlingGabungan,
                    '[[kode_kavling]]'   => $kodeKavlingGabungan,
                    '{no_kwitansi}'      => $item->no_kwitansi ?? '-',
                    '{nominal}'          => 'Rp. ' . number_format($item->nominal, 0, ',', '.'),
                    '{tanggal}'          => Carbon::parse($item->tanggal)->translatedFormat('d F Y'),
                    '{jenis}'            => $item->kategori->kategori ?? '-',
                ]);
                $item->tanggal          = Carbon::parse($item->tanggal)->translatedFormat('d F Y');
                $item->kategori         = $item->kategori->kategori ?? '-';
                $item->jumlah_formatted = '
                    <div class="d-flex justify-content-between">
                        <span>Rp.</span>
                        <span>' . number_format($item->nominal, 0, ',', '.') . '</span>
                    </div>';

                $action = '<div class="action-pemasukan"><form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">'
                . csrf_field()
                . method_field('DELETE')
                    . '<button type="submit" class="delete-pemasukan btn btn-danger btn-sm btn-action-xs">Hapus</button></form></div>';

                if (! in_array($item->id_kategori_transaksi, [4])) {
                    $action = '<div class="action-pemasukan">
                    <a class="btn btn-sm btn-primary btn-action-xs btn-cetak-pemasukan"
                        href="' . e($cetakUrl) . '"
                        target="_blank"
                        data-id="' . e($item->id) . '">Cetak</a>
                    <button type="button"
                        class="btn btn-sm btn-success btn-action-xs btn-kirim-pemasukan' . ($pdfFileExists ? '' : ' d-none') . '"
                        data-bs-toggle="modal"
                        data-bs-target="#modalKirimKwitansi"
                        data-id="' . e($item->id) . '"
                        data-nama="' . e($customer->nama_lengkap ?? '-') . '"
                        data-no-wa="' . e($customer->no_wa ?? $customer->no_telp ?? '') . '"
                        data-pesan="' . e($isiPesan) . '"
                        data-pdf-url="' . e($pdfUrl) . '">Kirim</button>
                    <form action="' . e($deleteUrl) . '" method="POST" style="display:inline;">'
                        . csrf_field()
                        . method_field('DELETE')
                        . '<button type="submit" class="delete-pemasukan btn btn-danger btn-sm btn-action-xs">Hapus</button></form>
                    </div>';
                }

                if (!\App\Services\ReceivablePayment::canManage(auth()->id(), 'pembayaran.index', 'edit')) {
                    $action = preg_replace('/<form\b[^>]*>.*?<\/form>/s', '', $action);
                    $action = preg_replace('/<button\b[^>]*btn-kirim-pemasukan[^>]*>.*?<\/button>/s', '', $action);
                }
                $item->action = $action;
            }

            $total = $data->sum('nominal');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('tanggal', fn($item) => $item->tanggal)
                ->addColumn('keterangan', fn($item) => $item->keterangan)
                ->addColumn('kategori', fn($item) => $item->kategori)
                ->addColumn('jumlah', fn($item) => $item->jumlah_formatted)
                ->addColumn('action', fn($item) => $item->action)
                ->with('total_pemasukan_formatted', number_format($total, 0, ',', '.'))
                ->rawColumns(['action', 'jumlah'])
                ->make(true);
        }
    }

    public function kirimKwitansi(Request $request)
    {
        $request->validate([
            'nama_customer' => 'required',
            'no_wa'         => 'required',
            'isi_pesan'     => 'required',
            'pdf_url'       => 'required|url',
        ], [
            'nama_customer.required' => 'Nama customer wajib diisi.',
            'no_wa.required'         => 'No WA wajib diisi.',
            'isi_pesan.required'     => 'Isi pesan wajib diisi.',
            'pdf_url.required'       => 'File PDF wajib tersedia.',
            'pdf_url.url'            => 'URL PDF tidak valid.',
        ]);

        $konfigurasiWa = KonfigurasiWa::first();

        $clientId = $konfigurasiWa ? ($this->extractClientIdFromUrl($konfigurasiWa->url_api) ?: $konfigurasiWa->number_key) : null;

        if (! $konfigurasiWa || ! $konfigurasiWa->url_api || ! $konfigurasiWa->api_key || ! $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan koneksi WA belum lengkap.',
            ], 422);
        }

        $phoneNo = $this->normalizePhoneNumber($request->no_wa);

        $textResponse = $this->sendWaRequest($this->resolveTextApiUrl($konfigurasiWa->url_api), [
            'clientId'   => $clientId,
            'to'         => $phoneNo,
            'text'       => $request->isi_pesan,
            'api_key'    => $konfigurasiWa->api_key,
            'number_key' => $konfigurasiWa->number_key,
            'phone_no'   => $phoneNo,
            'no_wa'      => $phoneNo,
            'message'    => $request->isi_pesan,
        ]);

        if (! $textResponse['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan gagal dikirim: ' . $textResponse['message'],
            ], 422);
        }

        $fileResponse = $this->sendWaRequest($this->resolveFileApiUrl($konfigurasiWa->url_api), [
            'clientId'    => $clientId,
            'to'          => $phoneNo,
            'phone_no'    => $phoneNo,
            'documentUrl' => $request->pdf_url,
            'fileName'    => basename(parse_url($request->pdf_url, PHP_URL_PATH) ?: 'kwitansi.pdf'),
            'caption'     => 'Kwitansi',
            'api_key'     => $konfigurasiWa->api_key,
            'number_key'  => $konfigurasiWa->number_key,
            'message'     => 'Kwitansi',
            'url'         => $request->pdf_url,
        ]);

        if (! $fileResponse['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan terkirim, tetapi PDF gagal dikirim: ' . $fileResponse['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesan dan PDF berhasil dikirim.',
        ]);
    }

    public function tambahPemasukan(Request $request, $id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pembayaran.index', 'edit'), 403);
        $value=(string)$request->nominal_bayar;
        if(preg_match('/^\d{1,3}(?:\.\d{3})+$/',$value)) $request->merge(['nominal_bayar'=>str_replace('.','',$value)]);
        $request->validate([
            'tanggal_pembayaran'=>'required|date_format:Y-m-d', 'nominal_bayar'=>'required|integer|min:1|max:2147483647',
            'id_bank'=>'required|integer|exists:bank,id', 'id_metode'=>'nullable|integer|exists:metode_bayar,id',
            'id_kategori_transaksi'=>'required|integer|in:25,26,27,28,102|exists:kategori_transaksi,id',
            'id_tagihan'=>'required_if:id_kategori_transaksi,102|nullable|integer|exists:piutang,id',
            'keterangan_pembayaran'=>'nullable|string|max:1000', 'file'=>'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:2048',
        ], [
            'file.max' => 'File bukti pembayaran terlalu besar. Maksimal 2 MB.',
            'file.uploaded' => 'Bukti pembayaran gagal diupload. Pastikan ukuran file maksimal 2 MB.',
            'file.mimes' => 'Bukti pembayaran harus berupa JPG, JPEG, PNG, WEBP, atau PDF.',
            'tanggal_pembayaran.required' => 'Tanggal pembayaran wajib diisi.',
            'id_bank.required' => 'Pilih rekening penerima.',
            'id_kategori_transaksi.required' => 'Pilih kategori transaksi.',
            'id_tagihan.required_if' => 'Pilih tagihan yang akan dibayar.',
            'nominal_bayar.required' => 'Nominal pembayaran wajib diisi.',
            'nominal_bayar.min' => 'Nominal pembayaran harus lebih dari nol.',
        ]);
        return \App\Services\FinanceFiles::withUpload($request,'file',['pemasukan'],function($filename) use($request,$id){
            app(\App\Services\ReceivablePayment::class)->paySales((int)$id,[
                'tanggal'=>$request->tanggal_pembayaran,'nominal'=>(int)$request->nominal_bayar,'id_bank'=>(int)$request->id_bank,
                'id_metode'=>$request->id_metode,'id_kategori_transaksi'=>(int)$request->id_kategori_transaksi,
                'id_tagihan'=>$request->id_tagihan,'keterangan'=>$request->keterangan_pembayaran,'lampiran'=>$filename,
            ]);
            return $this->paymentTotals((int)$id, 'Pembayaran berhasil dicatat.');
        });
    }
    public function DeletePemasukan($id)
    {
        abort_unless(\App\Services\ReceivablePayment::canManage(auth()->id(), 'pembayaran.index', 'edit'), 403);
        $payment=Pemasukan::findOrFail($id);
        abort_unless((bool)$payment->id_customer,422,'Transaksi ini bukan pembayaran customer.');
        app(\App\Services\ReceivablePayment::class)->cancel((int)$id,true);
        \App\Services\FinanceFiles::remove(['pemasukan'],$payment->lampiran);
        return $this->paymentTotals((int)$payment->id_customer,'Pembayaran berhasil dihapus.');
    }

    private function paymentTotals(int $customerId,string $message)
    {
        $query=Piutang::where('id_customer',$customerId);
        return response()->json(['success'=>true,'message'=>$message,
            'jumlah_bayar'=>number_format((clone $query)->sum('terbayar'),0,',','.'),
            'total_tagihan'=>number_format((clone $query)->sum('nominal'),0,',','.'),
            'sisa_bayar'=>number_format((clone $query)->sum('sisa_bayar'),0,',','.')]);
    }
    public function print($id)
    {
        return $this->cetak($id);
    }

    public function rekapPembayaran(Request $request)
    {
        $pembayaran = KategoriTransaksi::where('id', 4)->get();
        $lokasi     = LokasiKavling::orderBy('id', 'asc')->get();

        if ($request->ajax()) {
            $data = KavlingPeta::with(['customer', 'lokasi'])
                ->whereHas('lokasi', function ($q) use ($request) {
                    if ($request->lokasi_id) {
                        $q->where('id', $request->lokasi_id);
                    }
                })
                ->when($request->status == 1, function ($q) {
                    $q->whereHas('customer');
                })
                ->orderBy('kode_kavling', 'asc')
                ->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('customer', function ($row) {
                    return optional($row->customer)->nama_lengkap ?? '';
                })
                ->addColumn('lokasi', function ($row) {
                    $namaLokasi = optional($row->lokasi)->nama_kavling ?? '-';

                    if (optional($row->lokasi)->is_cluster == 1) {
                        $kodeKavling = $row->cluster . '-' . $row->no ?? '-';
                    } else {
                        $kodeKavling = $row->kode_kavling ?? '-';
                    }

                    return '<strong>' . $namaLokasi . '</strong><br> ' . $kodeKavling;
                })
                ->editColumn('hrg_jual', function ($row) {
                    return 'Rp. ' . number_format($row->hrg_jual, 0, ',', '.');
                })
                ->addColumn('pembayaran', function ($row) {
                    $customerId = optional($row->customer)->id;

                    if (! $customerId) {
                        return 'Rp. 0';
                    }

                    $jumlahBayar = Pemasukan::where('id_customer', $customerId)
                        ->where('keterangan', 'NOT LIKE', 'Biaya ganti nama%')
                        ->where('id_kategori_transaksi', '!=', 4)
                        ->sum('nominal');

                    return 'Rp. ' . number_format($jumlahBayar, 0, ',', '.');
                })
                ->addColumn('pencairan', function ($row) {
                    $customerId = optional($row->customer)->id;

                    if (! $customerId) {
                        return 'Rp. 0';
                    }

                    $pencairan = Pemasukan::where('id_customer', $customerId)
                        ->where('keterangan', 'NOT LIKE', 'Biaya ganti nama%')
                        ->where('id_kategori_transaksi', 4)
                        ->sum('nominal');

                    return 'Rp. ' . number_format($pencairan, 0, ',', '.');
                })
                ->addColumn('sbum', function ($row) {
                    $customerId = optional($row->customer)->id_customer;

                    if (! $customerId) {
                        return 'Rp. 0';
                    }

                    $sbum = Pemasukan::where('id_customer', $customerId)
                        ->whereNot('keterangan', 'GANTI NAMA')
                        ->where('id_jenis_pembayaran', 13)
                        ->sum('jumlah');

                    return 'Rp. ' . number_format($sbum, 0, ',', '.');
                })
                ->addColumn('sisa', function ($row) {
                    $customerId = optional($row->customer)->id;

                    if (! $customerId) {
                        return 'Rp. 0';
                    }

                    $totalTagihan = Piutang::where('id_customer', $customerId)->sum('nominal');
                    $jumlahBayar  = Pemasukan::where('id_customer', $customerId)
                        ->where('keterangan', 'NOT LIKE', 'Biaya ganti nama%')
                        ->sum('nominal');

                    $sisaBayar = $totalTagihan - $jumlahBayar;

                    return 'Rp. ' . number_format($sisaBayar, 0, ',', '.');
                })
                ->addColumn('action', function ($row) {
                    $customerId = optional($row->customer)->id;

                    $detailUrl = $customerId
                        ? route('pembayaran.show', $customerId)
                        : null;

                    $btn = '<div class="d-flex justify-content-center">';

                    if (! empty($customerId)) {
                        $btn .= '<button class="mx-1 btn btn-primary btn-sm bayar-button"
                        data-id="' . e($customerId) . '"
                        data-toggle="modal"
                        data-target="#modalForm">
                        Bayar
                    </button>';
                    } else {
                        $btn .= '<button class="mx-1 btn btn-secondary btn-sm" disabled>Bayar</button>';
                    }

                    if (! empty($customerId)) {
                        $btn .= '<a href="' . $detailUrl . '" class="btn btn-success btn-sm">Detail</a>';
                    } else {
                        $btn .= '<button class="btn btn-secondary btn-sm" disabled>Detail</button>';
                    }

                    $btn .= '</div>';

                    return $btn;
                })
                ->rawColumns(['action', 'lokasi'])
                ->make(true);
        }

        return view('admin.pembayaran.rekap', compact('pembayaran', 'lokasi'));
    }

}
