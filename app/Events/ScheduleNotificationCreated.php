<?php

namespace App\Events;

use App\Models\ScheduleNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly ScheduleNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'schedule-notification.created';
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
