# Queue Management System (QMS) – Laravel Backend

A **Laravel 12** backend for a real-time **Queue Management System** with WebSocket broadcasting (Laravel Reverb). It powers ticket creation, queue assignment, counter management, and live updates to office displays and clerk dashboards.

---

## Features

- **Ticket lifecycle**: Create tickets with auto-generated numbers (e.g. `A001`–`Z999`), assign to queues by service and office, and track status: `waiting` → `called` → `serving` → `completed` (or `skipped` / `cancelled`).
- **Queue & position**: Queues per counter, automatic `queue_position`, priority tickets, estimated wait time, and “next ticket” logic.
- **Real-time updates**: WebSocket events when a ticket is created, when it’s “now serving,” and when queue positions change (Laravel Reverb).
- **Multi-tenant & multi-office**: Tenants, offices, counters, services; tickets filtered by `office_id`, `queue_id`, `counter_id`, etc.
- **SMS notifications**: Optional SMS on ticket creation (and related listeners).
- **REST API**: Full CRUD for tickets under `/api/qms/tickets`; auth via Laravel Sanctum.
- **Audit**: Audit trail support for tracking changes.

---

## Screenshots & inspiration

The QMS in action — use these screenshots as inspiration for queue UIs, office displays, and clerk dashboards. All images live in `public/images`.

### 1. Queue system overview

![Queue system overview](public/images/Screenshot%202026-02-19%20at%2010.30.15%20AM.png)

### 2. Queue and tickets view

![Queue and tickets view](public/images/Screenshot%202026-02-19%20at%2010.30.32%20AM.png)

### 3. Ticket and service screen

![Ticket and service screen](public/images/Screenshot%202026-02-19%20at%2010.31.01%20AM.png)

### 4. Display / dashboard

![Display or dashboard](public/images/Screenshot%202026-02-19%20at%2010.31.09%20AM.png)

### 5. Queue management

![Queue management](public/images/Screenshot%202026-02-19%20at%2010.33.59%20AM.png)

### 6. Queue and counters

![Queue and counters](public/images/Screenshot%202026-02-19%20at%2010.34.23%20AM.png)

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12 |
| PHP | ^8.2 |
| Real-time | Laravel Reverb (WebSockets) |
| Auth (API) | Laravel Sanctum |
| Database | MySQL / PostgreSQL / SQLite (via Laravel migrations) |

---

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm (for frontend assets if used)
- Redis (optional, for cache/sessions/queues)

---

## Installation

### 1. Clone and install dependencies

```bash
git clone <your-repo-url> laravel-socket
cd laravel-socket
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Environment

Edit `.env`:

- **Database**: `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- **Broadcasting / Reverb** (for real-time):

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

- **Frontend WebSocket** (if your frontend connects to Reverb):

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- **SMS** (optional): Configure your SMS driver in `config/services.php` and set the relevant env vars.

### 3. Database

```bash
php artisan migrate
php artisan db:seed   # if you use seeders (tenants, services, roles, etc.)
```

### 4. Run the app

**Option A – All-in-one (dev)**

```bash
composer run dev
```

This typically starts: `php artisan serve`, Reverb, queue worker, and frontend dev server.

**Option B – Manual (dev)**

```bash
# Terminal 1 – Web
php artisan serve

# Terminal 2 – WebSockets
php artisan reverb:start

# Terminal 3 – Queue worker (if you use queued jobs)
php artisan queue:work
```

---

## Queue System Overview

### Concepts

- **Tenant** – Top-level tenant (e.g. organisation).
- **Office** – Physical office/branch.
- **Counter** – Service counter (1:1 with a **Queue**).
- **Queue** – One per counter; holds `status` (e.g. BUSY, NORMAL, FREE), `members_waiting`, `average_wait_time`, etc.
- **Ticket** – One per customer request; has `ticket_number`, `queue_id`, `queue_position`, `status`, `counter_id`, `clerk_id`, `office_id`, optional `priority`, timestamps (`called_at`, `serving_started_at`, `completed_at`).

