@extends('admin.layout')
@section('content')
    <style>
        .card-custom {
            width: 300px;
            height: 50px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #333;

            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            gap: 10px;
            font-size: 16px;
            padding: 0 auto;

            margin: 10px 0;
        }


        .card-custom:hover {
            border-color: #6610f2;
            box-shadow: 0 3px 10px rgb(102 16 242 / 0.3);
        }

        .card-custom i {
            font-size: 16px !important;
            line-height: 1;
        }

        .card-custom strong {
            font-size: 14px;
            line-height: 1.2;
            text-align: center;
            color: inherit;
        }

        .content-header h4,
        .content-header h5 {
            color: #333;
            transition: color 0.3s ease;
        }

        /* Icon Colors - Light Theme */
        .icon-orange {
            color: #f09000;
        }

        .icon-green {
            color: #28a745;
        }

        .icon-red {
            color: #dc3545;
        }

        .icon-blue {
            color: #007bff;
        }

        .icon-gray {
            color: #6c757d;
        }

        .icon-info {
            color: #17a2b8;
        }

        .icon-yellow {
            color: #ffc107;
        }

        .icon-purple {
            color: #6f42c1;
        }

        /* Dark Theme Styles */
        .theme-dark .card-custom {
            background: #2d3748;
            border: 1px solid #4a5568;
            color: #e2e8f0;
        }

        .theme-dark .card-custom:hover {
            border-color: #805ad5;
            box-shadow: 0 3px 10px rgb(128 90 213 / 0.4);
            background: #374151;
        }

        .theme-dark .content-header h4,
        .theme-dark .content-header h5 {
            color: #f7fafc;
        }

        /* Dark Theme Icon Colors - Lebih terang agar kontras */
        .theme-dark .icon-orange {
            color: #fbb040;
        }

        .theme-dark .icon-green {
            color: #48bb78;
        }

        .theme-dark .icon-red {
            color: #f56565;
        }

        .theme-dark .icon-blue {
            color: #4299e1;
        }

        .theme-dark .icon-gray {
            color: #a0aec0;
        }

        .theme-dark .icon-info {
            color: #63b3ed;
        }

        .theme-dark .icon-yellow {
            color: #ffd700;
        }

        .theme-dark .icon-purple {
            color: #9f7aea;
        }

        /* Page content background */
        .theme-dark .page-content {
            background-color: #1a202c;
            color: #e2e8f0;
        }

        /* Smooth transitions untuk semua elemen */
        .content-header h4,
        .content-header h5,
        .card-custom,
        .page-content {
            transition: all 0.3s ease;
        }
    </style>

    <div class="page-content">
        <section class="content-header">
            <div class="container-fluid text-center my-4">
                <h5>Halo, <strong>{{ $username }}</strong></h5>
                <h4><strong>Aktivitas apa yang ingin Anda lakukan?</strong></h4>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid d-flex justify-content-center">
                <div class="row" style="max-width: 900px;">

                    @php
                        $userId = auth()->id();

                        $hakAkses = \App\Models\HakAkses::with('menu')
                            ->where('id_user', $userId)
                            ->where('lihat', 1)
                            ->where('beranda', 1)
                            ->get();

                        $colors = [
                            'icon-red',
                            'icon-blue',
                            'icon-green',
                            'icon-yellow',
                            'icon-orange',
                            'icon-purple',
                            'icon-gray',
                            'icon-info',
                        ];
                    @endphp

                    @foreach ($hakAkses as $akses)
                        @if ($akses->menu)
                            @php
                                $randomColor = $colors[array_rand($colors)];

                                // Jika menu adalah sub-menu (id_parent != 0), gunakan icon dari parent
                                $menuIcon = $akses->menu->icon;
                                if ($akses->menu->id_parent != 0) {
                                    $parentMenu = \App\Models\Menu::find($akses->menu->id_parent);
                                    if ($parentMenu) {
                                        $menuIcon = $parentMenu->icon;
                                    }
                                }
                            @endphp

                            <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center">
                                <div class="card-custom d-flex align-items-center"
                                    onclick="window.location='{{ route($akses->menu->route_name) }}'" role="button"
                                    tabindex="0"
                                    onkeypress="if(event.key === 'Enter'){ window.location='{{ route($akses->menu->route_name) }}'}">

                                    <!-- Icon di kiri (gunakan icon parent jika sub-menu) -->
                                    <i class="{{ $menuIcon }} {{ $randomColor }}" style="min-width:20px;"></i>

                                    <!-- Teks di kanan icon -->
                                    <strong class="text-center">{{ $akses->menu->title }}</strong>
                                </div>
                            </div>
                        @endif
                    @endforeach

                </div>
            </div>
        </section>
    </div>
@endsection
