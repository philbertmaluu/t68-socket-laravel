# ICTMS Complete Base URLs

This file provides complete URL references for ICTMS integration.

## QMS API Base URL

Use this as the base for all QMS endpoints:

- `https://queue-dev-api.nssf.go.tz/api`

---

## Access Management Endpoints (ICTMS -> QMS)

Registered with ICTMS (`ictms:register`):

1. Modules
- `GET https://queue-dev-api.nssf.go.tz/api/modules`

2. Module Roles
- `POST https://queue-dev-api.nssf.go.tz/api/module/roles`

3. Assign Role (legacy ICTMS path)
- `POST https://queue-dev-api.nssf.go.tz/api/assign-role`

4. User Roles (grouped by module)
- `GET https://queue-dev-api.nssf.go.tz/api/user/roles`

5. Module Users (roles for one PFNO)
- `POST https://queue-dev-api.nssf.go.tz/api/module/users`

6. Revoke Access
- `POST https://queue-dev-api.nssf.go.tz/api/access/revoke`

---

## CFMS Pro–compatible aliases (same ICTMS contract as CFMSPRO)

These mirror CFMS Pro paths/payloads so ICTMS can call QMS the same way.

1. Assign roles (CFMS Pro body)
- `POST https://queue-dev-api.nssf.go.tz/api/cfmspro/assign-roles`
- Body: `{ "user_id": "<target PFNO>", "role_id": 123, "start_date": "2024-01-01", "end_date": "2024-12-31", "pfno": "<CREATED_BY PFNO>" }`
- Success message: `Role assigned to user successful`
- Duplicate active role: `Role Already Exist in User Profile!`

2. Roles for module (alias of `/api/module/roles`)
- `POST https://queue-dev-api.nssf.go.tz/api/roles`
- Body: `{ "moduleId": 100 }`

3. User profile roles
- `POST https://queue-dev-api.nssf.go.tz/api/cfmspro/users-profile`
- Body: `{ "pfno": 5549 }`
- `data`: `[{ id, role, role_id, start_date, end_date }]`

4. Has any access?
- `POST https://queue-dev-api.nssf.go.tz/api/cfmspro/users-access`
- Body: `{ "pfno": 5549 }`
- `data`: `1` or `0`

5. Revoke (same path as registered; CFMS Pro response)
- `POST https://queue-dev-api.nssf.go.tz/api/access/revoke`
- Body: `{ "pfno": "<target PFNO>", "role_id": 123 }`
- Success `data`: `{ user_name, user_email, role_name, module_name }`, message `Role removed successfully.`

Legacy `/api/assign-role` still accepts `{ PFNO, ROLE_ID, FROM_DATE, TO_DATE, CREATED_BY }` and also accepts the CFMS Pro field names above.

---

## Monitoring Endpoints (ICTMS -> QMS)

1. Service Health
- `GET https://queue-dev-api.nssf.go.tz/api/ictms/service`

2. Interface Status
- `GET https://queue-dev-api.nssf.go.tz/api/ictms/interface`

---

## ICTMS API Base URL (QMS -> ICTMS)

Used by QMS for registration and ICTMS integration calls:

- `https://ictmspre-api.nssf.go.tz/api`

Common integration targets:
- `https://ictmspre-api.nssf.go.tz/api/access/add-software-access`
- `https://ictmspre-api.nssf.go.tz/api/add-system`
- `https://ictmspre-api.nssf.go.tz/api/send-notification`

---

## If moving to another environment

Replace the host only, keep the same paths:

- QMS: `{QMS_BASE_URL}/api/...`
- ICTMS: `{ICTMS_API_BASE}/api/...`
