<?php

namespace App\Modules\Workshop\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Something on the workshop floor changed (task, schedule, vehicle / key movement): live screens refresh. */
class WorkshopUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $companyId, public string $what = 'task') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'workshop.updated';
    }

    public function broadcastWith(): array
    {
        return ['what' => $this->what];
    }

    /** Only when real-time broadcasting is configured (no queue / network work otherwise). */
    public static function notify(?int $companyId, string $what = 'task'): void
    {
        if ($companyId && config('broadcasting.default') === 'reverb') {
            try {
                event(new self($companyId, $what));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
