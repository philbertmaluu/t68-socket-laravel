<?php

namespace App\Console\Commands;

use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Services\TicketNumberAllocator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CompletePreviousDayTicketsCommand extends Command
{
    protected $signature = 'tickets:complete-previous-days
                            {--dry-run : Show how many tickets would be completed without writing}';

    protected $description = 'Complete open tickets issued before today';

    /**
     * Statuses that are still in progress and should be closed at day end.
     *
     * @var list<string>
     */
    private const OPEN_STATUSES = [
        'waiting',
        'called',
        'serving',
        'paused',
        'hold',
        'suspended',
    ];

    public function handle(): int
    {
        $today = Carbon::now(TicketNumberAllocator::BUSINESS_TIMEZONE)->toDateString();
        $startOfTodayUtc = Carbon::parse($today, TicketNumberAllocator::BUSINESS_TIMEZONE)
            ->startOfDay()
            ->utc();

        $query = Ticket::withoutTenant()
            ->whereIn('status', self::OPEN_STATUSES)
            ->where(function ($inner) use ($today, $startOfTodayUtc) {
                $inner->whereDate('issued_on', '<', $today)
                    ->orWhere(function ($fallback) use ($startOfTodayUtc) {
                        $fallback
                            ->whereNull('issued_on')
                            ->where('created_at', '<', $startOfTodayUtc);
                    });
            });

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would complete {$count} ticket(s) issued before {$today}.");

            return self::SUCCESS;
        }

        $completed = 0;
        $now = now();

        $query->orderBy('id')->chunkById(200, function ($tickets) use (&$completed, $now) {
            foreach ($tickets as $ticket) {
                $payload = [
                    'status' => 'completed',
                    'completed_at' => $ticket->completed_at ?? $now,
                ];

                $startedAt = $ticket->serving_started_at ?? $ticket->called_at;
                if ($startedAt) {
                    $payload['duration_seconds'] = max(0, (int) $now->diffInSeconds($startedAt));
                }

                $ticket->withoutEvents(function () use ($ticket, $payload) {
                    $ticket->update($payload);
                });

                $completed++;
            }
        });

        $this->info("Completed {$completed} ticket(s) issued before {$today}.");

        return self::SUCCESS;
    }
}
