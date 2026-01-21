# Queue Management System - Technical Documentation

## Table of Contents
1. [Introduction](#introduction)
2. [System Overview](#system-overview)
3. [Architecture](#architecture)
4. [The Journey of a Ticket](#the-journey-of-a-ticket)
5. [Event-Driven System](#event-driven-system)
6. [Queue Management](#queue-management)
7. [Components Deep Dive](#components-deep-dive)
8. [Data Flow](#data-flow)
9. [API Reference](#api-reference)
10. [Testing & Monitoring](#testing--monitoring)
11. [Deployment & Operations](#deployment--operations)

---

## Introduction

This document tells the complete story of a **Queue Management System (QMS)** built with Laravel, designed to handle customer service queues efficiently. The system uses an event-driven architecture combined with Laravel's job queue system to manage ticket creation, queuing, status changes, and real-time updates.

### Key Features
- **Automatic Queue Management**: Tickets are automatically queued upon creation
- **Real-time Updates**: WebSocket broadcasting for live queue updates
- **Priority Support**: Priority tickets skip to the front of the queue
- **Position Tracking**: Each ticket knows its position and estimated wait time
- **Event-Driven**: Decoupled architecture using Laravel events and listeners
- **Asynchronous Processing**: Queue jobs handle heavy operations

---

## System Overview

### Technology Stack
- **Framework**: Laravel 12
- **Database**: SQLite (configurable to MySQL/PostgreSQL)
- **Queue System**: Laravel Queue (Database driver)
- **Broadcasting**: Laravel Broadcasting (ready for WebSocket integration)
- **Language**: PHP 8.2+

### Core Concepts

1. **Ticket**: Represents a customer service request
2. **Queue**: A logical grouping of tickets (by service type, office, etc.)
3. **Event**: Something that happened in the system (ticket created, called, etc.)
4. **Listener**: Code that responds to events
5. **Job**: Asynchronous task (like queuing a ticket)

---

## Architecture

### High-Level Architecture

```
┌─────────────────┐
│   Customer      │
│   Creates       │
│   Ticket        │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Ticket Model   │───► Fires TicketCreated Event
│  (created)      │
└────────┬────────┘
         │
         ├──► Dispatches QueueTicket Job
         │
         ▼
┌─────────────────┐
│  Queue Worker   │───► Processes QueueTicket Job
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ QueueService    │───► Calculates Position
│                 │───► Updates Queue Position
└────────┬────────┘
         │
         ├──► Fires QueuePositionUpdated Event
         │
         ▼
┌─────────────────┐
│   Broadcasters  │───► WebSocket Channels
│   (Events)      │    (tickets, office.*, queue.*)
└─────────────────┘
```

### Component Relationships

```
┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│   Ticket     │────────►│   Events     │────────►│  Listeners   │
│   Model      │         │              │         │              │
└──────┬───────┘         └──────┬───────┘         └──────┬───────┘
       │                        │                        │
       │                        │                        │
       ▼                        ▼                        ▼
┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│ QueueTicket │         │ Broadcasting │         │   Logging    │
│    Job      │         │   Channels   │         │              │
└──────┬───────┘         └──────────────┘         └──────────────┘
       │
       ▼
┌──────────────┐
│QueueService  │
│              │
└──────────────┘
```

---

## The Journey of a Ticket

Let's follow a ticket from creation to completion, step by step.

### Chapter 1: The Birth of a Ticket

**When**: A customer arrives at the service center and requests a ticket.

**What Happens**:

1. **Ticket Creation**
   ```php
   $ticket = Ticket::create([
       'ticket_number' => 'A001',
       'service_type' => 'Member Registration',
       'queue_id' => 'registration-queue',
       'member_name' => 'John Doe',
       'status' => 'waiting',
       'office_id' => 'office-1',
   ]);
   ```

2. **Model Boot Method Triggers**
   - The `Ticket` model's `boot()` method detects the `created` event
   - Two things happen simultaneously:
     - **Event Fired**: `TicketCreated` event is dispatched
     - **Job Dispatched**: `QueueTicket` job is pushed to the queue

3. **Event Broadcasting**
   - `TicketCreated` event implements `ShouldBroadcast`
   - Automatically broadcasts to channels:
     - `tickets` (global channel)
     - `office.office-1` (office-specific)
     - `queue.registration-queue` (queue-specific)
   - Event name: `ticket.created`
   - Payload includes ticket details

4. **Listener Execution**
   - `BroadcastTicketCreated` listener handles the event
   - Logs the ticket creation for audit purposes

### Chapter 2: Entering the Queue

**When**: The queue worker processes the `QueueTicket` job (asynchronously).

**What Happens**:

1. **Job Processing**
   ```php
   // QueueTicket job's handle() method executes
   public function handle(QueueService $queueService)
   {
       // Validates ticket is in 'waiting' status
       // Calls QueueService::addToQueue()
   }
   ```

2. **Position Calculation**
   - `QueueService::calculateQueuePosition()` determines where the ticket belongs:
     - **Priority tickets**: Position `0` (highest priority)
     - **Regular tickets**: Count of existing tickets + 1

3. **Position Assignment**
   - Ticket's `queue_position` is updated (using `withoutEvents()` to prevent loops)
   - If priority ticket, other tickets' positions are recalculated

4. **Broadcast Update**
   - `QueuePositionUpdated` event fires
   - Broadcasts new position and estimated wait time
   - All connected clients receive the update

### Chapter 3: Waiting in Line

**While Waiting**:

- Ticket status remains: `waiting`
- Queue position can change if:
  - Priority tickets are added ahead
  - Tickets ahead are processed
- Estimated wait time is calculated dynamically:
  ```php
  // Sum of estimated_time for all tickets ahead
  $waitTime = sum of (tickets_ahead.estimated_time)
  ```

### Chapter 4: The Call

**When**: A clerk calls the next ticket.

**What Happens**:

1. **Status Update**
   ```php
   $ticket->update([
       'status' => 'called',
       'called_at' => now(),
       'counter_id' => 'counter-1',
       'clerk_id' => 'clerk-123',
   ]);
   ```

2. **Model Event Chain**
   - `updating` event: Stores old status
   - `updated` event: Detects status change
   - Queue recalculation triggered
   - `TicketStatusChanged` event fired
   - `TicketCalled` event fired

3. **Queue Recalculation**
   - `QueueService::recalculateQueuePositions()` runs
   - All remaining tickets get new positions
   - Each position change broadcasts `QueuePositionUpdated`

4. **Broadcasting**
   - `TicketCalled` broadcasts to all channels
   - Display screens show: "Now Serving: A001"
   - Customer's mobile app receives notification

### Chapter 5: Service Begins

**When**: Customer reaches the counter and service starts.

**What Happens**:

1. **Status Change**
   ```php
   $ticket->update([
       'status' => 'serving',
       'serving_started_at' => now(),
   ]);
   ```

2. **Queue Removal**
   - Ticket removed from active queue
   - `queue_position` set to `null`
   - Remaining tickets' positions recalculated

3. **Events**
   - `TicketServing` event fires
   - `TicketStatusChanged` event fires
   - Both broadcast to channels

### Chapter 6: Completion

**When**: Service is completed.

**What Happens**:

1. **Final Status Update**
   ```php
   $ticket->update([
       'status' => 'completed',
       'completed_at' => now(),
       'duration_seconds' => 180, // 3 minutes
   ]);
   ```

2. **Final Events**
   - `TicketCompleted` event fires
   - `TicketStatusChanged` event fires
   - Duration calculated and stored

3. **Queue Cleanup**
   - Ticket completely removed from queue
   - All remaining tickets advance in position
   - Wait times recalculated

---

## Event-Driven System

### Event Types

#### 1. TicketCreated
**When**: New ticket is created  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `ticket.created`  
**Payload**: Ticket details (number, service type, queue, member info)

#### 2. TicketCalled
**When**: Ticket status changes to 'called'  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `ticket.called`  
**Payload**: Ticket details + counter/clerk info + called_at timestamp

#### 3. TicketServing
**When**: Ticket status changes to 'serving'  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `ticket.serving`  
**Payload**: Ticket details + serving_started_at timestamp

#### 4. TicketCompleted
**When**: Ticket status changes to 'completed'  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `ticket.completed`  
**Payload**: Ticket details + completed_at + duration_seconds

#### 5. TicketStatusChanged
**When**: Any status change occurs  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `ticket.status.changed`  
**Payload**: Ticket details + old_status + new_status

#### 6. QueuePositionUpdated
**When**: Queue position changes  
**Channels**: `tickets`, `office.{id}`, `queue.{id}`  
**Broadcast Name**: `queue.position.updated`  
**Payload**: Ticket details + queue_position + estimated_wait_time

### Listener Responsibilities

Each event has a corresponding listener:

- **BroadcastTicketCreated**: Logs ticket creation
- **BroadcastTicketCalled**: Logs ticket call
- **BroadcastTicketServing**: Logs service start
- **BroadcastTicketCompleted**: Logs completion
- **BroadcastTicketStatusChanged**: Logs status changes
- **BroadcastQueuePositionUpdated**: Logs position updates

All listeners are registered in `AppServiceProvider::boot()`.

### Event Registration Flow

```
AppServiceProvider::boot()
    │
    ├──► Event::listen(TicketCreated::class, BroadcastTicketCreated::class)
    ├──► Event::listen(TicketCalled::class, BroadcastTicketCalled::class)
    ├──► Event::listen(TicketServing::class, BroadcastTicketServing::class)
    ├──► Event::listen(TicketCompleted::class, BroadcastTicketCompleted::class)
    ├──► Event::listen(TicketStatusChanged::class, BroadcastTicketStatusChanged::class)
    └──► Event::listen(QueuePositionUpdated::class, BroadcastQueuePositionUpdated::class)
```

---

## Queue Management

### Queue Position Logic

**Priority System**:
- Priority tickets: Position `0` (always first)
- Regular tickets: Sequential positions (`1`, `2`, `3`, ...)

**Position Calculation**:
```php
if (ticket.priority) {
    position = 0;
} else {
    position = count(waiting_tickets_in_queue) + 1;
}
```

### Wait Time Calculation

**Formula**:
```
estimated_wait_time = sum(estimated_time of all tickets ahead)
```

**Example**:
- Ticket A: Position 1, estimated_time: 300s (5 min)
- Ticket B: Position 2, estimated_time: 180s (3 min)
- Ticket C: Position 3, estimated_time: 240s (4 min)

Ticket C's wait time = 300 + 180 = 480 seconds (8 minutes)

### Queue Recalculation Triggers

Positions are recalculated when:

1. **Ticket Created**: If priority ticket, others shift
2. **Ticket Called**: Remaining tickets advance
3. **Ticket Serving**: Ticket removed, others advance
4. **Ticket Completed**: Ticket removed, others advance
5. **Ticket Cancelled**: Ticket removed, others advance
6. **Ticket Skipped**: Ticket removed, others advance

### QueueService Methods

#### `addToQueue(Ticket $ticket): int`
- Calculates position
- Updates ticket's queue_position
- Recalculates if priority ticket
- Broadcasts QueuePositionUpdated
- Returns assigned position

#### `getQueuePosition(Ticket $ticket): ?int`
- Returns current queue position

#### `calculateEstimatedWaitTime(Ticket $ticket): int`
- Calculates wait time based on tickets ahead
- Returns seconds

#### `recalculateQueuePositions(string $queueId, ?string $excludeTicketId): void`
- Recalculates all positions in a queue
- Updates positions without triggering events (prevents loops)
- Broadcasts updates for changed tickets

#### `getNextTicket(string $queueId): ?Ticket`
- Returns next ticket to be called
- Ordered by position, then creation time

#### `removeFromQueue(Ticket $ticket): void`
- Removes ticket from queue
- Sets queue_position to null
- Recalculates remaining tickets

---

## Components Deep Dive

### 1. Ticket Model (`app/Models/Ticket.php`)

**Purpose**: Core entity representing a customer service ticket

**Key Features**:
- Auto-incrementing integer ID
- Status tracking (waiting, called, serving, completed, etc.)
- Queue position management
- Timestamp tracking (called_at, serving_started_at, completed_at)

**Model Events**:
```php
// On creation
static::created() {
    → Fires TicketCreated event
    → Dispatches QueueTicket job
}

// On update
static::updating() {
    → Stores old status
}

static::updated() {
    → Detects status changes
    → Triggers queue recalculation
    → Fires status-specific events
}
```

**Helper Methods**:
- `isWaiting()`, `isCalled()`, `isServing()`, `isCompleted()`
- `getQueuePosition()`
- `getEstimatedWaitTime()`
- `isNextInQueue()`

**Status Flow**:
```
waiting → called → serving → completed
   ↓        ↓        ↓         ↓
skipped  cancelled  transferred
```

### 2. QueueService (`app/Services/QueueService.php`)

**Purpose**: Business logic for queue management

**Responsibilities**:
- Position calculation
- Wait time estimation
- Queue recalculation
- Queue manipulation (add/remove)

**Key Design Decisions**:
- Uses `withoutEvents()` to prevent infinite loops
- Only recalculates when necessary
- Efficient queries (indexed columns)

### 3. QueueTicket Job (`app/Jobs/QueueTicket.php`)

**Purpose**: Asynchronous ticket queuing

**Why Async?**:
- Prevents blocking the main request
- Handles high traffic efficiently
- Can be retried on failure

**Process**:
1. Validates ticket status
2. Calls QueueService::addToQueue()
3. Logs success/failure

**Queue Configuration**:
- Driver: Database (configurable)
- Connection: Default queue connection
- Retries: Configurable

### 4. Events (`app/Events/`)

**Common Structure**:
```php
class TicketEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(public Ticket $ticket) {}
    
    public function broadcastOn(): array
    {
        return [
            new Channel('tickets'),
            new Channel("office.{$this->ticket->office_id}"),
            new Channel("queue.{$this->ticket->queue_id}"),
        ];
    }
    
    public function broadcastAs(): string
    {
        return 'ticket.event-name';
    }
    
    public function broadcastWith(): array
    {
        return ['ticket' => [...]];
    }
}
```

### 5. Listeners (`app/Listeners/`)

**Purpose**: Handle events (currently logging, can be extended)

**Structure**:
```php
class BroadcastTicketEvent
{
    public function handle(TicketEvent $event): void
    {
        // Log event
        // Can add: notifications, analytics, etc.
    }
}
```

### 6. Database Schema

**Tickets Table**:
```sql
- id (integer, primary key)
- ticket_number (string, unique)
- service_type (string)
- service_id (string, nullable)
- queue_id (string)
- member_number (string, nullable)
- member_name (string, nullable)
- phone_number (string, nullable)
- estimated_time (integer, seconds)
- priority (boolean)
- status (string: waiting|called|serving|completed|skipped|transferred|cancelled)
- counter_id (string, nullable)
- clerk_id (string, nullable)
- called_at (timestamp, nullable)
- serving_started_at (timestamp, nullable)
- completed_at (timestamp, nullable)
- duration_seconds (integer, nullable)
- transferred_to_counter_id (string, nullable)
- office_id (string)
- queue_position (integer, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

**Indexes**:
- `ticket_number` (unique)
- `queue_id`, `status`, `queue_position` (composite)
- `counter_id`, `clerk_id`, `office_id`
- `created_at`, `member_number`

---

## Data Flow

### Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    TICKET CREATION FLOW                     │
└─────────────────────────────────────────────────────────────┘

1. HTTP Request / API Call
   │
   ▼
2. Ticket::create([...])
   │
   ├──► Database INSERT
   │
   └──► Model Boot Events
       │
       ├──► TicketCreated Event
       │   │
       │   ├──► BroadcastTicketCreated Listener
       │   │   └──► Logs creation
       │   │
       │   └──► Broadcasting System
       │       ├──► Channel: tickets
       │       ├──► Channel: office.{id}
       │       └──► Channel: queue.{id}
       │
       └──► QueueTicket Job Dispatch
           │
           ▼
           Queue Worker (async)
           │
           └──► QueueTicket::handle()
               │
               └──► QueueService::addToQueue()
                   │
                   ├──► Calculate Position
                   ├──► Update queue_position (withoutEvents)
                   ├──► Recalculate if priority
                   └──► QueuePositionUpdated Event
                       │
                       ├──► BroadcastQueuePositionUpdated Listener
                       └──► Broadcasting System
```

```
┌─────────────────────────────────────────────────────────────┐
│                  STATUS CHANGE FLOW                         │
└─────────────────────────────────────────────────────────────┘

1. Ticket::update(['status' => 'called'])
   │
   ├──► Model Boot: updating()
   │   └──► Store old_status
   │
   └──► Database UPDATE
       │
       └──► Model Boot: updated()
           │
           ├──► Detect Status Change
           │   │
           │   └──► QueueService::recalculateQueuePositions()
           │       │
           │       ├──► Query tickets in queue
           │       ├──► Calculate new positions
           │       ├──► Update positions (withoutEvents)
           │       └──► QueuePositionUpdated Events
           │
           ├──► TicketStatusChanged Event
           │   └──► Broadcasting
           │
           └──► TicketCalled Event (status-specific)
               └──► Broadcasting
```

---

## API Reference

### Test Endpoints

#### Create Ticket
```http
POST /test/tickets/create
Content-Type: application/json

{
    "service_type": "Member Registration",
    "queue_id": "registration-queue",
    "member_name": "John Doe",
    "member_number": "M123456",
    "phone_number": "+1234567890",
    "estimated_time": 300,
    "priority": false,
    "office_id": "office-1"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Ticket created successfully",
    "ticket": {
        "id": 1,
        "ticket_number": "TEST-0001",
        "status": "waiting",
        "queue_position": 1,
        "estimated_wait_time": 0
    }
}
```

#### Get Queue Status
```http
GET /test/tickets/queue/{queueId}
```

**Response**:
```json
{
    "success": true,
    "queue_id": "registration-queue",
    "tickets": [
        {
            "id": 1,
            "ticket_number": "TEST-0001",
            "status": "waiting",
            "queue_position": 1,
            "estimated_wait_time": 0,
            "priority": false,
            "created_at": "2026-01-20T10:00:00Z"
        }
    ],
    "count": 1
}
```

#### Get Ticket Details
```http
GET /test/tickets/{id}
```

#### Update Ticket Status
```http
PATCH /test/tickets/{id}/status
Content-Type: application/json

{
    "status": "called"
}
```

### Log Viewer

#### View Logs
```http
GET /logs?lines=200&level=ERROR
```

#### Clear Logs
```http
POST /logs/clear
```

#### Download Logs
```http
GET /logs/download
```

---

## Testing & Monitoring

### Testing Command

```bash
# Test with synchronous processing
php artisan test:ticket-queue --sync

# Test with async processing (requires queue worker)
php artisan test:ticket-queue

# Create more tickets
php artisan test:ticket-queue --sync --count=5
```

**What It Tests**:
- ✅ Ticket creation
- ✅ Queue job dispatch and processing
- ✅ Queue position calculation
- ✅ Estimated wait time calculation
- ✅ Status changes (called → serving → completed)
- ✅ Queue position recalculation
- ✅ Event firing

### Monitoring

#### Log Viewer
- **URL**: `/logs`
- **Features**:
  - Filter by log level
  - Adjustable line count (50-2000)
  - Color-coded entries
  - Download/clear options

#### Log Files
- **Location**: `storage/logs/laravel.log`
- **What's Logged**:
  - Ticket creation
  - Queue job processing
  - Queue position updates
  - Status changes
  - Errors and exceptions

### Queue Monitoring

```bash
# Check queue status
php artisan queue:work --verbose

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Deployment & Operations

### Prerequisites

1. **PHP 8.2+**
2. **Composer**
3. **Database** (SQLite/MySQL/PostgreSQL)
4. **Queue Worker** (required for async processing)

### Setup Steps

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run Migrations**
   ```bash
   php artisan migrate
   ```

4. **Start Queue Worker**
   ```bash
   php artisan queue:work
   ```

5. **Start Development Server**
   ```bash
   php artisan serve
   ```

### Production Considerations

1. **Queue Worker**
   - Use supervisor or systemd to keep worker running
   - Configure retries and timeouts
   - Monitor failed jobs

2. **Database**
   - Use MySQL/PostgreSQL for production
   - Configure proper indexes
   - Regular backups

3. **Broadcasting**
   - Set up WebSocket server (Laravel Reverb)
   - Configure broadcasting driver
   - Set up Redis for scaling

4. **Performance**
   - Enable query caching
   - Use Redis for queue (optional)
   - Optimize database queries

5. **Monitoring**
   - Set up log rotation
   - Monitor queue depth
   - Track error rates
   - Monitor WebSocket connections

### Environment Variables

```env
QUEUE_CONNECTION=database  # or redis, sqs, etc.
DB_CONNECTION=sqlite       # or mysql, pgsql
BROADCAST_DRIVER=log       # or pusher, reverb, etc.
```

---

## Architecture Decisions

### Why Event-Driven?

1. **Decoupling**: Components don't need to know about each other
2. **Extensibility**: Easy to add new listeners
3. **Testability**: Events can be mocked easily
4. **Scalability**: Events can be queued and processed async

### Why Async Queue Jobs?

1. **Performance**: Doesn't block HTTP requests
2. **Reliability**: Jobs can be retried on failure
3. **Scalability**: Can process jobs in parallel
4. **User Experience**: Fast response times

### Why Multiple Broadcast Channels?

1. **Efficiency**: Clients subscribe only to relevant channels
2. **Security**: Can implement channel authorization
3. **Scalability**: Reduces unnecessary traffic
4. **Flexibility**: Different UIs can listen to different channels

### Why withoutEvents()?

Prevents infinite loops when updating queue positions:
- Position update → triggers updated event → recalculates → updates positions → loops!

Solution: Use `withoutEvents()` for position-only updates.

---

## Future Enhancements

### Potential Additions

1. **WebSocket Integration**
   - Real-time display screens
   - Mobile app notifications
   - Clerk dashboard updates

2. **Analytics**
   - Average wait times
   - Service duration statistics
   - Peak hour analysis
   - Clerk performance metrics

3. **Advanced Features**
   - Ticket transfers between counters
   - Appointment scheduling
   - SMS notifications
   - Multi-language support

4. **Security**
   - Authentication/authorization
   - Rate limiting
   - API keys
   - Audit logging

---

## Conclusion

This Queue Management System demonstrates a modern, event-driven architecture that:

- ✅ Handles high traffic efficiently
- ✅ Provides real-time updates
- ✅ Maintains data consistency
- ✅ Scales horizontally
- ✅ Is easy to extend and maintain

The combination of Laravel's event system, job queue, and broadcasting creates a robust foundation for managing customer service queues in real-time.

---

## Appendix

### File Structure

```
app/
├── Console/Commands/
│   └── TestTicketQueue.php
├── Events/
│   ├── QueuePositionUpdated.php
│   ├── TicketCalled.php
│   ├── TicketCompleted.php
│   ├── TicketCreated.php
│   ├── TicketServing.php
│   └── TicketStatusChanged.php
├── Http/Controllers/
│   ├── Controller.php
│   └── LogViewController.php
├── Jobs/
│   └── QueueTicket.php
├── Listeners/
│   ├── BroadcastQueuePositionUpdated.php
│   ├── BroadcastTicketCalled.php
│   ├── BroadcastTicketCompleted.php
│   ├── BroadcastTicketCreated.php
│   ├── BroadcastTicketServing.php
│   └── BroadcastTicketStatusChanged.php
├── Models/
│   ├── Ticket.php
│   └── User.php
├── Providers/
│   └── AppServiceProvider.php
└── Services/
    └── QueueService.php

database/migrations/
├── 2026_01_20_081700_create_tickets_table.php
└── 2026_01_20_081722_add_queue_position_to_tickets_table.php

resources/views/
└── logs/
    └── view.blade.php

routes/
└── web.php
```

### Key Classes Reference

| Class | Purpose | Location |
|-------|---------|----------|
| `Ticket` | Core model | `app/Models/Ticket.php` |
| `QueueService` | Queue logic | `app/Services/QueueService.php` |
| `QueueTicket` | Async job | `app/Jobs/QueueTicket.php` |
| `TicketCreated` | Creation event | `app/Events/TicketCreated.php` |
| `TicketCalled` | Call event | `app/Events/TicketCalled.php` |
| `TicketServing` | Service event | `app/Events/TicketServing.php` |
| `TicketCompleted` | Completion event | `app/Events/TicketCompleted.php` |
| `QueuePositionUpdated` | Position event | `app/Events/QueuePositionUpdated.php` |

---

**Document Version**: 1.0  
**Last Updated**: January 2026  
**Author**: Development Team
