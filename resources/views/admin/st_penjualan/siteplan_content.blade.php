<a href="{{ route('denah-penjualan.cetak.pdf', $lokasi->id) }}" target="_blank" class="btn btn-warning btn-sm">
    Cetak Denah PDF
</a>
<a href="{{ route('denah-penjualan.cetak.jpg', $lokasi->id) }}" target="_blank" class="btn btn-info btn-sm">
    Download Denah JPG
</a>

<div class="svg-container mt-3">
    <button class="reset-button btn btn-success btn-sm d-none"
        style="position: absolute; top: 10px; left: 10px; z-index: 10;">
        Reset Siteplan
    </button>

    {{-- SVG Header --}}
    @if ($lokasi->masterSvg)
        {!! str_replace(['[[lebar]]', '[[tinggi]]'], ['100%', '100%'], $lokasi->masterSvg->header_svg) !!}
    @endif

    {{-- Loop kavling --}}
    @foreach ($lokasi->kavlingPeta as $pt)
        @php
            $warna = '#ffffff';
            if ($pt->customer) {
                if ($pt->progres) {
                    $warna = $pt->progres->warna;
                }
            } else {
                if ($pt->registrasi) {
                    if ($pt->registrasi->stt_reg == 0) {
                        $warna = '#00ffff';
                    } elseif (in_array($pt->registrasi->stt_reg, [1, 2, 3])) {
                        $warna = '#ffffff';
                    }
                }
            }
        @endphp

        @php $svg_code = $pt->jenis_map === 'polygon' ? $lokasi->masterSvg->polygon_svg : $lokasi->masterSvg->path_svg; @endphp

        <a href="javascript:void(0);" data-id="{{ $pt->id }}"
            data-url="{{ route('st-penjualan.detail', $pt->id) }}" data-bs-toggle="modal"
            data-bs-target="#modalDetail" class="detail-button">
            {!! str_replace(
                ['[[1]]', '[[2]]', '[[3]]', '[[4]]'],
                [$pt->map, $warna, $pt->matrik, $pt->kode_kavling],
                $svg_code,
            ) !!}
        </a>
    @endforeach

    {{-- SVG Footer --}}
    @if ($lokasi->masterSvg)
        {!! $lokasi->masterSvg->footer_svg !!}
    @endif
</div>
