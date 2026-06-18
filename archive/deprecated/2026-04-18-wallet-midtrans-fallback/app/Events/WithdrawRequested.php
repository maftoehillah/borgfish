<?php

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class WithdrawRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Withdrawal $withdrawal;

    public function __construct(Withdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }
}
