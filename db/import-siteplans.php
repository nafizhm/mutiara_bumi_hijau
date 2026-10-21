<?php

// Run from the project root: php db/import-siteplans.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$plans = [];
foreach (['pesona-angrek' => ['Pesona Angrek', 'PA'], 'mutiara-ville' => ['Mutiara Ville', 'MV']] as $file => [$name, $short]) {
    $doc = new DOMDocument();
    if (!$doc->load(__DIR__.'/siteplans/'.$file.'.svg', LIBXML_NONET)) {
        throw new RuntimeException("SVG tidak valid: $file");
    }
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('s', 'http://www.w3.org/2000/svg');
    $lots = [];
    $templates = [];
    $groups = iterator_to_array($xpath->query('/s:svg/s:g//s:g'));
    foreach ($groups as $group) {
        $texts = $xpath->query('.//s:text', $group);
        if ($texts->length !== 1 || !preg_match('/^[A-Z]+-\d+$/', trim($texts->item(0)->textContent))) continue;
        $text = $texts->item(0);
        $code = trim($text->textContent);
        $shapes = $xpath->query('./s:polygon | ./s:path', $group);
        if ($shapes->length === 0) continue;
        if ($shapes->length !== 1 || isset($lots[$code])) throw new RuntimeException("Kavling tidak unik: $name $code");
        $shape = $shapes->item(0);
        $kind = $shape->localName;
        $attribute = $kind === 'polygon' ? 'points' : 'd';
        $lots[$code] = ['kode_kavling' => $code, 'jenis_map' => $kind,
            'map' => $shape->getAttribute($attribute), 'matrik' => $text->parentNode->getAttribute('transform'),
            'panjang' => 0, 'lebar' => 0, 'luas_tanah' => 0, 'hrg_jual' => 0, 'keterangan' => ''];
        if (!isset($templates[$kind])) {
            $template = $group->cloneNode(true);
            $template->removeAttribute('id');
            $templateShape = $xpath->query('./s:polygon | ./s:path', $template)->item(0);
            $templateShape->setAttribute($attribute, '[[1]]');
            $templateShape->setAttribute('style', 'fill:[[2]]');
            $templateText = $xpath->query('.//s:text', $template)->item(0);
            $templateText->nodeValue = '[[4]]';
            $templateText->parentNode->setAttribute('transform', '[[3]]');
            $templates[$kind] = $doc->saveXML($template);
        }
        $group->parentNode->removeChild($group);
    }
    if (!$lots) throw new RuntimeException("Tidak ada kavling: $name");
    // CorelDRAW can leave a duplicate standalone label outside its lot group.
    foreach (iterator_to_array($xpath->query('//s:text')) as $text) {
        if (isset($lots[trim($text->textContent)])) $text->parentNode->removeChild($text);
    }
    // Both templates are required by the settings form, even for polygon-only maps.
    if (!isset($templates['path'])) {
        $templates['path'] = preg_replace('/<polygon\b[^>]*\/>/', '<path d="[[1]]" style="fill:[[2]]"/>', $templates['polygon']);
    }
    $root = $doc->documentElement;
    $root->setAttribute('width', '[[lebar]]');
    $root->setAttribute('height', '[[tinggi]]');
    $svg = $doc->saveXML($root);
    $plans[] = compact('name', 'short', 'lots', 'templates', 'svg');
}

DB::transaction(function () use ($plans) {
    foreach ($plans as $plan) {
        if (DB::table('lokasi_kavling')->where('nama_kavling', $plan['name'])->exists()) {
            throw new RuntimeException('Lokasi sudah ada: '.$plan['name'].'. Impor dibatalkan agar data tidak tertimpa.');
        }
        $location = [];
        foreach (DB::select('SHOW COLUMNS FROM lokasi_kavling') as $column) {
            if ($column->Field !== 'id' && $column->Null === 'NO' && $column->Default === null) {
                $location[$column->Field] = preg_match('/int|double|decimal/', $column->Type) ? 0 : '';
            }
        }
        $id = DB::table('lokasi_kavling')->insertGetId(array_merge($location, [
            'nama_kavling' => $plan['name'], 'nama_singkat' => $plan['short'],
            'urutan' => (DB::table('lokasi_kavling')->max('urutan') ?? 0) + 1, 'stt_tampil' => 1,
        ]));
        DB::table('master_svg')->insert(['id_lokasi' => $id,
            'header_xml' => '<?xml version="1.0" encoding="UTF-8"?>',
            'header_svg' => substr($plan['svg'], 0, strrpos($plan['svg'], '</svg>')),
            'polygon_svg' => $plan['templates']['polygon'], 'path_svg' => $plan['templates']['path'],
            'body_svg' => '', 'footer_svg' => '</svg>',
        ]);
        foreach ($plan['lots'] as $lot) DB::table('kavling_peta')->insert(['id_lokasi' => $id] + $lot);
        if (DB::table('kavling_peta')->where('id_lokasi', $id)->count() !== count($plan['lots'])) {
            throw new RuntimeException('Jumlah kavling tidak sesuai.');
        }
        echo $plan['name']." (ID $id): ".count($plan['lots'])." kavling\n";
    }
});
