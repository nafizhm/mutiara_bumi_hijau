<!DOCTYPE html>
<html lang="en">
@php
    use Illuminate\Support\Facades\DB;

    $namaPerusahaan = DB::table('konfigurasi')->value('nama_perusahaan');

    $favicon = DB::table('konfigurasi_media')->where('jenis_data', 'fav icon')->value('nama_file');

    $logo = DB::table('konfigurasi_media')->where('jenis_data', 'logo website')->value('nama_file');
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $namaPerusahaan ?? 'Aplikasi Admin' }}</title>

    <link rel="shortcut icon" href="{{ asset('config_media/' . ($favicon ?? 'default-fav.png')) }}" type="image/x-icon">

    <link rel="stylesheet"
        href="{{ asset('template/assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}">

    <link rel="stylesheet" href="{{ asset('template/assets/extensions/choices.js/public/assets/styles/choices.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('template/assets/compiled/css/table-datatable-jquery.css') }}">
    {{-- <link rel="stylesheet" href="{{ asset('template/assets/extensions/rater-js/lib/style.css') }}"> --}}
    <link rel="stylesheet" href="{{ asset('template/assets/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('template/assets/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('template/assets/compiled/css/iconly.css') }}">

    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('template/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('template/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('template/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">

    <!-- Toastr -->
    <link rel="stylesheet" href="{{ asset('template/plugins/toastr/toastr.min.css') }}">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="{{ asset('template/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        body.light .user-info-text .username {
            color: #fff important;
        }

        body.light .user-info-text .role {
            color: #fff !important;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        body.dark .select2-container--default .select2-selection--single {
            background-color: #1e1e2d;
            color: #fff;
            border: 1px solid #444;
        }

        body.dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff;
        }

        body.dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #aaa;
        }

        body.dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #fff transparent transparent transparent;
        }

        body.dark .select2-container--default .select2-results__options {
            padding-left: 0 !important;
            margin: 0;
        }

        body.dark .select2-container--default .select2-results__option {
            text-align: left;
            padding: 6px 12px;
            color: #fff;
        }

        body.dark .select2-container--default .select2-results__option--highlighted[aria-selected],
        body.dark .select2-container--default .select2-results__option--highlighted[aria-selected]:hover {
            background-color: #444;
            color: #fff;
        }

        body.dark .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: #333;
            color: #fff;
        }

        body.dark .select2-dropdown {
            background-color: #2c2f33;
            color: #fff;
            border-color: #444;
            z-index: 9999;
        }

        body.dark .select2-container--default .select2-selection--multiple {
            background-color: #1e1e2d;
            border: 1px solid #444;
            min-height: 38px;
            position: relative;
            padding-right: 45px;
        }

        body.dark .select2-container--default.select2-container--focus .select2-selection--multiple {
            border: 1px solid #444;
            outline: none;
        }

        body.dark .select2-container--default .select2-selection--multiple::after {
            border-top-color: #ffffff;
        }

        body.dark .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #2c2f33;
            border: 1px solid #444;
            color: #fff;
            padding: 2px 6px;
            margin-top: 5px;
            display: inline-flex;
            flex-direction: row-reverse;
            align-items: center;
        }

        body.dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: static !important;
            color: #ccc;
            margin-right: 0;
            margin-left: 6px;
            border-right: none;
            border-left: 1px solid #444;
            padding-right: 0;
            padding-left: 6px;
            float: none;
        }

        body.dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #fff;
            background-color: transparent;
        }

        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 6px 12px;
            font-size: 1rem;
            background-color: #fff;
        }

        .select2-container .select2-selection__arrow {
            display: none;
        }

        .select2-container .select2-selection--single:focus {
            outline: none;
        }

        .select2-container .select2-selection__rendered {
            line-height: 30px;
        }

        .select2-container--default .select2-selection--single {
            position: relative;
            padding-right: 36px;
        }

        .select2-container--default .select2-selection--single::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 12px;
            width: 0;
            height: 0;
            pointer-events: none;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #6c757d;
            transform: translateY(-50%);
        }

        body.dark .select2-container--default .select2-selection--single::after {
            border-top-color: #ffffff;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            margin: 0;
            padding: 0;
            z-index: 2;
        }

        .select2-container--default .select2-selection--single {
            padding-right: 52px;
        }

        .select2-container--default .select2-selection--multiple {
            position: relative;
            padding-right: 45px;
            min-height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding-left: 8px;
            font-size: 1rem;
            background-color: #fff;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border: 1px solid #ced4da;
            outline: none;
        }

        .select2-container--default .select2-selection--multiple::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 12px;
            width: 0;
            height: 0;
            pointer-events: none;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #6c757d;
            transform: translateY(-50%);
        }

        .select2-container--default .select2-selection--multiple .select2-selection__clear {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            margin: 0;
            padding: 0;
            z-index: 2;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            color: #495057;
            border-radius: 0.2rem;
            padding: 2px 6px;
            margin-top: 5px;
            display: inline-flex;
            flex-direction: row-reverse;
            align-items: center;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: static !important;
            color: #495057;
            margin-right: 0;
            margin-left: 6px;
            border-right: none;
            border-left: 1px solid #ced4da;
            padding-right: 0;
            padding-left: 6px;
            float: none;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #212529;
            background-color: transparent;
        }

        .select2-container--default .select2-selection--multiple .select2-search--inline {
            display: inline-flex;
            align-items: center;
            width: 100%;
        }

        .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
            margin-top: 6px;
            line-height: inherit;
            width: 100% !important;
            box-sizing: border-box;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice~.select2-search--inline {
            display: none !important;
        }

        .preview-wrapper {
            position: relative;
            width: 100%;
        }

        .preview-item-container {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease;
        }

        .preview-item-container:hover {
            transform: scale(1.02);
        }

        .preview-item-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-preview {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: background-color 0.2s;
            line-height: 0;
        }

        .remove-preview i {
            font-weight: 900;
            font-size: 1.1rem;
            -webkit-text-stroke: 1px white;
            margin-top: 1px;
        }

        .remove-preview:hover {
            background-color: #dc2626;
        }

        #main-navbar {
            background-color: white !important;
            color: #333;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.dark #main-navbar {
            background-color: #1e1e2d !important;
            color: #eee;
        }

        body.dark #main-navbar .nav-link {
            color: #ccc;
        }

        body.dark #main-navbar .nav-link.active,
        body.dark #main-navbar .nav-link:hover {
            color: #0d6efd;
        }

        .main-navbar {
            position: relative;
            z-index: 1000;
        }

        .dropdown-menu {
            top: 100% !important;
            left: auto !important;
            right: 0 !important;
        }

        input[readonly] {
            background-color: #e9ecef !important;
            color: #212529 !important;
        }


        @media (max-width: 768px) {
            #main-menu {
                display: none !important;
                flex-direction: column;
                background-color: #fff;
                padding: 10px 0;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
            }

            #main-menu.show {
                display: flex !important;
            }

            body.dark #main-menu {
                background-color: #1e1e2d;
            }

            .main-navbar {
                position: relative;
            }
        }

        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .fixed-header-nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 767.98px) {
            .fixed-header {
                height: 80px;
            }

            .fixed-header-nav {
                top: 80px;
            }
        }

        .rating-star i {
            font-size: 2rem;
            color: gray;
            cursor: pointer;
            margin: 0 15px;
            transition: color 0.1s;
        }

        .rating-star i.active {
            color: gold;
        }

        @media (min-width: 992px) {
            .rating-star i {
                margin: 0 25px;
            }
        }

        .card.border-danger {
            border: 2px solid #dc3545 !important;
        }

        .card.border-danger.shadow {
            box-shadow: 0 0 10px rgba(220, 53, 69, .6) !important;
        }

        #full-width {
            margin: 2rem
        }

        #main-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 70px;
            background-color: #1e1e2d;
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        body.dark #main-navbar {
            background-color: #1e1e2d;
        }

        body.light #main-navbar {
            background-color: #ffffff;
        }

        body {
            overflow-y: auto !important;
        }

        #main {
            /* margin-top: 60px; */
            min-height: calc(100vh - 60px) !important;
        }

        #sidebar {
            z-index: 100;
            position: relative;
        }

        .notification-dropdown {
            position: fixed !important;
            top: 50px !important;
            right: 50px;
            min-width: 300px;
            max-height: 350px;
            overflow-y: auto;
            transform: translateX(-50px) !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.30);
        }

        /* Desktop */
        @media (min-width: 992px) {
            .notification-dropdown {
                transform: translateX(-170px) !important;
                max-height: 500px;
            }
        }

        #profileDropdown {
            display: flex;
            align-items: center;
        }

        #profileDropdown::after {
            margin-left: 5px;
            margin-top: 0;
        }

        #profileDropdown+.dropdown-menu {
            box-shadow:
                0 10px 25px rgba(0, 0, 0, 0.22),
                0 4px 10px rgba(0, 0, 0, 0.18);
        }

        .navbar-shadow {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            background-color: #fff;
        }

        .submenu.tree-submenu {
            position: relative !important;
            padding-left: 15px !important;
        }

        .submenu.tree-submenu .submenu-item {
            position: relative !important;
            padding-left: 10px !important;
            display: flex;
            align-items: center;
        }

        .submenu.tree-submenu .submenu-item::before {
            content: "";
            position: absolute;
            left: 8px;
            top: -12px;
            bottom: 0;
            width: 0;
            border-left: 2px dashed rgba(187, 187, 187, 0.6) !important;
        }

        .submenu.tree-submenu .submenu-item:last-child::before {
            bottom: 10%;
        }
    </style>

