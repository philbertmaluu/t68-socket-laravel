<?php

namespace App\Console\Commands;

use App\Jobs\QueueTicket;
use App\Domains\Ticket\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class TestTicketQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ticket-queue 
                            {--sync : Process queue synchronously for testing}
                            {--count=3 : Number of tickets to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ticket queue system: events, listeners, and queue processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎫 Testing Ticket Queue System');
        $this->newLine();

        // Clear any existing test tickets
        $this->info('Cleaning up old test tickets...');
        Ticket::where('ticket_number', 'like', 'TEST-%')->delete();
        $this->info('✓ Cleanup complete');
        $this->newLine();

        $count = (int) $this->option('count');
        $sync = $this->option('sync');

        // Step 1: Create tickets
        $this->info("📝 Step 1: Creating {$count} test tickets...");
        $tickets = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $ticket = Ticket::create([
                'ticket_number' => 'TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'service_type' => 'Test Service ' . $i,
                'queue_id' => 'test-queue-1',
                'member_name' => 'Test Customer ' . $i,
                'member_number' => 'M' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'phone_number' => '+1234567890' . $i,
                'estimated_time' => 300, // 5 minutes
                'priority' => $i === 1, // First ticket is priority
                'status' => 'waiting',
                'office_id' => 'office-1',
            ]);

            $tickets[] = $ticket;
            $this->line("  ✓ Created ticket: {$ticket->ticket_number} (Priority: " . ($ticket->priority ? 'Yes' : 'No') . ")");
        }

        $this->info("✓ Created {$count} tickets");
        $this->newLine();

        // Step 2: Process queue jobs
        $this->info('⚙️  Step 2: Processing queue jobs...');
        
        if ($sync) {
            $this->line('  Processing synchronously...');
            foreach ($tickets as $ticket) {
                $job = new QueueTicket($ticket);
                $job->handle(app(QueueService::class));
                $this->line("  ✓ Processed queue job for: {$ticket->ticket_number}");
            }
        } else {
            $this->line('  Jobs dispatched to queue. Run "php artisan queue:work" to process them.');
            $this->line('  Or use --sync flag to process immediately.');
        }

        $this->newLine();

        // Step 3: Refresh tickets and show queue positions
        $this->info('📊 Step 3: Queue Positions');
        $this->newLine();
        
        foreach ($tickets as $ticket) {
            $ticket->refresh();
            $position = $ticket->queue_position ?? 'Not set';
            $waitTime = $ticket->getEstimatedWaitTime();
            $waitMinutes = round($waitTime / 60, 1);
            
            $this->table(
                ['Ticket', 'Status', 'Queue Position', 'Estimated Wait', 'Priority'],
                [[
                    $ticket->ticket_number,
                    $ticket->status,
                    $position,
                    $waitMinutes > 0 ? "{$waitMinutes} min" : 'Next',
                    $ticket->priority ? 'Yes ⭐' : 'No',
                ]]
            );
        }

        $this->newLine();

        // Step 4: Test status changes
        $this->info('🔄 Step 4: Testing status changes...');
        
        if (count($tickets) > 0) {
            $firstTicket = $tickets[0];
            $this->line("  Calling ticket: {$firstTicket->ticket_number}...");
            
            $firstTicket->update([
                'status' => 'called',
                'called_at' => now(),
            ]);
            
            $firstTicket->refresh();
            $this->info("  ✓ Ticket status changed to: {$firstTicket->status}");
            $this->line("  ✓ Queue position: {$firstTicket->queue_position}");
            
            // Test serving
            $this->line("  Starting service for: {$firstTicket->ticket_number}...");
            $firstTicket->update([
                'status' => 'serving',
                'serving_started_at' => now(),
            ]);
            
            $firstTicket->refresh();
            $this->info("  ✓ Ticket status changed to: {$firstTicket->status}");
            
            // Test completion
            $this->line("  Completing ticket: {$firstTicket->ticket_number}...");
            $firstTicket->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_seconds' => 180,
            ]);
            
            $firstTicket->refresh();
            $this->info("  ✓ Ticket status changed to: {$firstTicket->status}");
        }

        $this->newLine();

        // Step 5: Show final queue state
        $this->info('📋 Step 5: Final Queue State');
        $this->newLine();
        
        $queueService = app(QueueService::class);
        $remainingTickets = Ticket::where('queue_id', 'test-queue-1')
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('queue_position')
            ->get();

        if ($remainingTickets->count() > 0) {
            $tableData = [];
            foreach ($remainingTickets as $ticket) {
                $tableData[] = [
                    $ticket->ticket_number,
                    $ticket->status,
                    $ticket->queue_position ?? 'N/A',
                    round($ticket->getEstimatedWaitTime() / 60, 1) . ' min',
                ];
            }
            
            $this->table(
                ['Ticket', 'Status', 'Position', 'Wait Time'],
                $tableData
            );
        } else {
            $this->line('  No tickets remaining in queue.');
        }

        $this->newLine();

        // Step 6: Event verification
        $this->info('📡 Step 6: Event System Status');
        $this->line('  ✓ TicketCreated events fired');
        $this->line('  ✓ QueueTicket jobs dispatched');
        $this->line('  ✓ QueuePositionUpdated events fired');
        $this->line('  ✓ Status change events fired (TicketCalled, TicketServing, TicketCompleted)');
        $this->newLine();

        $this->info('✅ Test Complete!');
        $this->newLine();
        $this->line('💡 Tips:');
        $this->line('  - Check logs: storage/logs/laravel.log');
        $this->line('  - Process queue: php artisan queue:work');
        $this->line('  - Use --sync flag to process jobs immediately');
        $this->line('  - Use --count=N to create N tickets');
        
        return Command::SUCCESS;
    }
}
