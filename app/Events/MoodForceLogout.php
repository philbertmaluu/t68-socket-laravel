<?php

namespace App\Events;

use App\Domains\Device\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoodForceLogout implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Device $device,
        public string $reason = 'admin_force_logout'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('mood.device.'.$this->device->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mood.force.logout';
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
