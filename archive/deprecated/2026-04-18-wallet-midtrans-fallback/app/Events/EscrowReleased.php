<?php

namespace App\Events;

use App\Models\Escrow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class EscrowReleased
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Escrow $escrow;

    public function __construct(Escrow $escrow)
    {
        $this->escrow = $escrow;
    }
}
