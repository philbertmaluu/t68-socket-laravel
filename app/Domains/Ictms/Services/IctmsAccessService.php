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
     * POST /api/module/roles - roles per module.
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
     */
    public function assignRole(array $payload): void
    {
        $items = is_array($payload) && isset($payload[0]) ? $payload : [$payload];
        foreach ($items as $item) {
            try {
                $this->repository->assignRoleToUser($item);
            } catch (\Throwable $e) {
                Log::warning('ICTMS assign-role item failed', ['item' => $item, 'error' => $e->getMessage()]);
                throw $e;
            }
        }
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
        $user = $this->repository->getOrCreateUserByPfno($pfno, $createdByUserId);
        $created = (bool) $user->wasRecentlyCreated;
        return [
            'pfno' => $user->user_id,
            'fullname' => $user->name ?? 'Unknown',
            'created' => $created,
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
     * POST /api/access/revoke - revoke role from user.
     */
    public function revokeAccess(string $pfno, string $roleId, ?string $updatedBy = null): bool
    {
        return $this->repository->revokeUserRole($pfno, $roleId, $updatedBy);
    }

    /**
     * Assign multiple roles to a user (QMS UI). Does not modify assign-role used by other systems.
     * Accepts either:
     * - An array of items: [ { PFNO, ROLE_ID, FROM_DATE?, TO_DATE?, CREATED_BY? }, ... ]
     * - A single object with ROLE_IDS: { PFNO, ROLE_IDS: [id, ...], FROM_DATE?, TO_DATE?, CREATED_BY? }
     * Each user is found or created from HRP if they do not exist; then each role is assigned.
     */
    public function assignRolesToUser(array $payload): void
    {
        $items = $this->expandAssignRolesPayload($payload);
        foreach ($items as $item) {
            try {
                $this->repository->assignRoleToUser($item);
            } catch (\Throwable $e) {
                Log::warning('assign-roles item failed', ['item' => $item, 'error' => $e->getMessage()]);
                throw $e;
            }
        }
    }

    /**
     * Expand assign-roles payload to a flat array of items (each with PFNO, ROLE_ID, FROM_DATE, TO_DATE, CREATED_BY).
     */
    private function expandAssignRolesPayload(array $payload): array
    {
        $roleIds = $payload['ROLE_IDS'] ?? $payload['role_ids'] ?? null;
        if (is_array($roleIds) && count($roleIds) > 0) {
            $pfno = $payload['PFNO'] ?? $payload['pfno'] ?? null;
            if ($pfno === null || $pfno === '') {
                return [];
            }
            $fromDate = $payload['FROM_DATE'] ?? $payload['from_date'] ?? now()->format('Y-m-d');
            $toDate = $payload['TO_DATE'] ?? $payload['to_date'] ?? null;
            $createdBy = $payload['CREATED_BY'] ?? $payload['created_by'] ?? null;
            $items = [];
            foreach ($roleIds as $roleId) {
                $items[] = [
                    'PFNO' => $pfno,
                    'ROLE_ID' => (int) $roleId,
                    'FROM_DATE' => $fromDate,
                    'TO_DATE' => $toDate,
                    'CREATED_BY' => $createdBy,
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