### Ticket flow

1. **Create** – POST to API with `service_type_id`, `office_id`, `phone_number` (and optional data). Backend generates `ticket_number`, finds/creates queue by service + office, assigns `queue_id` and `queue_position`.
2. **Waiting** – Ticket sits in queue; position can change when others complete or priority tickets are added.
3. **Called** – Clerk “calls” the ticket; `TicketCalled` can broadcast to channel `queue.{queue_id}`.
4. **Serving** – Status set to `serving`; `TicketServing` event broadcasts to `tickets`, `office.{office_id}`, `queue.{queue_id}` so displays and dashboards can show “Now serving …”.
5. **Completed / Skipped / Cancelled** – Ticket leaves active queue; positions are recalculated.

### Real-time channels

| Channel | When used |
|--------|-----------|
| `tickets` | Global ticket updates (e.g. ticket.serving) |
| `office.{office_id}` | Office-scoped updates |
| `queue.{queue_id}` | Queue-scoped updates (e.g. next called, position changes) |

Events include:

- **TicketServing** – `ticket.serving` with ticket payload (id, ticket_number, service_type, queue_id, counter_id, clerk_id, office_id, status, etc.).
- **TicketCalled** – When a ticket is called (broadcasts to `queue.{queue_id}`).
- **QueuePositionUpdated** – When queue positions are recalculated (e.g. after add/remove/reorder).

---

## API Overview

Base path for QMS: **`/api/qms`**.

### Tickets (REST)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/qms/tickets` | Sanctum | List tickets (paginated), filter by `status`, `queue_id`, `service_id`, `counter_id`, `clerk_id`, `office_id`, `priority` |
| POST | `/api/qms/tickets` | No (public) | Create ticket; body: `service_type_id`, `office_id`, `phone_number` (+ optional fields) |
| GET | `/api/qms/tickets/{id}` | Sanctum | Get one ticket |
| PUT/PATCH | `/api/qms/tickets/{id}` | Sanctum | Update ticket (e.g. status, counter_id, clerk_id) |
| DELETE | `/api/qms/tickets/{id}` | Sanctum | Soft-delete ticket |

Create payload example:

```json
{
  "service_type_id": 1,
  "office_id": "office-1",
  "phone_number": "+255..."
}
```

Update payload can include: `status` (`waiting` \| `called` \| `serving` \| `completed` \| `skipped` \| `cancelled`), `queue_id`, `counter_id`, `clerk_id`, etc.

### Other domains

Under `/api/qms` with `auth:sanctum`: tenants, counters, counter types, services, service documents, devices, audit (see `routes/api.php` and each domain’s `routes.php`).

### Authentication

- Public: ticket creation (POST `/api/qms/tickets`).
- Protected: login via your Auth domain; then use Sanctum token (Bearer) for other QMS endpoints.

---

## Project structure (relevant to QMS)

```
app/
├── Domains/
│   └── Ticket/
│       ├── Controllers/TicketController.php
│       ├── Models/Ticket.php
│       ├── Requests/StoreTicketRequest.php, UpdateTicketRequest.php
│       ├── Repositories/TicketRepository.php
│       ├── Services/TicketService.php
│       └── routes.php
├── Events/
│   ├── TicketServing.php   # Broadcasts when status → serving
│   └── TicketCalled.php    # Broadcasts when ticket is called
├── Listeners/
│   └── BroadcastTicketServing.php
├── Services/
│   └── QueueService.php    # addToQueue, removeFromQueue, recalculateQueuePositions, getNextTicket, estimated wait
config/
├── broadcasting.php
└── reverb.php
database/migrations/
├── 2026_01_22_100027_create_queues_table.php
└── 2026_01_22_100028_create_tickets_table.php
public/
└── images/   # Screenshots used in this README
```

---

## Testing (optional)

```bash
composer run test
# or
php artisan test
```

---

## License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).
