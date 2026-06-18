<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\Transaksi;

class OrderCodeService
{
    public function nextOrderCode(): string
    {
        $date = now()->format('Ymd');
        $count = Transaksi::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('ORD-%s-%03d', $date, $count);
    }

    public function nextPaymentCode(): string
    {
        $date = now()->format('Ymd');
        $count = PaymentAttempt::query()
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('PAY-%s-%03d', $date, $count);
    }
}
