<?php

namespace App\Events;

use App\Domains\Mood\Models\MoodFeedbackSession;
use App\Domains\Mood\Services\MoodFeedbackSessionService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoodFeedbackRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MoodFeedbackSession $session
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('mood.device.'.$this->session->device_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mood.feedback.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => (new MoodFeedbackSessionService())->formatSession($this->session),
        ];
    }
}
