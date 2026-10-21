<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\KavlingPeta;
use App\Models\ListPenjualan;
use App\Models\LokasiKavling;
use App\Models\Marketing;
use App\Models\Pemasukan;
use App\Models\TemplatePesan;
use App\Models\TransaksiKavling;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login')->withError('Silahkan Login terlebih dahulu');
        }

        $dateNow = Carbon::now()
            ->locale('id')
            ->timezone('Asia/Jakarta')
            ->translatedFormat('d F Y');

        $jumlahKavling = KavlingPeta::count();
        $sudahLaku     = TransaksiKavling::count();
        $sisaLaku      = $jumlahKavling - $sudahLaku;

        $customers = Customer::with(['piutangs', 'pemasukans'])->where('stt_arsip', 0)->orderBy('id', 'desc')->get();

        $kolomStatus = ListPenjualan::where('status_progres', '!=', 'HOLD')
            ->where('stt_tampil', 1)
            ->orderBy('urutan', 'asc')
            ->get();

        $dataLokasi = LokasiKavling::whereIn('stt_tampil', [1, 3])
            ->orderByRaw("
                CASE
                    WHEN nama_kavling LIKE 'SIRANDU 1' THEN 1
                    WHEN nama_kavling LIKE 'SIRANDU 2' THEN 2
                    ELSE 3
                END
            ")
            ->get()
            ->map(function ($lokasi) use ($kolomStatus) {
                $id = $lokasi->id;

                $countKavlingBy = function ($callback) use ($id) {
                    $query = Customer::where('stt_arsip', 0)->whereHas('kavling', function ($q) use ($id) {
                        $q->where('id_lokasi', $id);
                    });

                    $callback($query);

                    return $query->withCount(['kavling' => function ($q) use ($id) {
                        $q->where('id_lokasi', $id);
                    }])->get()->sum('kavling_count');
                };

                $data = [
                    'id'           => $id,
                    'nama'         => $lokasi->nama_kavling,
                    'nama_singk_1' => $lokasi->nama_singkat,
                    'jumlah'       => KavlingPeta::where('id_lokasi', $id)->count(),
                    'cash'         => $countKavlingBy(fn($q) => $q->whereIn('jenis_pembelian', ['Cash Keras', 'Cash Bertahap'])),
                    'kredit'       => $countKavlingBy(fn($q) => $q->where('jenis_pembelian', 'Kredit')),
                    'hold'         => $countKavlingBy(fn($q) => $q->where('id_status_progres', 19)),
                ];

                foreach ($kolomStatus as $status) {
                    $key        = strtolower(str_replace(' ', '_', $status->short_name));
                    $data[$key] = $countKavlingBy(fn($q) => $q->where('id_status_progres', $status->id));
                }

                return $data;
            });

        $totalLokasi = [
            'jumlah' => 0,
            'hold'   => 0,
            'cash'   => 0,
            'kredit' => 0,
        ];

        foreach ($kolomStatus as $status) {
            $key               = strtolower(str_replace(' ', '_', $status->short_name));
            $totalLokasi[$key] = 0;
        }

        foreach ($dataLokasi as $lokasi) {
            $totalLokasi['jumlah'] += $lokasi['jumlah'];
            $totalLokasi['hold']   += $lokasi['hold'];
            $totalLokasi['cash']   += $lokasi['cash'];
            $totalLokasi['kredit'] += $lokasi['kredit'];

            foreach ($kolomStatus as $status) {
                $key                = strtolower(str_replace(' ', '_', $status->short_name));
                $totalLokasi[$key] += $lokasi[$key] ?? 0;
            }
        }

        $totalData = 0;

        foreach ($dataLokasi as $lokasi) {
            $totalData += ($lokasi['bf'] ?? 0);
        }

        $totalCustomer = Customer::where('stt_arsip', 0)->count();

        $progresList = ListPenjualan::all();
        $dataProgres = [];

        $noProgres = 1;
        foreach ($progresList as $progres) {
                    $jumlah     = Customer::where('stt_arsip', 0)->where('id_status_progres', $progres->id)->count();
            $persentase = $totalCustomer > 0 ? round(($jumlah / $totalCustomer) * 100) : 0;

            $dataProgres[] = [
                'no'                => $noProgres++,
                'status_progres'    => $progres->status_progres,
                'jumlah'            => $jumlah,
                'persentase'        => $persentase,
                'id_status_progres' => $progres->id,
            ];
        }

        $marketingList  = Marketing::all();
        $dataMarketing  = [];
        $marketingFilters = $this->marketingFilters($request);
        $marketingCustomers = $this->filterMarketingCustomers(Customer::where('stt_arsip', 0), $marketingFilters);
        $totalMarketingCustomers = (clone $marketingCustomers)->count();
        $marketingCounts = (clone $marketingCustomers)->selectRaw('id_marketing, COUNT(*) as total')
            ->groupBy('id_marketing')->pluck('total', 'id_marketing');
        $marketingLocations = LokasiKavling::orderBy('urutan')->orderBy('nama_kavling')->get();
        $marketingYears = TransaksiKavling::whereNotNull('tgl_terima')->pluck('tgl_terima')
            ->map(fn ($date) => (int) substr($date, 0, 4))
            ->push(now('Asia/Jakarta')->year)->filter()->unique()->sortDesc()->values();

        $noMarketing = 1;
        foreach ($marketingList as $marketing) {
            $jumlah     = (int) ($marketingCounts[$marketing->id] ?? 0);
            $persentase = $totalMarketingCustomers > 0 ? round(($jumlah / $totalMarketingCustomers) * 100) : 0;

            $dataMarketing[] = [
                'no'           => $noMarketing++,
                'marketing'    => $marketing->nama_marketing,
                'jumlah'       => $jumlah,
                'persentase'   => $persentase,
                'id_marketing' => $marketing->id,
            ];
        }

        $marketingList = Marketing::all();

        $currentYear = Carbon::now()->year;

        $years = collect(range($currentYear, $currentYear - 5));

        return view('admin.dashboard.index', compact(
            'dateNow',
            'jumlahKavling',
            'sudahLaku',
            'sisaLaku',
            'kolomStatus',
            'dataLokasi',
            'totalLokasi',
            'totalData',
            'dataProgres',
            'dataMarketing',
            'marketingFilters',
            'marketingLocations',
            'marketingYears',
            'marketingList',
            'years'
        ));
    }

    public function totalUnit(Request $request)
    {
        if ($request->ajax()) {
            $data = KavlingPeta::select(
                'kavling_peta.*',
                'lokasi_kavling.nama_kavling as nama_cluster'
            )
                ->leftJoin('lokasi_kavling', 'kavling_peta.id_lokasi', '=', 'lokasi_kavling.id');

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('panjang', function ($row) {
                    return '
                        <p>pjg kanan: <strong>' . $row->panjang_kanan . ' m</strong></p>
                        <p>pjg kiri: <strong>' . $row->panjang_kiri . ' m</strong></p>
                    ';
                })
                ->addColumn('lebar', function ($row) {
                    return '
                        <p>lebar depan: <strong>' . $row->lebar_depan . ' m</strong></p>
                        <p>lebar belakang: <strong>' . $row->lebar_belakang . ' m</strong></p>
                    ';
                })
                ->addColumn('luas', function ($row) {
                    return '
                        <p>luas tanah: <strong>' . $row->luas_tanah . ' m</strong></p>
                        <p>luas bangunan: <strong>' . $row->luas_bangunan . ' m</strong></p>
                    ';
                })
                ->addColumn('harga', function ($row) {
                    return number_format($row->hrg_jual, 0, ',', '.');
                })
                ->addColumn('lokasi', function ($row) {
                    return $row->kode_kavling;
                })
                ->rawColumns(['panjang', 'lebar', 'luas'])
                ->make(true);
        }

        return view('admin.dashboard.totalUnit');
    }

    public function booking(Request $request)
    {
        if ($request->ajax()) {
            $data = Customer::with(['lokasi', 'progres'])
                ->where('stt_arsip', 0)
                ->whereIn('id_status_progres', [1, 22]);

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('id_lokasi', function ($row) {
                    return $row->lokasi ? $row->lokasi->nama_kavling : '-';
                })
                ->addColumn('id_status_progres', function ($row) {
                    return $row->progres ? $row->progres->status_progres : '-';
                })
                ->make(true);
        }

        return view('admin.dashboard.booking');
    }

    public function akad(Request $request)
    {
        if ($request->ajax()) {
            $data = Customer::with(['lokasi', 'progres'])
                ->where('stt_arsip', 0)
                ->whereIn('id_status_progres', [3]);

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('id_lokasi', function ($row) {
                    return $row->lokasi ? $row->lokasi->nama_kavling : '-';
                })
                ->addColumn('id_status_progres', function ($row) {
                    return $row->progres ? $row->progres->status_progres : '-';
                })
                ->make(true);
        }

        return view('admin.dashboard.akad');
    }

    public function lunasUnit(Request $request)
    {
        if ($request->ajax()) {

            $customers = Customer::with(['lokasi', 'progres', 'kavling', 'piutangs', 'pemasukans'])
                ->where('stt_arsip', 0)
                ->orderBy('id', 'desc')
                ->get();

            $data = $customers->filter(function ($row) {
                $totalTagihan = $row->piutangs->sum('nominal');

                $totalBayar = $row->pemasukans
                    ->where('keterangan', '!=', 'GANTI NAMA')
                    ->sum('nominal');

                $sisa = $totalTagihan - $totalBayar;

                return $sisa == 0 && $totalTagihan > 0;
            });

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('nama_lengkap', fn($row) => $row->nama_lengkap ?? '')
                ->addColumn('perumahan', fn($row) => $row->lokasi->nama_kavling ?? '')

                ->addColumn('kode_kavling', function ($row) {
                    $namaLokasi          = $row->lokasi->nama_kavling ?? '-';
                    $kodeKavlingGabungan = $row->kavling->pluck('kode_kavling')->implode(', ');

                    return '<strong>' . $namaLokasi . '</strong><br> ' . ($kodeKavlingGabungan ?: '-');
                })

                ->addColumn('harga_jual', function ($row) {
                    $totalHargaJual = $row->kavling->sum(function ($kav) {
                        return $kav->pivot->hrg_rumah;
                    });
                    return number_format($totalHargaJual, 0, ',', '.');
                })

                ->addColumn('tanggal_lunas', function ($row) {
                    $totalTagihan       = $row->piutangs->sum('nominal');
                    $pemasukansFiltered = $row->pemasukans->where('keterangan', '!=', 'GANTI NAMA');
                    $totalBayar         = $pemasukansFiltered->sum('nominal');

                    if ($totalTagihan > 0 && $totalTagihan <= $totalBayar) {
                        $lastPayment = $pemasukansFiltered->sortByDesc('tanggal')->first();

                        return $lastPayment ? date('d-m-Y', strtotime($lastPayment->tanggal)) : '';
                    }
                    return '';
                })
                ->rawColumns(['kode_kavling'])
                ->make(true);
        }

        return view('admin.dashboard.lunas');
    }

    public function showLokasiPenjualan(string $id)
    {
        $getName  = LokasiKavling::where('id', $id)->first();
        $viewData = [
            'lokasi_id' => $id,
            'scope'     => 'Lokasi Kavling',
            'nama'      => $getName->nama_kavling ?? '-',
        ];

        $query = Customer::with(['progres', 'marketing', 'kavling'])
            ->where('stt_arsip', 0)
            ->whereHas('kavling', function ($q) use ($id) {
                $q->where('id_lokasi', $id);
            });

        if (request()->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('id_status_progres', function ($row) {
                    return $row->progres->status_progres ?? '-';
                })
                ->editColumn('nama_lengkap', function ($row) {
                    return $row->nama_lengkap ?? '-';
                })
                ->editColumn('id_marketing', function ($row) {
                    return $row->marketing->nama_marketing ?? '-';
                })
                ->addColumn('kode_kavling', function ($row) use ($id) {
                    $kavlings = $row->kavling->where('id_lokasi', $id);
                    return $kavlings->pluck('kode_kavling')->implode(', ') ?: '-';
                })
                ->rawColumns(['kode_kavling'])
                ->make(true);
        }

        return view('admin.dashboard.statistik.lokasi', $viewData);
    }

    public function showCustomer(string $id)
    {
        $routeName = request()->route()->getName();
        $getName   = [];
        $viewData  = [];
        $query     = Customer::where('stt_arsip', 0);

        if ($routeName === 'dashboard.customer-status-progres-show') {
            $query->where('id_status_progres', $id);
            $getName  = ListPenjualan::where('id', $id)->first();
            $viewData = [
                'status_progres_id' => $id,
                'scope'             => 'Progres Penjualan',
                'nama'              => $getName->status_progres ?? '-',
            ];
        } elseif ($routeName === 'dashboard.customer-marketing-show') {
            $this->filterMarketingCustomers($query, $this->marketingFilters(request()));
            $query->where('id_marketing', $id);
            $getName  = Marketing::where('id', $id)->first();
            $viewData = [
                'marketing_id' => $id,
                'scope'        => 'Marketing',
                'nama'         => $getName->nama_marketing ?? '-',
            ];
        }

        if (request()->ajax()) {
            Carbon::setLocale('id');

            $program = $query->with(['marketing', 'lokasi', 'kavling', 'progres']);

            return DataTables::of($program)
                ->addIndexColumn()
                ->editColumn('tgl_terima', function ($row) {
                    $tgl  = $row->tgl_terima ? Carbon::parse($row->tgl_terima)->translatedFormat('d F Y') : '-';
                    $kode = $row->kode_customer ? '<strong>' . $row->kode_customer . '</strong>' : '';
                    return "$tgl<br>$kode";
                })
                ->editColumn('id_marketing', function ($row) {
                    $namaMarketing  = $row->marketing->nama_marketing ?? '<span class="badge bg-danger">None Marketing</span>';
                    $namaFreelance  = $row->freelance->nama_freelance ?? null;
                    $freelanceBadge = $namaFreelance ? '<br><span class="badge bg-info">' . $namaFreelance . '</span>' : '';

                    return $namaMarketing . $freelanceBadge;
                })
                ->editColumn('id_lokasi', function ($row) {
                    $namaLokasi  = $row->lokasi->nama_kavling ?? '-';
                    $kodeKavling = $row->kavling->pluck('kode_kavling')->implode(', ');

                    return '<strong>' . $namaLokasi . '</strong><br>' . ($kodeKavling ?: '-');
                })
                ->editColumn('id_status_progres', function ($row) {
                    $status      = $row->progres->status_progres ?? '-';
                    $ketCashback = $row->ket_cashback ?? '';

                    $badgeColors = [
                        'BF'    => 'warning',
                        'AKAD'  => 'info',
                        'HOLD'  => 'dark',
                        'LUNAS' => 'success',
                    ];

                    $badgeClass    = $badgeColors[$status] ?? 'secondary';
                    $statusDisplay = '<span class="badge bg-' . $badgeClass . '">' . $status . '</span>';

                    $cashbackText = $ketCashback ? '<br><small>' . $ketCashback . '</small>' : '';
                    return $statusDisplay . $cashbackText;
                })
                ->editColumn('nama_lengkap', function ($row) {
                    $nama = '<strong>' . $row->nama_lengkap . '</strong>';
                    $wa   = $row->no_wa ?? '-';
                    $ktp  = $row->no_ktp ? '<span class="badge bg-info">NIK: ' . $row->no_ktp . '</span>' : '';

                    return "$nama<br>$wa<br>$ktp";
                })
                ->rawColumns([
                    'tgl_terima',
                    'nama_lengkap',
                    'id_marketing',
                    'id_lokasi',
                    'id_status_progres',
                    'kode_kavling',
                ])
                ->make(true);
        }

        return view('admin.dashboard.statistik.customer', $viewData);
    }

    private function marketingFilters(Request $request): array
    {
        return $request->validate([
            'marketing_year' => 'nullable|integer|min:0|max:9999',
            'marketing_location' => 'nullable|integer|min:0',
        ]);
    }

    private function filterMarketingCustomers($query, array $filters)
    {
        $year = (int) ($filters['marketing_year'] ?? 0);
        $location = (int) ($filters['marketing_location'] ?? 0);
        if ($year || $location) {
            $query->where(function ($query) use ($year, $location) {
                $query->whereHas('kavling', function ($lots) use ($year, $location) {
                    if ($year) $lots->whereYear('transaksi_kavling.tgl_terima', $year);
                    if ($location) $lots->where('kavling_peta.id_lokasi', $location);
                });
                if ($location && !$year) {
                    $query->orWhere(fn ($legacy) => $legacy->whereDoesntHave('kavling')->where('id_lokasi', $location));
                }
            });
        }
        return $query;
    }

    public function chart1(Request $request)
    {
        return $this->monthlySales($request);
    }

    public function chart2(Request $request)
    {
        return $this->monthlySales($request, true);
    }

    private function monthlySales(Request $request, bool $filterMarketing = false)
    {
        $request->validate([
            'tahun' => 'nullable|integer|min:0|max:9999',
            'marketing' => 'nullable|integer|min:0',
        ]);
        $query = TransaksiKavling::whereNotNull('tgl_terima')
            ->whereHas('customer', function ($query) use ($request, $filterMarketing) {
                $query->where('stt_arsip', 0);
                if ($filterMarketing && $request->integer('marketing') > 0) {
                    $query->where('id_marketing', $request->integer('marketing'));
                }
            });
        if ($request->integer('tahun') > 0) {
            $query->whereYear('tgl_terima', $request->integer('tahun'));
        }
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', tgl_terima) AS INTEGER)"
            : 'MONTH(tgl_terima)';
        $counts = $query->selectRaw($month . ' as bulan, COUNT(*) as total')
            ->groupByRaw($month)->pluck('total', 'bulan');

        return response()->json(collect(range(1, 12))->map(fn ($month) => (int) ($counts[$month] ?? 0)));
    }
    public function detailPenjualan(Request $request)
    {
        try {

            if ($request->ajax()) {

                $data = KavlingPeta::query()
                    ->leftJoin('transaksi_kavling', 'transaksi_kavling.id_kavling', '=', 'kavling_peta.id')
                    ->leftJoin('customer', function ($join) {
                        $join->on('customer.id', '=', 'transaksi_kavling.id_customer')
                            ->where('customer.stt_arsip', 0);
                    })
                    ->leftJoin(
                        DB::raw('(SELECT id_customer, SUM(nominal) as total_bayar FROM pemasukan GROUP BY id_customer) as bayar'),
                        'bayar.id_customer',
                        '=',
                        'customer.id'
                    )
                    ->select(
                        'kavling_peta.kode_kavling',
                        'kavling_peta.hrg_jual',
                        'customer.nama_lengkap as customer',
                        DB::raw('transaksi_kavling.hrg_rumah as harga_laku'),
                        DB::raw('bayar.total_bayar as sudah_terbayar'),
                        DB::raw('(COALESCE(transaksi_kavling.hrg_rumah,0) - COALESCE(bayar.total_bayar,0)) as belum_terbayar')
                    );

                return DataTables::of($data)
                    ->addIndexColumn()
                    ->editColumn('harga_jual', function ($row) {
                        return '
                        <div class="d-flex justify-content-between harga-format w-100">
                            <span>Rp.</span>
                            <span>' . number_format($row->hrg_jual ?? 0, 0, ',', '.') . '</span>
                        </div>';
                    })
                    ->editColumn('harga_laku', function ($row) {
                        if (! $row->harga_laku) {
                            return '';
                        }

                        return '
                        <div class="d-flex justify-content-between harga-format w-100">
                            <span>Rp.</span>
                            <span>' . number_format($row->harga_laku, 0, ',', '.') . '</span>
                        </div>';
                    })
                    ->editColumn('sudah_terbayar', function ($row) {
                        if (! $row->sudah_terbayar) {
                            return '';
                        }

                        return '
                        <div class="d-flex justify-content-between harga-format w-100">
                            <span>Rp.</span>
                            <span>' . number_format($row->sudah_terbayar, 0, ',', '.') . '</span>
                        </div>';
                    })
                    ->editColumn('belum_terbayar', function ($row) {
                        if (! $row->harga_laku) {
                            return '';
                        }

                        return '
                        <div class="d-flex justify-content-between harga-format w-100">
                            <span>Rp.</span>
                            <span>' . number_format($row->belum_terbayar, 0, ',', '.') . '</span>
                        </div>';
                    })
                    ->rawColumns(['harga_jual', 'harga_laku', 'sudah_terbayar', 'belum_terbayar'])
                    ->with([
                        'total_harga_jual'     => (clone $data)->sum('kavling_peta.hrg_jual'),
                        'total_harga_laku'     => (clone $data)->sum('transaksi_kavling.hrg_rumah'),
                        'total_sudah_terbayar' => (clone $data)->sum('bayar.total_bayar'),
                        'total_belum_terbayar' => (clone $data)->sum(DB::raw('(COALESCE(transaksi_kavling.hrg_rumah,0) - COALESCE(bayar.total_bayar,0))')),
                    ])
                    ->make(true);
            }

            $lokasiList = LokasiKavling::all();

            return view('admin.dashboard.detail_penjualan', compact('lokasiList'));

        } catch (\Throwable $e) {

            Log::error('Error detailPenjualan', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            if ($request->ajax()) {
                return response()->json(['error' => 'Terjadi kesalahan pada server'], 500);
            }

            abort(500);
        }
    }

    public function statistikCicilanCustomer()
    {
        Carbon::setLocale('id');

        $today = Carbon::today();
        $customers = Customer::with([
            'kavling.lokasi',
            'piutangs',
            'pemasukans',
            'marketing',
        ])
            ->where('stt_arsip', 0)
            ->orderBy('nama_lengkap')
            ->get();

        $skipped = [
            'tanpa_kavling'       => 0,
            'tanpa_tanggal_beli'  => 0,
            'tanpa_nilai_tagihan' => 0,
        ];

        $templateTerlambat = TemplatePesan::whereIn('jenis_pesan', ['terlambat', 'jatuh tempo', 'jatuh_tempo'])
            ->orderByRaw("FIELD(jenis_pesan, 'terlambat', 'jatuh tempo', 'jatuh_tempo')")
            ->first();
        $templatePesan = $templateTerlambat->isi_template ?? 'Yth. {nama_customer}, pembayaran cicilan kavling {kode_kavling} terlambat {jumlah_terlambat} kali. Total yang harus dibayar Rp {total_tunggakan}. Mohon segera melakukan pembayaran. Terima kasih.';

        $rows = collect();

        foreach ($customers as $customer) {
            if ($customer->kavling->isEmpty()) {
                $skipped['tanpa_kavling']++;
                continue;
            }

            $tanggalBeli = $customer->kavling
                ->pluck('pivot.tgl_terima')
                ->filter()
                ->sort()
                ->first();

            if (! $tanggalBeli) {
                $skipped['tanpa_tanggal_beli']++;
                continue;
            }

            $hargaJual = $customer->kavling->sum(fn($kavling) => (float) ($kavling->pivot->hrg_rumah ?? $kavling->hrg_jual ?? 0));
            $totalTagihan = (float) $customer->piutangs->sum('nominal');

            if ($totalTagihan <= 0 && $hargaJual <= 0) {
                $skipped['tanpa_nilai_tagihan']++;
                continue;
            }

            $nilaiKontrak = $totalTagihan > 0 ? $totalTagihan : $hargaJual;
            $sudahDibayar = (float) $customer->pemasukans
                ->where('keterangan', '!=', 'GANTI NAMA')
                ->sum('nominal');
            $sisaBayar = max($nilaiKontrak - $sudahDibayar, 0);
            $tanggalBeliCarbon = Carbon::parse($tanggalBeli)->startOfDay();
            $tenor = (int) ($customer->inhouse_tenor ?: 0);
            $bulanBerjalan = (int) ceil(max(1, $tanggalBeliCarbon->floatDiffInMonths($today) + 1));

            if ($tenor > 0) {
                $bulanBerjalan = min($bulanBerjalan, $tenor);
            }

            $angsuranPerBulan = (float) ($customer->inhouse_perbulan ?: 0);
            $bulanTerbayar = $angsuranPerBulan > 0
                ? min($tenor > 0 ? $tenor : $bulanBerjalan, (int) floor($sudahDibayar / $angsuranPerBulan))
                : 0;

            $terlambatKali = max($bulanBerjalan - $bulanTerbayar, 0);
            $totalTunggakan = $angsuranPerBulan > 0
                ? min($sisaBayar, $terlambatKali * $angsuranPerBulan)
                : $sisaBayar;

            if ($sisaBayar <= 0) {
                $status = 'Lunas';
                $terlambatKali = 0;
                $totalTunggakan = 0;
            } elseif ($sudahDibayar <= 0) {
                $status = 'Belum Bayar';
            } elseif ($terlambatKali > 0) {
                $status = 'Terlambat';
            } else {
                $status = 'Tepat';
                $totalTunggakan = 0;
            }

            $jatuhTempoDay = $customer->inhouse_jatuh_tempo
                ? Carbon::parse($customer->inhouse_jatuh_tempo)->day
                : 10;
            $jatuhTempoBulanIni = $today->copy()->day(min($jatuhTempoDay, $today->daysInMonth));
            $kodeKavling = $customer->kavling->pluck('kode_kavling')->filter()->implode(', ');
            $namaLokasi = $customer->kavling
                ->map(fn($kavling) => $kavling->lokasi->nama_kavling ?? null)
                ->filter()
                ->unique()
                ->implode(', ');
            $marketing = $customer->marketing->nama_marketing ?? '-';
            $progressPercent = $nilaiKontrak > 0 ? min(100, round(($sudahDibayar / $nilaiKontrak) * 100)) : 0;
            $pesanTerlambat = strtr($templatePesan, [
                '{nama}'              => $customer->nama_lengkap ?? '-',
                '{nama_customer}'     => $customer->nama_lengkap ?? '-',
                '[[nama_customer]]'   => $customer->nama_lengkap ?? '-',
                '{no_wa}'             => $customer->no_wa ?: $customer->no_telp ?: '-',
                '{kode_kavling}'      => $kodeKavling ?: '-',
                '[[kode_kavling]]'    => $kodeKavling ?: '-',
                '{lokasi}'            => $namaLokasi ?: '-',
                '{jumlah_terlambat}'  => $terlambatKali,
                '{total_tunggakan}'   => number_format($totalTunggakan, 0, ',', '.'),
                '{total_harus_bayar}' => number_format($totalTunggakan, 0, ',', '.'),
                '{sisa_bayar}'        => number_format($sisaBayar, 0, ',', '.'),
                '{angsuran_perbulan}' => number_format($angsuranPerBulan, 0, ',', '.'),
            ]);

            $rows->push([
                'customer'            => $customer,
                'nama_customer'       => $customer->nama_lengkap,
                'no_wa'               => $customer->no_wa ?: $customer->no_telp,
                'kode_kavling'        => $kodeKavling ?: '-',
                'lokasi'              => $namaLokasi ?: '-',
                'marketing'           => $marketing,
                'tanggal_beli'        => $tanggalBeliCarbon,
                'jatuh_tempo'         => $jatuhTempoBulanIni,
                'bulan_berjalan'      => $bulanBerjalan,
                'bulan_terbayar'      => $bulanTerbayar,
                'tenor'               => $tenor,
                'terlambat_kali'      => $terlambatKali,
                'total_tunggakan'     => $totalTunggakan,
                'harga_jual'          => $hargaJual,
                'nilai_kontrak'       => $nilaiKontrak,
                'sudah_dibayar'       => $sudahDibayar,
                'sisa_bayar'          => $sisaBayar,
                'status'              => $status,
                'pesan_terlambat'     => $pesanTerlambat,
                'progress_percent'    => $progressPercent,
                'progress_bar_class'  => $progressPercent < 50 ? 'danger' : ($progressPercent < 80 ? 'warning' : ''),
            ]);
        }

        $totalCustomer = $rows->count();
        $totalPiutang = $rows->sum('sisa_bayar');
        $summary = [
            'total_customer' => $totalCustomer,
            'tepat'          => $rows->where('status', 'Tepat')->count(),
            'terlambat'      => $rows->where('status', 'Terlambat')->count(),
            'belum_bayar'    => $rows->where('status', 'Belum Bayar')->count(),
            'lunas'          => $rows->where('status', 'Lunas')->count(),
            'total_piutang'  => $totalPiutang,
        ];

        $lokasiList = $rows->pluck('lokasi')->filter(fn($lokasi) => $lokasi !== '-')->unique()->sort()->values();
        $marketingList = $rows->pluck('marketing')->filter(fn($marketing) => $marketing !== '-')->unique()->sort()->values();

        return view('admin.dashboard.statistik_cicilan_customer', compact(
            'rows',
            'summary',
            'skipped',
            'lokasiList',
            'marketingList'
        ));
    }

}
