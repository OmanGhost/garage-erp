<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

/** Notifications center (database notifications; refreshed via polling or Reverb). */
class NotificationsBell extends Component
{
    #[On('notification-received')]
    public function refreshList(): void {}

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
