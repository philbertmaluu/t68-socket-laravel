# Migration Order for Production

This document shows the correct migration order to avoid foreign key dependency errors.

## Migration Execution Order

### Phase 1: Base Tables (No Dependencies)
1. `0001_01_01_000000_create_users_table.php` - Laravel default (will be replaced)
2. `0001_01_01_000001_create_cache_table.php` - Laravel default
3. `0001_01_01_000002_create_jobs_table.php` - Laravel default
4. `2026_01_20_080000_create_tenants_table.php` - **Base table for multi-tenancy**
5. `2026_01_22_133701_create_personal_access_tokens_table.php` - Sanctum tokens

### Phase 2: Modules (Before Roles)
6. `2026_01_23_100050_create_modules_table.php` - **Must be before roles** (roles references modules)

### Phase 3: Users (Depends on Tenants)
7. `2026_01_23_100000_modify_users_table.php` - **Depends on: tenants**

### Phase 4: Authentication & Roles (Depends on Users, Modules)
8. `2026_01_23_100100_create_roles_table.php` - **Depends on: modules** (via module_id string reference)
9. `2026_01_23_100200_create_user_roles_table.php` - **Depends on: users, roles**
10. `2026_01_23_100300_create_handovers_table.php` - **Depends on: user_roles, users, roles**

### Phase 5: Core Domain Tables (Depends on Tenants)
11. `2026_01_22_095000_create_counter_types_table.php` - **Depends on: tenants**
12. `2026_01_22_095016_create_services_table.php` - **Depends on: tenants**
13. `2026_01_22_095317_create_service_documents_table.php` - **Depends on: services, tenants**
14. `2026_01_22_100026_create_counters_table.php` - **Depends on: tenants, counter_types, services**
15. `2026_01_22_101334_create_counter_clerk_table.php` - **Depends on: tenants, counters**
16. `2026_01_22_102000_create_devices_table.php` - **Depends on: tenants**
17. `2026_01_20_081700_create_tickets_table.php` - **Depends on: tenants**
18. `2026_01_20_081722_add_queue_position_to_tickets_table.php` - **Depends on: tickets**

### Phase 6: Audit & System Tables
19. `2026_01_22_110000_create_audit_trails_table.php` - **Depends on: tenants**

## Dependency Graph

```
tenants (base)
  ├── users
  │     └── user_roles
  │           └── handovers
  ├── counter_types
  │     └── counters
  │           └── counter_clerk
  ├── services
  │     ├── counters
  │     └── service_documents
  ├── devices
  ├── tickets
  └── audit_trails

modules (base)
  └── roles
        └── user_roles
```

## Notes

- **modules** must run before **roles** (roles.table references modules via module_id string)
- **users** must run after **tenants** (users has FK to tenants)
- **user_roles** must run after both **users** and **roles**
- **counters** must run after **counter_types** and **services**
- All domain tables depend on **tenants** for multi-tenancy

## Running Migrations

For production, simply run:
```bash
php artisan migrate --force
```

Laravel will execute migrations in chronological order based on the timestamp prefix.
