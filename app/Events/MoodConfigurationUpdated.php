<?php

namespace App\Events;

use App\Domains\Device\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoodConfigurationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Device $device,
        public int $version,
        public array $delta = []
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
        return 'mood.configuration.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'delta' => $this->delta,
        ];
    }
}
