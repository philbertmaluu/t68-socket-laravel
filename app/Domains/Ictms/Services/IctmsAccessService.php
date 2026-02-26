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
}
