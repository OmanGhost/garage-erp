<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (database + real-time broadcast via Reverb).
 */
class SystemMessage extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $body = '', public ?string $url = null, public string $level = 'info') {}

    public function via($notifiable): array
    {
        return config('broadcasting.default') && ! in_array(config('broadcasting.default'), ['null', 'log'], true)
            ? ['database', 'broadcast']
            : ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'level' => $this->level];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
