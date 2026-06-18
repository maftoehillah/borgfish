<?php

namespace App\Jobs;

use App\Services\NotificationOutboxService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNotificationOutboxJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 55;

    public int $tries = 3;

    public function __construct(private readonly int $limit = 250)
    {
    }

    public function uniqueId(): string
    {
        return 'notification-outbox:' . $this->limit;
    }

    public function handle(NotificationOutboxService $notifications): void
    {
        $notifications->processPending($this->limit);
    }
}
