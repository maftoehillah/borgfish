<?php

namespace App\Console\Commands;

use App\Services\LelangService;
use Illuminate\Console\Command;

class TutupLelangCommand extends Command
{
    protected $signature = 'lelang:cek';

    protected $description = 'Aktifkan lelang, tutup lelang habis, dan sinkronkan status transaksi';

    public function handle(LelangService $service): void
    {
        $service->aktifkanYangBelumMulai();
        $service->cekDanTutupSemua();
        $service->prosesOtomatisTransaksi();

        $this->info('Lelang berhasil diperbarui: ' . now());
    }
}
