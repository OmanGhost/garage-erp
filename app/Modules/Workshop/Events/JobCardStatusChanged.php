<?php

namespace App\Modules\Workshop\Events;

use App\Modules\Workshop\Models\JobCard;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast over Laravel Reverb to the company channel (Kanban / dashboards
 * refresh in real time) and consumed by notification & webhook listeners.
 */
class JobCardStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JobCard $jobCard, public ?string $from, public string $to) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->jobCard->company_id)];
    }

    public function broadcastAs(): string
    {
        return 'job-card.status';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->jobCard->id,
            'number' => $this->jobCard->number,
            'from' => $this->from,
            'to' => $this->to,
            'label' => status_label($this->to),
        ];
    }
}
