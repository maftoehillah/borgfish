<?php

namespace App\Jobs;

use App\Services\LelangService;
use App\Services\NotificationOutboxService;
use App\Services\TransaksiFulfillmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAuctionAutomationJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 55;

    public int $tries = 3;

    public function uniqueId(): string
    {
        return 'auction-automation';
    }

    public function handle(
        LelangService $lelang,
        NotificationOutboxService $notifications,
        TransaksiFulfillmentService $fulfillment,
    ): void
    {
        $lelang->aktifkanYangBelumMulai();
        $lelang->cekDanTutupSemua();
        $lelang->prosesOtomatisTransaksi();
        $fulfillment->syncSlaTimeouts();

        $notifications->queuePaymentDeadlineReminders();
        $notifications->processPending(250);
    }
}
