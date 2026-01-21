# Ticket Queue System Testing Guide

This guide will help you test the ticket queue system, including events, listeners, and queue processing.

## Prerequisites

1. **Run migrations**: `php artisan migrate`
2. **Start queue worker** (in a separate terminal): `php artisan queue:work`
   - Or use the dev script: `composer run dev` (includes queue worker)

## Testing Methods

### Method 1: Artisan Command (Recommended)

The easiest way to test everything at once:

```bash
# Test with synchronous processing (processes jobs immediately)
php artisan test:ticket-queue --sync

# Test with async processing (requires queue worker running)
php artisan test:ticket-queue

# Create more tickets
php artisan test:ticket-queue --sync --count=5
```

**What it tests:**
- ✅ Ticket creation
- ✅ QueueTicket job dispatch and processing
- ✅ Queue position calculation
- ✅ Estimated wait time calculation
- ✅ Status changes (called → serving → completed)
- ✅ Queue position recalculation
- ✅ Event firing

### Method 2: HTTP API Testing

Start your server: `php artisan serve`

#### Create a Ticket

```bash
# Create a regular ticket
curl -X POST http://localhost:8000/test/tickets/create \
  -H "Content-Type: application/json" \
  -d '{
    "service_type": "Service A",
    "queue_id": "queue-1",
    "member_name": "John Doe",
    "estimated_time": 300
  }'

# Create a priority ticket
curl -X POST http://localhost:8000/test/tickets/create \
  -H "Content-Type: application/json" \
  -d '{
    "service_type": "Service B",
    "queue_id": "queue-1",
    "member_name": "Jane Smith",
    "priority": true,
    "estimated_time": 300
  }'
```

#### Get Queue Status

```bash
curl http://localhost:8000/test/tickets/queue/queue-1
```

#### Get Ticket Details

```bash
curl http://localhost:8000/test/tickets/1
```

#### Update Ticket Status

```bash
# Call the ticket
curl -X PATCH http://localhost:8000/test/tickets/1/status \
  -H "Content-Type: application/json" \
  -d '{"status": "called"}'

# Start serving
curl -X PATCH http://localhost:8000/test/tickets/1/status \
  -H "Content-Type: application/json" \
  -d '{"status": "serving"}'

# Complete
curl -X PATCH http://localhost:8000/test/tickets/1/status \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'
```

### Method 3: Tinker (Interactive Testing)

```bash
php artisan tinker
```

```php
// Create a ticket
$ticket = App\Models\Ticket::create([
    'ticket_number' => 'T-001',
    'service_type' => 'Test Service',
    'queue_id' => 'queue-1',
    'member_name' => 'Test User',
    'status' => 'waiting',
    'office_id' => 'office-1',
]);

// Check queue position (after job processes)
$ticket->refresh();
$ticket->queue_position;
$ticket->getEstimatedWaitTime();

// Update status
$ticket->update(['status' => 'called', 'called_at' => now()]);
$ticket->refresh();
$ticket->queue_position; // Should be recalculated

// Get next ticket in queue
$queueService = app(App\Services\QueueService::class);
$nextTicket = $queueService->getNextTicket('queue-1');
```

## What to Verify

### 1. Events Are Firing
Check `storage/logs/laravel.log` for:
- `Ticket created` logs
- `Ticket queued successfully` logs
- `Queue position updated` logs
- `Ticket called` logs
- `Ticket serving` logs
- `Ticket completed` logs

### 2. Queue Jobs Are Processing
- Check queue jobs table: `SELECT * FROM jobs;`
- Or watch logs when queue worker processes jobs

### 3. Queue Positions Are Correct
- Priority tickets should have position `0`
- Regular tickets should have positions `1, 2, 3...`
- When a ticket is completed, remaining tickets should have positions recalculated

### 4. Wait Times Are Calculated
- Sum of `estimated_time` for all tickets ahead in queue
- Priority tickets should have `0` wait time

### 5. WebSocket Broadcasting (when broadcasting is set up)
- Events should broadcast to channels:
  - `tickets` (global)
  - `office.{office_id}` (office-specific)
  - `queue.{queue_id}` (queue-specific)

## Troubleshooting

### Queue Jobs Not Processing
- Make sure queue worker is running: `php artisan queue:work`
- Check queue connection in `.env`: `QUEUE_CONNECTION=database`
- Check failed jobs: `php artisan queue:failed`

### Events Not Firing
- Check `app/Providers/AppServiceProvider.php` - listeners should be registered
- Check logs for errors
- Verify event classes implement `ShouldBroadcast` if needed

### Queue Positions Not Updating
- Ensure `QueueTicket` job is being processed
- Check that `QueueService` methods are being called
- Verify migration added `queue_position` column

## Example Test Flow

1. **Create 3 tickets** (1 priority, 2 regular)
   ```bash
   php artisan test:ticket-queue --sync --count=3
   ```

2. **Verify queue positions**:
   - Priority ticket: position `0`
   - Regular tickets: positions `1` and `2`

3. **Call first ticket**:
   - Status changes to `called`
   - Queue positions recalculate

4. **Start serving**:
   - Status changes to `serving`
   - Queue positions recalculate

5. **Complete ticket**:
   - Status changes to `completed`
   - Ticket removed from queue
   - Remaining tickets positions recalculate

## Next Steps

After testing, you can:
1. Set up WebSocket broadcasting: `php artisan install:broadcasting --reverb`
2. Create frontend to listen to WebSocket events
3. Implement ticket calling system
4. Add more queue management features
