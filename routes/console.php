<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('finance:audit {--repair : Cadangkan lalu perbaiki saldo dan tautan pembayaran yang pasti}', function () {
    $audit = app(\App\Services\FinanceAudit::class);
    if ($this->option('repair')) {
        $result = $audit->repair();
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
    }
    $issues = $audit->issues();
    foreach ($issues as $issue) $this->warn(json_encode($issue));
    $this->info(count($issues) . ' masalah rekonsiliasi ditemukan.');
    return count($issues) ? 1 : 0;
})->purpose('Periksa konsistensi hutang, piutang, transaksi rekening, dan mutasi');
