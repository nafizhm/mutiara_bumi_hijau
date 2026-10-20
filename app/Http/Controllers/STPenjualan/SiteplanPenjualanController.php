<?php

namespace App\Http\Controllers\STPenjualan;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FotoKavling;
use App\Models\KavlingPeta;
use App\Models\KonfigurasiMedia;
use App\Models\ListPenjualan;
use App\Models\ListrikAir;
use App\Models\LokasiKavling;
use App\Models\MasterSVG;
use App\Models\UploadFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use TCPDF;

class SiteplanPenjualanController extends Controller
{
    public function index()
    {
        $lokasiKavling = LokasiKavling::whereIn('stt_tampil', [1, 3])
            ->orderBy('urutan', 'asc')
            ->get();

        $legend = ListPenjualan::whereNotNull('warna')
            ->where('warna', '!=', '')
            ->where('stt_tampil', '!=', 0)
            ->orderBy('urutan', 'asc')
            ->get();

        return view('admin.st_penjualan.index', compact('lokasiKavling', 'legend'));
    }

    public function loadSiteplan($id_lokasi)
    {
        try {
            $lokasi = LokasiKavling::where('id', $id_lokasi)
                ->whereIn('stt_tampil', [1, 3])
                ->first();

            if (!$lokasi) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Lokasi tidak ditemukan atau tidak diizinkan tampil',
                ]);
            }

            $lokasi->masterSvg = MasterSVG::where('id_lokasi', $lokasi->id)->first();

            // Eager load customer untuk menghindari N+1 Query
            $lokasi->kavlingPeta = KavlingPeta::with(['customer' => function ($q) {
                    $q->where('stt_arsip', 0);
                }])
                ->where('id_lokasi', $lokasi->id)
                ->get();

            foreach ($lokasi->kavlingPeta as $kavling) {
                // LOGIKA BARU: Ambil customer pertama dari relasi many-to-many
                // Asumsi: Satu kavling pada satu waktu hanya dimiliki 1 customer aktif
                $owner = $kavling->customer->first();

                // Kita timpa relasi 'customer' di objek ini agar View blade tidak bingung
                // (View mengharapkan objek/null, bukan Collection)
                if ($owner) {
                    // Set manual properti customer dengan data owner
                    $kavling->setRelation('customer', $owner);

                    // Ambil progres berdasarkan status customer
                    $kavling->progres = ListPenjualan::where('id', $owner->id_status_progres)->first();
                } else {
                    // Kosongkan relasi jika tidak ada owner
                    $kavling->setRelation('customer', null);
                    $kavling->progres = null;
                }

                // Logika registrasi (Jika menggunakan logika hold/booking lama)
                // Menggunakan owner yang sama
                $kavling->registrasi = $owner;
            }

            $html = view('admin.st_penjualan.siteplan_content', compact('lokasi'))->render();

            return response()->json([
                'success' => true,
                'html'    => $html,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ]);
        }
    }

    public function detail($id)
    {
        $kavling = KavlingPeta::with(['lokasi', 'customer' => function ($q) {
                $q->where('stt_arsip', 0);
            }])->where('id', $id)->first();

        $customer = $kavling->customer->first();

        $fileUrls = [];
        $files = collect([]);

        if ($customer) {
            $files = UploadFile::where('id_customer', $customer->id)->get();

            foreach ($files as $file) {
                $fileType = str_replace(' ', '_', strtolower($file->nama_file));
                $fileUrls[$fileType] = $file->lampiran ?
                    url('berkas_user', $file->lampiran) : null;
            }
        }

        $listrikAir = ListrikAir::where('id_kavling', $id)->first();

        if ($listrikAir) {
            $listrikAir->foto_listrik_url = $listrikAir->foto_listrik ?
                route('listrik-air.foto-listrik', $listrikAir->foto_listrik) : null;

            $listrikAir->foto_listrik_2_url = $listrikAir->foto_listrik_2 ?
                route('listrik-air.foto-listrik-2', $listrikAir->foto_listrik_2) : null;

            $listrikAir->foto_air_url = $listrikAir->foto_air ?
                route('listrik-air.foto-air', $listrikAir->foto_air) : null;

            $listrikAir->foto_air_2_url = $listrikAir->foto_air_2 ?
                route('listrik-air.foto-air-2', $listrikAir->foto_air_2) : null;
        }

        unset($kavling->customer);

        return response()->json([
            'success'    => true,
            'kavling'    => $kavling,
            'customer'   => $customer,
            'files'      => $fileUrls,
            'listrikAir' => $listrikAir,
            'lokasi'     => $kavling && $kavling->lokasi ? $kavling->lokasi->nama_kavling : '-',
        ]);
    }

    public function cetak(Request $request)
    {
        $kavling = KavlingPeta::with(['lokasi', 'customer'])
            ->where('kode_kavling', $request->kode_kavling)
            ->where('id_lokasi', $request->lokasi)
            ->first();

        if (!$kavling) {
            return abort(404, 'Data Kavling tidak ditemukan');
        }

        $namaLokasi = $kavling->lokasi ? $kavling->lokasi->nama_kavling : '-';

        $cust = $kavling->customer->where('no_ktp', $request->no_ktp)->first();

        if ($cust) {
            $cust->load('marketing');
        }

        $listrikAir = ListrikAir::where('id_kavling', $kavling->id)->first();

        $kavlingData = [
            'status'        => $request->status ?? 'Terjual',
            'marketing'     => $cust && $cust->marketing ? $cust->marketing->nama_marketing : '-',
            'lokasi'        => $namaLokasi,
            'blok'          => $request->blok ?? $request->kode_kavling ?? '-',
            'luas_tanah'    => $request->luas_tanah ?? '-',
            'harga'         => $request->harga ? (int) $request->harga : 0,
            'no_sertifikat' => $request->no_sertifikat ?? '-',
            'foto'          => null,
        ];

        $customerData = [
            'nama'          => $request->nama_customer ?? '-',
            'no_ktp'        => $request->no_ktp ?? '-',
            'tempat_lahir'  => $request->tempat_lahir ?? '-',
            'tanggal_lahir' => $request->tgl_lahir ? \Carbon\Carbon::parse($request->tgl_lahir)->translatedFormat('d F Y') : '-',
            'alamat'        => $request->alamat ?? '-',
            'no_hp'         => $request->no_hp ?? '-',
            'pekerjaan'     => $request->pekerjaan ?? '-',
        ];


        // --- PENGATURAN PDF (TCPDF) ---
        $pengaturanMedia = KonfigurasiMedia::where('jenis_data', 'kop surat')->first();
        $logoPath        = null;
        if ($pengaturanMedia && $pengaturanMedia->nama_file) {
            $logoPath = public_path('config_media/' . $pengaturanMedia->nama_file);
        }

        $pdf = new TCPDF('P', 'mm', 'A4');
        $pdf->SetCreator('Sistem Kavling');
        $pdf->SetAuthor('Sistem Kavling');
        $pdf->SetTitle('Data Unit - ' . $kavlingData['blok']);
        $pdf->SetMargins(15, 45, 15);
        $pdf->AddPage();

        $tanggalCetak = \Carbon\Carbon::now()->translatedFormat('d F Y');

        // Header Gambar
        if ($logoPath && file_exists($logoPath)) {
            $pdf->Image($logoPath, 0, 5, 210);
        }

        $pdf->SetY(35);

        // Header Teks
        $pdf->SetFont('times', '', 10);
        $pdf->Cell(0, 8, 'Tanggal Cetak : ' . $tanggalCetak, 0, 1, 'R');
        $pdf->Ln(3);

        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetFont('times', 'BU', 14);
        $pdf->Cell(0, 4, 'DATA UNIT TANAH KAVLING', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('times', '', 8);
        $pdf->Cell(0, 2, 'Status Tanah Kavling : ' . $kavlingData['status'], 0, 1, 'C');
        $pdf->Cell(0, 2, 'Marketing : ' . $kavlingData['marketing'], 0, 1, 'C');
        $pdf->Ln(5);

        // Section 1: Data Unit
        $pdf->SetFont('times', 'BU', 12);
        $pdf->Cell(0, 8, 'DATA UNIT TANAH KAVLING', 0, 1, 'L');
        $pdf->SetFont('times', '', 10);
        $this->rowData($pdf, 'Lokasi Perumahan', $namaLokasi);
        $this->rowData($pdf, 'No. Blok / Kav', $kavlingData['blok']);
        $this->rowData($pdf, 'Luas Tanah', $kavlingData['luas_tanah']);
        $this->rowData($pdf, 'Harga Jual', 'Rp ' . number_format($kavlingData['harga'], 0, ',', '.'));
        $pdf->Ln(2);

        // Section 2: Data Legal
        $pdf->SetFont('times', 'BU', 12);
        $pdf->Cell(0, 8, 'DATA LEGAL', 0, 1, 'L');
        $pdf->SetFont('times', '', 10);
        $this->rowData($pdf, 'ID Kavling', $kavling->id_rumah_sikumbang ?? '-');
        $this->rowData($pdf, 'No. Sertipikat', $kavlingData['no_sertifikat']);
        $pdf->Ln(2);

        // Section 3: Data Customer
        $pdf->SetFont('times', 'BU', 12);
        $pdf->Cell(0, 8, 'DATA CUSTOMER', 0, 1, 'L');
        $pdf->SetFont('times', '', 10);
        $this->rowData($pdf, 'Nama Lengkap', $customerData['nama']);
        $this->rowData($pdf, 'No. KTP', $customerData['no_ktp']);
        $this->rowData($pdf, 'Tempat / Tgl Lahir', $customerData['tempat_lahir'] . ', ' . $customerData['tanggal_lahir']);
        $this->rowData($pdf, 'Alamat KTP', $customerData['alamat']);
        $this->rowData($pdf, 'No. Telp / WA', $customerData['no_hp']);
        $this->rowData($pdf, 'Pekerjaan', $customerData['pekerjaan']);
        $pdf->Ln(2);

        // Section 4: Foto (Placeholder)
        $pdf->SetFont('times', 'BU', 12);
        $pdf->Cell(0, 8, 'LAMPIRAN FOTO DATA UNIT TANAH KAVLING', 0, 1, 'L');
        $pdf->SetFont('times', 'I', 10);
        $pdf->Cell(0, 10, 'Foto tidak tersedia', 0, 1, 'L');

        $pdf->Output('Data_Unit_' . ($kavlingData['blok'] ?? 'unknown') . '.pdf', 'I');
    }

    private function rowData($pdf, $label, $value)
    {
        $pdf->SetFont('times', 'B', 10);
        $pdf->Cell(60, 7, $label, 0, 0, 'L');
        $pdf->SetFont('times', '', 10);
        $pdf->MultiCell(0, 7, ': ' . $value, 0, 'L', false, 1);
    }

    public function foto($id)
    {
        $fotoKavling = FotoKavling::where('id_kavling', $id)->get();

        $html = '';

        if ($fotoKavling->count() > 0) {
            foreach ($fotoKavling as $foto) {
                $html .= '<div class="col-md-3 mb-3 text-center">';
                $html .= '<img src="' . asset($foto->lampiran) . '" class="img-fluid mb-2" alt="Foto" style="max-height:200px; object-fit:cover; border:1px solid #ddd; padding:5px;">';
                $html .= '<div><strong>' . $foto->keterangan . '</strong></div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>Tidak ada foto untuk kavling ini.</p>';
        }

        return response()->json([
            'status' => 'success',
            'html'   => $html,
        ]);
    }

    public function tagihan($id_kavling)
    {
        $nasabahIds = Customer::where('id_kavling', $id_kavling)
            ->pluck('id_customer');

        if ($nasabahIds->isEmpty()) {
            return response()->json([
                'status'      => 'success',
                'tagihan'     => [],
                'pembayaran'  => [],
                'pengeluaran' => [],
                'message'     => 'Tidak ada nasabah untuk kavling ini',
            ]);
        }

        return response()->json([
            'status'      => 'success',
            'tagihan'     => 0,
            'pembayaran'  => 0,
            'pengeluaran' => 0,
            'debug'       => [
                'id_kavling' => $id_kavling,
                'nasabahIds' => $nasabahIds,
            ],
        ]);
    }

    public function cetakJPG($id_lokasi)
    {
        $svgContent = $this->generateSVG($id_lokasi);

        $svgFilename = "siteplan_{$id_lokasi}.svg";

        $svgPath = public_path("svg/{$svgFilename}");
        if (! file_exists(dirname($svgPath))) {
            mkdir(dirname($svgPath), 0755, true);
        }
        file_put_contents($svgPath, $svgContent);

        $endpoint   = 'https://aplikasikavling.com/convert/proses.php';
        $clientName = 'miliarder_group';

        $response = Http::attach(
            'svg_file',
            file_get_contents($svgPath),
            $svgFilename
        )->post($endpoint, [
            'client' => $clientName,
        ]);

        if (! $response->successful()) {
            abort(500, "Gagal upload ke server convert: " . $response->body());
        }

        $data = $response->json();
        if (! isset($data['jpg_url'])) {
            abort(500, "Gagal convert ke JPG: " . json_encode($data));
        }

        $jpgUrl = $data['jpg_url'];

        $jpgContent  = Http::get($jpgUrl)->body();
        $jpgFilename = basename(parse_url($jpgUrl, PHP_URL_PATH));

        $jpgPath = public_path("hasil/{$jpgFilename}");
        if (! file_exists(dirname($jpgPath))) {
            mkdir(dirname($jpgPath), 0755, true);
        }
        file_put_contents($jpgPath, $jpgContent);

        return response()->download($jpgPath);
    }

    private function generateSVG($id_lokasi, $width = '100%', $height = '100%')
    {
        // 1. Eager Loading 'kavlingPeta.customer' untuk performa (Pivot Table)
        $lokasi = LokasiKavling::with(['masterSvg', 'kavlingPeta.customer'])->findOrFail($id_lokasi);

        if (!$lokasi->masterSvg) {
            abort(404, "Data master_svg tidak ditemukan");
        }

        // 2. Ambil List Warna Progres Sekaligus (Optimasi Query)
        // Format: [id_progres => 'warna', ...]
        $listWarna = \App\Models\ListPenjualan::pluck('warna', 'id')->toArray();

        ob_start();

        // Render Header SVG
        echo str_replace(['[[lebar]]', '[[tinggi]]'], [$width, $height], $lokasi->masterSvg->header_svg);

        // 3. Loop Kavling
        foreach ($lokasi->kavlingPeta as $pt) {
            $warna = '#ffffff'; // Default Putih (Available)

            // Ambil Customer Pertama dari Relasi Pivot
            // Karena Many-to-Many, $pt->customer adalah Collection. Kita ambil first().
            $owner = $pt->customer->first();

            if ($owner) {
                // Cek apakah punya status progres dan warnanya ada di list
                if ($owner->id_status_progres && isset($listWarna[$owner->id_status_progres])) {
                    $warna = $listWarna[$owner->id_status_progres];
                }
                // Fallback Logic (Logic Registrasi lama)
                else {
                    if ($owner->stt_reg == 0) {
                        $warna = '#00ffff'; // Warna Cyan (Booking Sementara/Draft)
                    } elseif (in_array($owner->stt_reg, [1, 2, 3])) {
                        $warna = '#ffffff';
                    }
                }
            }

            // Tentukan Jenis Shape SVG (Polygon / Path)
            $svg_code = $pt->jenis_map === 'polygon' ? $lokasi->masterSvg->polygon_svg : $lokasi->masterSvg->path_svg;

            // Render Shape Kavling
            echo str_replace(
                ['[[1]]', '[[2]]', '[[3]]', '[[4]]'],
                [$pt->map, $warna, $pt->matrik, $pt->kode_kavling],
                $svg_code
            );
        }

        // Render Footer SVG
        echo $lokasi->masterSvg->footer_svg;

        return ob_get_clean();
    }

    public function cetakPDF($id_lokasi)
    {
        $lokasi = DB::table('lokasi_kavling')->where('id', $id_lokasi)->first();
        $namaKavling = $lokasi->nama_kavling ?? '-';
        $periodeCetak = now()->translatedFormat('d F Y');

        $svgContent = $this->generateSVG($id_lokasi);
        $svgFilename = "siteplan_{$id_lokasi}.svg";
        $svgPath = public_path("svg/{$svgFilename}");

        if (! file_exists(dirname($svgPath))) {
            mkdir(dirname($svgPath), 0755, true);
        }

        file_put_contents($svgPath, $svgContent);

        $endpoint = 'https://aplikasikavling.com/convert/proses.php';
        $clientName = 'tanah_kavling';
        $response = Http::attach('svg_file', file_get_contents($svgPath), $svgFilename)
            ->post($endpoint, ['client' => $clientName]);

        if (! $response->successful()) {
            abort(500, "Gagal upload ke server convert: " . $response->body());
        }

        $data = $response->json();
        if (! isset($data['jpg_url'])) {
            abort(500, "Gagal convert ke JPG: " . json_encode($data));
        }

        $jpgUrl = $data['jpg_url'];
        $jpgContent = Http::get($jpgUrl)->body();
        $jpgFilename = basename(parse_url($jpgUrl, PHP_URL_PATH));
        $jpgPath = public_path("hasil/{$jpgFilename}");

        if (! file_exists(dirname($jpgPath))) {
            mkdir(dirname($jpgPath), 0755, true);
        }

        file_put_contents($jpgPath, $jpgContent);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle("Site Plan Penjualan - {$namaKavling}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $mediaKop = KonfigurasiMedia::where('jenis_data', 'kop surat')->first();
        $kopSuratPath = null;

        if ($mediaKop && $mediaKop->nama_file) {
            $cekPath = public_path('config_media/' . $mediaKop->nama_file);
            if (file_exists($cekPath)) {
                $kopSuratPath = $cekPath;
            }
        }

        if ($kopSuratPath) {
            $pdf->Image($kopSuratPath, 0, 0, 210);
        } else {
            $logoPath = public_path('assets/img/header.png');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 10, 10, 25);
            }
        }

        $pdf->SetY(35);

        $pdf->SetFont('Times', 'B', 14);
        $pdf->Cell(0, 6, 'SITE PLAN PENJUALAN ' . strtoupper($namaKavling), 0, 1, 'C');

        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(0, 6, 'Periode Cetak : ' . $periodeCetak, 0, 1, 'C');

        $pdf->Ln(5);

        list($width_orig, $height_orig) = getimagesize($jpgPath);

        $finalWidth = ($width_orig > $height_orig) ? 190 : 70;
        $posisiX = (210 - $finalWidth) / 2;

        $pdf->Image($jpgPath, $posisiX, $pdf->GetY(), $finalWidth, 0, 'JPG');

        $pdf->Output("siteplan_{$namaKavling}.pdf", 'I');
    }
}
