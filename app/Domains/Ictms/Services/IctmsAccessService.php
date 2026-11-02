<?php

namespace App\Domains\Ictms\Services;

use App\Domains\Authentication\Repositories\AuthRepository;
use Illuminate\Support\Facades\Log;

class IctmsAccessService
{
    public function __construct(
        private AuthRepository $repository
    ) {
    }

    /**
     * GET /api/modules - list modules in ICTMS format.
     */
    public function getModules(): array
    {
        $rows = $this->repository->getModulesList();
        return $rows->map(fn ($row) => [
            'id' => (string) ($row->id ?? $row->module_id ?? ''),
            'module_name' => $row->name ?? $row->module_name ?? '',
        ])->values()->all();
    }

    /**
     * POST /api/module/roles | POST /api/roles - roles per module.
     */
    public function getRolesByModule(int $moduleId): array
    {
        $rows = $this->repository->getRolesByModuleId($moduleId);
        return $rows->map(fn ($row) => [
            'role_id' => (string) $row->role_id,
            'role' => $row->role ?? '',
        ])->values()->all();
    }

    /**
     * POST /api/assign-role - assign role(s) to user(s).
     * Each user is found or created from HRP (HRPD) if they do not exist; then the role is assigned.
     * Accepts legacy ICTMS, HRMS (USER_PFNO), and CFMS Pro field names.
     * Also unwraps ICTMS wrappers: { payload: [...] } / { data: [...] }.
     */
    public function assignRole(array $payload, bool $failIfAlreadyAssigned = false): void
    {
        $items = $this->unwrapAssignItems($payload);
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            try {
                $this->repository->assignRoleToUser($item, $failIfAlreadyAssigned);
            } catch (\Throwable $e) {
                Log::warning('ICTMS assign-role item failed', [
                    'item' => $item,
                    'raw_payload' => $payload,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function unwrapAssignItems(array $payload): array
    {
        foreach (['payload', 'data', 'items'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }
            $inner = $payload[$key];
            if ($inner === []) {
                continue;
            }
            if (isset($inner[0]) && is_array($inner[0])) {
                return array_values(array_filter($inner, 'is_array'));
            }

            return [$inner];
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [$payload];
    }

    /**
     * Find or create user from HRP by PFNO. Returns user info for UI (e.g. before assigning role).
     */
    public function getOrEnsureUserByPfno(string $pfno, ?int $createdByUserId = null): array
    {
        $pfno = trim($pfno);
        if ($pfno === '') {
            return ['pfno' => '', 'fullname' => '', 'created' => false];
        }
        $alreadyExisted = $this->repository->findUserByPfnoIncludingTrashed($pfno) !== null;
        $user = $this->repository->getOrCreateUserByPfno($pfno, $createdByUserId);
        return [
            'user_id' => (int) $user->id,
            'pfno' => $user->user_id,
            'fullname' => $user->name ?? 'Unknown',
            'created' => !$alreadyExisted,
        ];
    }

    /**
     * GET /api/user/roles - users grouped by module.
     */
    public function getUsersGroupedByModule(): array
    {
        return $this->repository->getUsersGroupedByModuleForIctms();
    }

    /**
     * POST /api/module/users - all roles for one user.
     */
    public function getUserRoles(string $pfno): array
    {
        return $this->repository->getUserRolesIctmsFormat($pfno);
    }

    /**
     * POST /api/cfmspro/users-profile - active roles in CFMS Pro shape.
     */
    public function getUserRolesCfmsProProfile(string $pfno): array
    {
        return $this->repository->getUserRolesCfmsProProfileFormat($pfno);
    }

    /**
     * POST /api/cfmspro/users-access - 1 if any active role, else 0.
     */
    public function userHasActiveAccess(string $pfno): int
    {
        return $this->repository->userHasActiveAccess($pfno);
    }

    /**
     * POST /api/access/revoke - hard-delete role from user (CFMS Pro contract).
     *
     * @return array{user_name: string, user_email: string|null, role_name: string, module_name: string}|null
     */
    public function revokeAccess(string $pfno, string $roleId, ?string $updatedBy = null): ?array
    {
        return $this->repository->revokeUserRole($pfno, $roleId, $updatedBy);
    }

    /**
     * Assign multiple roles to a user (QMS UI). Does not modify assign-role used by other systems.
     * Accepts either:
     * - An array of items: [ { PFNO, ROLE_ID, FROM_DATE?, TO_DATE?, CREATED_BY? }, ... ]
     * - A single object with ROLE_IDS: { PFNO, ROLE_IDS: [id, ...], FROM_DATE?, TO_DATE?, CREATED_BY? }
     * - CFMS Pro fields: user_id, role_id, start_date, end_date, pfno (created_by)
     * Creates the user from PFNO only when they do not already exist; then each role is assigned.
     *
     * @return list<array{user_id: int, pfno: string, fullname: string, created: bool}>
     */
    public function assignRolesToUser(array $payload, bool $failIfAlreadyAssigned = false): array
    {
        $items = $this->expandAssignRolesPayload($payload);
        if ($items === []) {
            throw new \InvalidArgumentException('pfno is required');
        }

        $results = [];
        foreach ($items as $item) {
            try {
                $results[] = $this->repository->assignRoleToUser($item, $failIfAlreadyAssigned);
            } catch (\Throwable $e) {
                Log::warning('assign-roles item failed', ['item' => $item, 'error' => $e->getMessage()]);
                throw $e;
            }
        }

        return $results;
    }

    /**
     * Expand assign-roles payload to a flat array of items.
     */
    private function expandAssignRolesPayload(array $payload): array
    {
        $roleIds = $payload['ROLE_IDS'] ?? $payload['role_ids'] ?? null;
        if (is_array($roleIds) && count($roleIds) > 0) {
            $normalizedBase = $this->repository->normalizeAssignRoleItem($payload);
            if ($normalizedBase['PFNO'] === '') {
                return [];
            }
            $items = [];
            foreach ($roleIds as $roleId) {
                $items[] = [
                    'PFNO' => $normalizedBase['PFNO'],
                    'ROLE_ID' => (int) $roleId,
                    'FROM_DATE' => $normalizedBase['FROM_DATE'],
                    'TO_DATE' => $normalizedBase['TO_DATE'],
                    'CREATED_BY' => $normalizedBase['CREATED_BY'],
                ];
            }
            return $items;
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload;
        }
        return [$payload];
    }
}