</head>

<body class="{{ session('theme') === 'dark' ? 'theme-dark' : '' }}">

    <script src="{{ asset('template/assets/static/js/initTheme.js') }}"></script>
    <div id="app">
        <div id="sidebar">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="index.html"><img
                                    src="{{ asset('config_media/' . ($logo ?? 'default-logo.png')) }}" alt="Logo"
                                    style="width:60px; height:auto;">
                            </a>
                        </div>
                        <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                aria-hidden="true" role="img" class="iconify iconify--system-uicons" width="20"
                                height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                                <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4zM4.136 4.136L5.55 5.55m9.9 9.9l1.414 1.414M1.5 10.5h2m14 0h2M4.135 16.863L5.55 15.45m9.899-9.9l1.414-1.415M10.5 19.5v-2m0-14v-2"
                                        opacity=".3"></path>
                                    <g transform="translate(-210 -1)">
                                        <path d="M220.5 2.5v2m6.5.5l-1.5 1.5"></path>
                                        <circle cx="220.5" cy="11.5" r="4"></circle>
                                        <path d="m214 5l1.5 1.5m5 14v-2m6.5-.5l-1.5-1.5M214 18l1.5-1.5m-4-5h2m14 0h2">
                                        </path>
                                    </g>
                                </g>
                            </svg>
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input me-0" type="checkbox" id="toggle-dark"
                                    style="cursor: pointer" />
                                <label class="form-check-label"></label>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                aria-hidden="true" role="img" class="iconify iconify--mdi" width="20"
                                height="20" preserveAspectRatio="xMidYMid meet" viewBox="0 0 24 24">
                                <path fill="currentColor"
                                    d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z">
                                </path>
                            </svg>
                        </div>
                        <div class="sidebar-toggler x">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i
                                    class="bi bi-x bi-middle"></i></a>
                        </div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">

                        @php
                            use Illuminate\Support\Str;
                            $getmenus = collect(session('getmenus', []))->filter(function ($menu) {
                                return !Str::startsWith($menu->route_name ?? '', ['prospek.', 'prospekData.', 'pengaturanLanding.', 'pengaturan-media.index']);
                            })->map(function ($menu) {
                                if ($menu->relationLoaded('children')) {
                                    $menu->setRelation('children', $menu->children->filter(function ($child) {
                                        return !Str::startsWith($child->route_name ?? '', ['prospek.', 'prospekData.', 'pengaturanLanding.', 'pengaturan-media.index']);
                                    }));
                                }
                                return $menu;
                            });
                            $activityMenu = \App\Models\Menu::where('route_name', 'activity-logs.index')
                                ->whereHas('hakAkses', fn ($q) => $q->where('id_user', auth()->id())->where('lihat', 1))->first();
                            if ($activityMenu && !$getmenus->contains('route_name', 'activity-logs.index')) {
                                $getmenus->push($activityMenu);
                            }
                            $currentRoute = request()->route()->getName();
                            if (auth()->user()->canApproveChanges()) {
                                $approvalSettingsMenu = new \App\Models\Menu([
                                    'title' => 'Pengaturan Persetujuan', 'route_name' => 'approval-settings.index',
                                    'icon' => 'bi bi-check2-square', 'urutan' => 999,
                                ]);
                                $settingsParent = $getmenus->first(fn ($item) => Str::contains(strtolower($item->title ?? ''), 'pengaturan'));
                                if ($settingsParent) {
                                    $settingsParent->setRelation('children', $settingsParent->children
                                        ->reject(fn ($child) => $child->route_name === 'approval-settings.index')->push($approvalSettingsMenu));
                                } else {
                                    $settingsParent = new \App\Models\Menu(['title' => 'Pengaturan', 'route_name' => '#', 'icon' => 'bi bi-gear']);
                                    $settingsParent->setRelation('children', collect([$approvalSettingsMenu]));
                                    $getmenus->push($settingsParent);
                                }
                            }
                            $tahunAjaranList = session('tahunAjaranList');
                        @endphp

                        @foreach ($getmenus as $menu)
                            @if ($menu->title === 'WhatsApp' || $menu->icon === 'bi bi-whatsapp')
                                @continue
                            @endif
                            @if ($menu->title === 'Beranda' || $menu->route_name === 'beranda')
                                @continue
                            @endif
                            @php
                                $hasChildren = isset($menu->children) && count($menu->children) > 0;
                                $childCount = $hasChildren ? count($menu->children) : 0;

                                $activeRoutes = $hasChildren
                                    ? $menu->children->pluck('route_name')->toArray()
                                    : [$menu->route_name];

                                $isActive = collect($activeRoutes)->contains(function ($route) use ($currentRoute) {
                                    return Str::startsWith($currentRoute, Str::before($route, '.'));
                                });
                            @endphp

                            @if ($hasChildren && $childCount === 1)
                                @php
                                    $child = $menu->children->first();
                                    $isActiveSingle = Str::startsWith(
                                        $currentRoute,
                                        Str::before($child->route_name, '.'),
                                    );
                                @endphp

                                <li class="sidebar-item {{ $isActiveSingle ? 'active' : '' }}">
                                    <a href="{{ route($child->route_name) }}" class="sidebar-link">
                                        <i class="{{ $menu->icon }}"></i>
                                        <span>{{ $child->title }}</span>
                                    </a>
                                </li>
                                @continue
                            @endif

                            @if ($hasChildren)
                                <li class="sidebar-item has-sub {{ $isActive ? 'active' : '' }}">
                                    <a href="#" class="sidebar-link">
                                        <i class="{{ $menu->icon }}"></i>
                                        <span>{{ $menu->title }}</span>
                                    </a>
                                    <ul class="submenu tree-submenu">
                                        @foreach ($menu->children->sortBy('urutan') as $submenu)
                                            @if ($submenu->title === 'Beranda' || $submenu->route_name === 'beranda')
                                                @continue
                                            @endif
                                            <li
                                                class="submenu-item {{ Str::startsWith($currentRoute, Str::before($submenu->route_name, '.')) ? 'active' : '' }}">
                                                <a href="{{ route($submenu->route_name) }}" class="submenu-link">
                                                    {{ $submenu->title }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                @if ($menu->route_name === '#')
                                    @continue
                                @endif

                                <li
                                    class="sidebar-item {{ Str::startsWith($currentRoute, Str::before($menu->route_name, '.')) ? 'active' : '' }}">
                                    <a href="{{ route($menu->route_name) }}" class="sidebar-link">
                                        <i class="{{ $menu->icon }}"></i>
                                        <span>{{ $menu->title }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                        <li class="sidebar-item">
                            <a href="#" onclick="logoutConfirm(event)" class="sidebar-link">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                            <form id="logout-form" action="{{ route('admin.logout') }}" method="POST"
                                style="display: none;">
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            @yield('content')

        </div>
    </div>

    <script src="{{ asset('template/assets/static/js/components/dark.js') }}"></script>
    <script src="{{ asset('template/assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>

    <script src="{{ asset('template/assets/compiled/js/app.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{-- <!-- Need: Apexcharts -->
    <script src="{{ asset('template/assets/extensions/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('template/assets/static/js/pages/dashboard.js') }}"></script> --}}

    <script src="{{ asset('template/assets/extensions/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('template/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('template/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('template/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('template/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <script src="{{ asset('template/assets/static/js/pages/datatables.js') }}"></script>


    <script src="{{ asset('template/assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    {{-- <script src="{{ asset('template/assets/extensions/rater-js/index.js?v=2') }}"></script> --}}
    {{-- <script src="{{ asset('template/assets/static/js/pages/rater-js.js') }}"></script> --}}
    <script src="{{ asset('template/assets/static/js/pages/form-element-select.js') }}"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Toastr -->
    <script src="{{ asset('template/plugins/toastr/toastr.min.js') }}"></script>
    <!-- SweetAlert2 -->
    <script src="{{ asset('template/plugins/sweetalert2/sweetalert2.min.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleDesktop = document.getElementById('toggle-dark');
            const toggleMobile = document.getElementById('toggle-dark-mobile');
            const navbar = document.getElementById('main-navbar');

            function updateTheme(isDark) {
                if (isDark) {
                    document.body.classList.add('dark');
                    document.body.classList.remove('light');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark');
                    document.body.classList.add('light');
                    localStorage.setItem('theme', 'light');
                }

                if (navbar) {
                    navbar.classList.toggle('navbar-dark', isDark);
                    navbar.classList.toggle('bg-dark', isDark);
                    navbar.classList.toggle('navbar-light', !isDark);
                    navbar.classList.toggle('bg-light', !isDark);
                }

                if (toggleDesktop) toggleDesktop.checked = isDark;
                if (toggleMobile) toggleMobile.checked = isDark;
            }

            const savedTheme = localStorage.getItem('theme') || 'light';
            updateTheme(savedTheme === 'dark');

            if (toggleDesktop) {
                toggleDesktop.addEventListener('change', function() {
                    updateTheme(toggleDesktop.checked);
                });
            }

            if (toggleMobile) {
                toggleMobile.addEventListener('change', function() {
                    updateTheme(toggleMobile.checked);
                });
            }
        });
    </script>

    <script>
        var audio = new Audio('{{ asset('audio/notification.ogg') }}');

        $(document).on('input', '.format-number', function() {
            let input = $(this).val().replace(/[^\d]/g, '');
            let formatted = input.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            $(this).val(formatted);
        });

        function logoutConfirm(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda akan keluar dari sistem.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('{{ route('refresh.csrf') }}')
                        .then(response => response.json())
                        .then(data => {
                            let form = document.getElementById('logout-form')
                            let tokenField = form.querySelector('input[name="_token"]')
                            if (!tokenField) {
                                tokenField = document.createElement('input')
                                tokenField.type = 'hidden'
                                tokenField.name = '_token'
                                form.appendChild(tokenField)
                            }
                            tokenField.value = data.token
                            form.submit()
                        })
                }
            });
        }
    </script>

    <script src="{{ asset('js/select2_multiple.js') }}"></script>

    @include('admin.approvals.reason-script')
    @stack('scripts')

</body>

</html>
