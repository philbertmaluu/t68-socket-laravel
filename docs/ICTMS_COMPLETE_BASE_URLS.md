# ICTMS Complete Base URLs

This file provides complete URL references for ICTMS integration.

## QMS API Base URL

Use this as the base for all QMS endpoints:

- `https://queue-dev-api.nssf.go.tz/api`

---

## Access Management Endpoints (ICTMS -> QMS)

1. Modules
- `GET https://queue-dev-api.nssf.go.tz/api/modules`

2. Module Roles
- `POST https://queue-dev-api.nssf.go.tz/api/module/roles`

3. Assign Role
- `POST https://queue-dev-api.nssf.go.tz/api/assign-role`

4. User Roles (grouped by module)
- `GET https://queue-dev-api.nssf.go.tz/api/user/roles`

5. Module Users (roles for one PFNO)
- `POST https://queue-dev-api.nssf.go.tz/api/module/users`

6. Revoke Access
- `POST https://queue-dev-api.nssf.go.tz/api/access/revoke`

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
