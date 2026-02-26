# ICTMS Integration

This document describes the Queue Management System (QMS) integration with ICTMS (Information and Communication Technology Management System) for access management and system monitoring.

## Overview

- **Access Management**: ICTMS calls our API to list modules/roles and to assign or revoke user access. No authentication is applied to these endpoints (server-to-server); secure the network or use a reverse proxy as needed.
- **System Monitoring**: ICTMS calls our health and interface-status endpoints. You register QMS once with ICTMS using the `ictms:register` command (see below).

---

## What the `ictms:register` command does

ICTMS does not know your QMS API URLs by default. The command **tells ICTMS where to find QMS** so that:

1. **Access Management** – ICTMS can call your QMS endpoints to list modules/roles, assign or revoke roles for users (by PFNO), and list users per module.
2. **System Monitoring** – ICTMS can call your QMS endpoints to check service health (database, app, Redis) and interface stats (tickets, SMS, counter calls).

**Technically:** the command sends two HTTP POST requests to the ICTMS API:

| Request | ICTMS endpoint | What is sent |
|---------|---------------|--------------|
| 1 | `POST {ICTMS_API_BASE}/api/access/add-software-access` | QMS short code and the six access endpoint URLs. |
| 2 | `POST {ICTMS_API_BASE}/api/add-system` | QMS short code and the two monitoring URLs (service + interface). |

After that, ICTMS stores these URLs and uses them when admins manage QMS access or when the monitoring dashboard checks QMS. Run the command **once** (or again if your QMS base URL changes).

---

## How to integrate QMS with ICTMS (step-by-step)

### Step 1: Configure environment

In your QMS project `.env`, set:

```env
QMS_BASE_URL=https://queue-dev-api.nssf.go.tz
QMS_SHORT_CODE=QMS
ICTMS_API_BASE=https://ictmspre-api.nssf.go.tz
ICTMS_ACCESS_ENABLED=true
```

- `QMS_BASE_URL` must be the **public base URL** of your QMS API (no trailing slash). ICTMS will call e.g. `{QMS_BASE_URL}/api/modules`.
- Ensure the QMS server is reachable from the ICTMS server (firewall/network allows HTTPS).

### Step 2: Deploy QMS with the ICTMS integration

Deploy your Laravel app so these URLs are live:

- **Access:** `GET /api/modules`, `POST /api/module/roles`, `POST /api/assign-role`, `GET /api/user/roles`, `POST /api/module/users`, `POST /api/access/revoke`
- **Monitoring:** `GET /api/ictms/service`, `GET /api/ictms/interface`

Verify (from a machine that can reach QMS):

```bash
curl -s https://queue-dev-api.nssf.go.tz/api/modules
curl -s https://queue-dev-api.nssf.go.tz/api/ictms/service
```

You should get JSON responses.

### Step 3: Run the registration command

From the **QMS project directory** (with the same `.env`):

**Optional – dry run (no requests sent):**

```bash
php artisan ictms:register --dry-run
```

Check the printed QMS base URL and ICTMS API base. Then run for real:

```bash
php artisan ictms:register
```

Expected output:

- `POST .../api/access/add-software-access` → Access registration: OK
- `POST .../api/add-system` → System monitoring registration: OK

If both show OK, **registration is complete**. ICTMS now has your QMS URLs.

### Step 4: Use from ICTMS

- **Access:** In ICTMS, admins can assign/revoke QMS roles for users (by PFNO). ICTMS will call your QMS endpoints.
- **Monitoring:** ICTMS can show QMS in its dashboard and will call `/api/ictms/service` and `/api/ictms/interface` for health and stats.

### Step 5: If your QMS URL changes

1. Update `QMS_BASE_URL` in `.env` and redeploy if needed.
2. Run again: `php artisan ictms:register`.

---

## Environment Variables (reference)

Add to `.env`:

**QMS (this system – Queue Management System):**

| Variable | Description | Example |
|----------|-------------|---------|
| `QMS_BASE_URL` | Public base URL of the QMS API (URL ICTMS will call for access/monitoring). | `https://queue-dev-api.nssf.go.tz` |
| `QMS_SHORT_CODE` | Short code used when registering this system with ICTMS. | `QMS` |

**ICTMS (their system – integration & SMS):**

| Variable | Description | Example |
|----------|-------------|---------|
| `ICTMS_API_BASE` | ICTMS API base URL (registration and SMS). | `https://ictmspre-api.nssf.go.tz` |
| `ICTMS_ACCESS_ENABLED` | Set to `false` to disable the six access endpoints. | `true` |
| `ICTMS_API_ENDPOINT` | ICTMS send-notification endpoint (SMS). | `https://ictmspre-api.nssf.go.tz/api/send-notification` |
| `ICTMS_SYSTEM` | System name sent to ICTMS. | `ICTMS` |
| `ICTMS_SMS_ENABLED` | Enable/disable SMS via ICTMS. | `true` |

## Access Management Endpoints (ICTMS calls us)

All under the API prefix (e.g. `https://your-domain/api/`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/modules` | List system modules. Response: `{ success, status_code: 1, data: [{ id, module_name }], message }` |
| POST | `/api/module/roles` | Roles for a module. Body: `{ "moduleId": 100 }`. Response: `{ success, status_code: 1, data: [{ role_id, role }], message }` |
| POST | `/api/assign-role` | Assign role(s). Body: array of `{ PFNO, ROLE_ID, FROM_DATE, TO_DATE, CREATED_BY }`. |
| GET | `/api/user/roles` | Users grouped by module. Response: nested structure with module and user list. |
| POST | `/api/module/users` | All roles for one user. Body: `{ "pfno": 5549 }`. Response: `{ success, data: [{ id, pfno, fullname, module_name, role_id, role, from_date, to_date }], message }` |
| POST | `/api/access/revoke` | Revoke role. Body: `{ "pfno", "role_id", "system_code", "updated_by" }`. |

IDs (`moduleId`, `ROLE_ID`) are our internal database IDs. Use the same IDs when registering with ICTMS so ICTMS sends them back in assign/revoke requests.

## System Monitoring Endpoints (ICTMS calls us)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/ictms/service` | Service health. Response: `{ status: 1, message: "Success", data: [{ name, type, status, message }] }` (status 1 = healthy, 0 = down). |
| GET | `/api/ictms/interface` | Interface status. Response: `{ success: true, data: [{ name, total, overdue, error, status }], message }`. |

## One-Time Registration

Run once (or when base URL changes) to register QMS with ICTMS:

```bash
php artisan ictms:register
```

This sends:

1. **Access integration** – POST to ICTMS `add-software-access` with the six access endpoint URLs.
2. **System monitoring** – POST to ICTMS `add-system` with the service and interface URLs.

To only print payloads without sending:

```bash
php artisan ictms:register --dry-run
```

Ensure `QMS_BASE_URL` and `ICTMS_API_BASE` are set correctly before running.

## Security Notes

- The access and monitoring endpoints are not protected by Sanctum. Restrict access (e.g. firewall, VPN, or API gateway) or add API-key middleware if ICTMS supports it.
- Do not expose the registration command or ICTMS API credentials in public environments.
