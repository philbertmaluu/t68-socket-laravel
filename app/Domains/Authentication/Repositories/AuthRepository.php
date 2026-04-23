<?php

namespace App\Domains\Authentication\Repositories;

use App\Domains\Authentication\Models\User;
use App\Domains\Authentication\Models\UserRole;
use Illuminate\Support\Facades\DB;

class AuthRepository
{
    public function getEmployeeByToken(string $token): ?object
    {

        // Use HRPD database link
        $query = "select a.national_id, a.pfno, b.positionid, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email, b.du_id 
                  from hrpd.employee@preprod a 
                  join hrpd.vw_employee_details@preprod b on b.pfno = a.pfno 
                  left join hrpd.office@preprod c on c.office_id = b.office_id 
                  where a.accesstoken=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$token]);
    }

    

    public function getEmployeeByPfno(string $pfno): ?object
    {
       
        
        // Use HRPD database

        $query = "select a.national_id, a.pfno, b.positionid, b.du_id, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email 
                  from hrpd.employee a 
                  join hrpd.vw_employee_details b on b.pfno = a.pfno 
                  left join hrpd.office c on c.office_id = b.office_id 
                  where a.pfno=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$pfno]);
    
    }

    public function getEmployeeProfile(string $pfno): ?object
    {
        
        // Use HRPD database
        
        $query = "SELECT A.NATIONAL_ID, A.PFNO, A.FNAME, A.MNAME, A.SNAME, A.GENDER, C.OFFICE_CODE, B.OFFICE_NAME, B.POSITIONID, A.MOBILE, A.EMAIL 
                  FROM HRPD.EMPLOYEE A 
                  JOIN HRPD.VW_EMPLOYEE_DETAILS B ON B.PFNO = A.PFNO 
                  LEFT JOIN HRPD.OFFICE C ON C.OFFICE_ID = B.OFFICE_ID 
                  WHERE A.PFNO=? AND A.EMPLOYEE_STATUS='A'";
        
        return DB::selectOne($query, [$pfno]);
        
    }

    public function findUserByPfno(string $pfno): ?User
    {
        return User::where('user_id', $pfno)->first();
    }

    public function findUserByRefreshToken(string $refreshToken): ?User
    {
        return User::where('refresh_token', $refreshToken)->first();
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function updateRefreshToken(User $user, string $refreshToken): bool
    {
        $user->refresh_token = $refreshToken;
        return $user->save();
    }

    public function getUserRoles(string $pfno): array
    {
        // Find user by user_id (pfno) to get the actual user ID
        $user = User::withoutTenant()->where('user_id', $pfno)->first();
        if (!$user) {
            return [];
        }

        // Group by role_code to avoid duplicates (if same role was assigned multiple times)
        return DB::table('user_roles as a')
            ->join('roles as b', 'a.role_id', '=', 'b.id')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('b.role_code', 'b.role_name', 'c.name as module_name', DB::raw('MIN(a.role_id) as role_id'))
            ->where('a.user_id', $user->id)
            ->where(function($query) {
                $query->whereNull('a.end_date')
                      ->orWhere('a.end_date', '>=', now());
            })
            ->where('a.start_date', '<=', now())
            ->groupBy('b.role_code', 'b.role_name', 'c.name')
            ->orderBy('c.name')
            ->orderBy('b.role_name')
            ->get()
            ->toArray();
    }

    public function getUserRolesByModule(string $pfno, ?int $moduleId): \Illuminate\Support\Collection
    {
        if (!$moduleId) {
            return collect();
        }

        // Find user by user_id (pfno) to get the actual user ID
        $user = User::withoutTenant()->where('user_id', $pfno)->first();
        if (!$user) {
            return collect();
        }

        return DB::table('user_roles as a')
            ->join('roles as b', 'a.role_id', '=', 'b.id')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('a.role_id', 'b.role_code', 'b.role_name', 'c.name as module_name')
            ->where('a.user_id', $user->id)
            ->where('c.id', $moduleId)
            ->where(function($query) {
                $query->whereNull('a.end_date')
                      ->orWhere('a.end_date', '>=', now());
            })
            ->where('a.start_date', '<=', now())
            ->orderBy('c.name')
            ->orderBy('b.role_name')
            ->get();
    }

    /**
     * Get active roles for a specific internal users.id value (USER_ROLES.USER_ID).
     */
    public function getUserRolesByUserId(int $userId): array
    {
        return DB::table('user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->leftJoin('modules as m', 'm.id', '=', 'r.module_id')
            ->where('ur.user_id', $userId)
            ->whereNull('ur.deleted_at')
            ->where('ur.status', 'active')
            ->where(function ($query) {
                $query->whereNull('ur.end_date')
                    ->orWhere('ur.end_date', '>=', now());
            })
            ->where('ur.start_date', '<=', now())
            ->select(
                'ur.id as user_role_id',
                'ur.user_id',
                'u.user_id as pfno',
                'u.name as fullname',
                'r.id as role_id',
                'r.role_code',
                'r.role_name',
                'm.name as module_name',
                'ur.status',
                'ur.start_date',
                'ur.end_date'
            )
            ->orderBy('m.name')
            ->orderBy('r.role_name')
            ->get()
            ->map(fn ($row) => [
                'user_role_id' => (int) $row->user_role_id,
                'user_id' => (int) $row->user_id,
                'pfno' => (string) ($row->pfno ?? ''),
                'fullname' => $row->fullname ?? 'Unknown',
                'role_id' => (int) $row->role_id,
                'role_code' => (string) ($row->role_code ?? ''),
                'role_name' => (string) ($row->role_name ?? ''),
                'module_name' => (string) ($row->module_name ?? ''),
                'status' => (string) ($row->status ?? ''),
                'start_date' => $row->start_date ? \Carbon\Carbon::parse($row->start_date)->toIso8601String() : null,
                'end_date' => $row->end_date ? \Carbon\Carbon::parse($row->end_date)->toIso8601String() : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Get active roles for a PFNO (users.user_id), resolved to internal users.id first.
     */
    public function getUserRolesByPfno(string $pfno): array
    {

        $user = User::withoutTenant()->where('user_id', trim($pfno))->first();
        if (!$user) {
            return [];
        }

        return $this->getUserRolesByUserId((int) $user->id);
    }

    public function getPublicRoles(?int $moduleId): \Illuminate\Support\Collection
    {
        if (!$moduleId) {
            return collect();
        }

        return DB::table('roles as b')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->where('c.id', $moduleId)
            ->select(DB::raw('b.id as role_id'), 'b.role_code', 'b.role_name', 'c.name as module_name')
            ->whereIn('b.id', [8, 10, 41, 55, 61, 77, 81, 98])
            ->orderBy('c.name')
            ->orderBy('b.role_name')
            ->get();
    }

    public function getTransferRoles(): array
    {
        return DB::table('roles as b')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('b.id', 'b.role_code', 'b.role_name', 'c.name as module_name')
            ->where('c.id', 8)
            ->orderBy('c.name')
            ->orderBy('b.role_name')
            ->get()
            ->toArray();
    }

    public function getLocalModules(string $userId): \Illuminate\Support\Collection
    {
        // Find user by user_id to get the actual user ID
        $user = User::withoutTenant()->where('user_id', $userId)->first();
        if (!$user) {
            return collect();
        }

        return DB::table('modules as a')
            ->select('a.id as module_id', 'a.name as module_name', DB::raw('COUNT(b.id) as role_count'))
            ->join('roles as b', 'a.id', '=', 'b.module_id')
            ->join('user_roles as c', 'c.role_id', '=', 'b.id')
            ->where('c.user_id', $user->id)
            ->where(function($query) {
                $query->whereNull('c.end_date')
                      ->orWhere('c.end_date', '>=', now());
            })
            ->where('c.start_date', '<=', now())
            ->groupBy('a.id', 'a.name')
            ->get();
    }

    public function getModulesList(): \Illuminate\Support\Collection
    {
        return DB::table('modules')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function getPublicModules(): \Illuminate\Support\Collection
    {
        return DB::table('modules as a')
            ->select('a.id as module_id', 'a.name as module_name', DB::raw('COUNT(b.id) as role_count'))
            ->join('roles as b', 'a.id', '=', 'b.module_id')
            ->whereIn('b.id', [8, 10, 41, 55, 61, 77, 81, 98])
            ->groupBy('a.id', 'a.name')
            ->get();
    }

    public function getExternalModules(string $userId): \Illuminate\Support\Collection
    {
        $externalModules = collect();

        $payrollRoles = $this->getPayrollRoles($userId);
        if (count($payrollRoles) > 0) {
            $externalModules->push([
                'module_id' => 'payroll_external',
                'module_name' => 'Payroll Management',
                'role_count' => count($payrollRoles)
            ]);
        }

        $allowanceRoles = $this->getAllowanceRoles($userId);
        if (count($allowanceRoles) > 0) {
            $externalModules->push([
                'module_id' => 'allowance_external',
                'module_name' => 'Allowance Management',
                'role_count' => count($allowanceRoles)
            ]);
        }

        $cardRoles = $this->getCardRoles($userId);
        if (count($cardRoles) > 0) {
            $externalModules->push([
                'module_id' => 'card_external',
                'module_name' => 'Card Management',
                'role_count' => count($cardRoles)
            ]);
        }

        $employeeRoles = $this->getEmployeeRoles($userId);
        if (count($employeeRoles) > 0) {
            $externalModules->push([
                'module_id' => 'employee_external',
                'module_name' => 'Employee Management',
                'role_count' => count($employeeRoles)
            ]);
        }

        return $externalModules;
    }

    private function getPayrollRoles(string $userId): array
    {
        return DB::select("
            SELECT UR.ROLE_ID, R.NAME AS ROLE_NAME, R.MODULE_NAME
            FROM LOAN.USER_ROLE_MAPPING UR
            INNER JOIN LOAN.ROLES R ON R.ID = UR.ROLE_ID
            WHERE UR.PFNO = ? AND R.MODULE_NAME = 'PAYROLL'
        ", [$userId]);
    }

    private function getAllowanceRoles(string $userId): array
    {
        return DB::select("
            SELECT UR.ROLE_ID, R.NAME AS ROLE_NAME, R.MODULE_NAME
            FROM LOAN.USER_ROLE_MAPPING UR
            INNER JOIN LOAN.ROLES R ON R.ID = UR.ROLE_ID
            WHERE UR.PFNO = ? AND R.MODULE_NAME = 'PAYROLL'
        ", [$userId]);
    }

    private function getCardRoles(string $userId): array
    {
        return DB::select("
            SELECT UR.ROLE_ID, R.NAME AS ROLE_NAME, R.MODULE_NAME
            FROM LOAN.USER_ROLE_MAPPING UR
            INNER JOIN LOAN.ROLES R ON R.ID = UR.ROLE_ID
            WHERE UR.PFNO = ? AND R.MODULE_NAME = 'PAYROLL'
        ", [$userId]);
    }

    private function getEmployeeRoles(string $userId): array
    {
        return DB::select("
            SELECT E.PFNO, U.AID, RA.NAME AS ROLE_NAME
            FROM HRPD.USER_ROLE U
            INNER JOIN HRPD.EMPLOYEE E ON E.NATIONAL_ID = U.USERID
            INNER JOIN HRPD.ROLE_ACTIONS RA ON RA.AID = U.AID
            WHERE TO_DATE(TO_CHAR(TDATE, 'DD/MM/RRRR'), 'DD/MM/RRRR') >= TO_DATE(TO_CHAR(SYSDATE, 'DD/MM/RRRR'), 'DD/MM/RRRR')
              AND E.PFNO = ?
        ", [$userId]);
    }

    /**
     * Get roles for a module (ICTMS format: role_id, role).
     */
    public function getRolesByModuleId(int $moduleId): \Illuminate\Support\Collection
    {
        return DB::table('roles as r')
            ->join('modules as m', 'm.id', '=', 'r.module_id')
            ->where('r.module_id', $moduleId)
            ->whereNull('r.deleted_at')
            ->select('r.id as role_id', 'r.role_name as role')
            ->orderBy('r.role_name')
            ->get();
    }

    /**
     * Get all users grouped by module for ICTMS GET /api/user/roles.
     * Returns structure: data[][ data: [ { module, user: [ { id, pfno, fullname, role, from, to, module } ] } ] ]
     */
    public function getUsersGroupedByModuleForIctms(): array
    {
        $rows = DB::table('user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('modules as m', 'm.id', '=', 'r.module_id')
            ->whereNull('ur.deleted_at')
            ->where('ur.status', 'active')
            ->where(function ($q) {
                $q->whereNull('ur.end_date')->orWhere('ur.end_date', '>=', now());
            })
            ->where('ur.start_date', '<=', now())
            ->select(
                'ur.id',
                'u.user_id as pfno',
                'u.name as fullname',
                'r.role_name as role',
                'ur.start_date',
                'ur.end_date',
                'm.name as module_name'
            )
            ->orderBy('m.name')
            ->orderBy('u.name')
            ->get();

        $byModule = $rows->groupBy('module_name');
        $data = [];
        foreach ($byModule as $moduleName => $userRows) {
            $data[] = [
                'data' => [
                    [
                        'module' => $moduleName,
                        'user' => $userRows->map(fn ($row) => [
                            'id' => (string) $row->id,
                            'pfno' => (string) $row->pfno,
                            'fullname' => $row->fullname ?? 'Unknown',
                            'role' => $row->role,
                            'from' => $row->start_date ? \Carbon\Carbon::parse($row->start_date)->format('d-M-y') : '',
                            'to' => $row->end_date ? \Carbon\Carbon::parse($row->end_date)->format('d-M-y') : '',
                            'module' => $moduleName,
                        ])->values()->all(),
                    ],
                ],
            ];
        }
        return $data;
    }

    /**
     * Get all roles for one user in ICTMS format (POST /api/module/users).
     */
    public function getUserRolesIctmsFormat(string $pfno): array
    {
        $user = User::withoutTenant()->where('user_id', $pfno)->first();
        if (!$user) {
            return [];
        }

        $rows = DB::table('user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('modules as m', 'm.id', '=', 'r.module_id')
            ->where('ur.user_id', $user->id)
            ->whereNull('ur.deleted_at')
            ->where(function ($q) {
                $q->whereNull('ur.end_date')->orWhere('ur.end_date', '>=', now());
            })
            ->where('ur.start_date', '<=', now())
            ->select(
                'ur.id',
                'u.user_id as pfno',
                'u.name as fullname',
                'm.name as module_name',
                'r.id as role_id',
                'r.role_name as role',
                'ur.start_date as from_date',
                'ur.end_date as to_date'
            )
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (string) $row->id,
            'pfno' => (string) $row->pfno,
            'fullname' => $row->fullname ?? 'Unknown',
            'module_name' => $row->module_name,
            'role_id' => (string) $row->role_id,
            'role' => $row->role,
            'from_date' => $row->from_date ? \Carbon\Carbon::parse($row->from_date)->format('d-M-Y') : '',
            'to_date' => $row->to_date ? \Carbon\Carbon::parse($row->to_date)->format('d-M-Y') : '',
        ])->all();
    }

    /**
     * Ensure user exists for PFNO; create minimal user if not (name from HRPD or "Unknown").
     */
    public function getOrCreateUserByPfno(string $pfno, ?int $createdByUserId = null): User
    {
        $user = User::withoutTenant()->where('user_id', $pfno)->first();
        if ($user) {
            return $user;
        }

        $name = 'Unknown';
        try {
            $employee = $this->getEmployeeByPfno($pfno);
            if ($employee) {
                $name = trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? '')) ?: 'Unknown';
            }
        } catch (\Throwable $e) {
            // HRPD may be unavailable; use Unknown
        }

        return $this->createUser([
            'tenant_id' => 1,
            'user_id' => $pfno,
            'user_type' => 'staff',
            'name' => $name,
            'email' => 'pfno' . $pfno . '@nssf.local',
            'password' => bcrypt($pfno),
            'is_active' => true,
            'created_by' => $createdByUserId,
        ]);
    }

    /**
     * Resolve creator PFNO (or internal user id) to internal user id for created_by.
     * Accepts int or string so request payload CREATED_BY can be PFNO.
     */
    public function resolveCreatedBy(int|string|null $pfnoOrUserId): ?int
    {
        if ($pfnoOrUserId === null || $pfnoOrUserId === '') {
            return null;
        }
        $user = User::withoutTenant()->where('user_id', (string) $pfnoOrUserId)->first();
        if ($user) {
            return (int) $user->id;
        }
        return null;
    }

    /**
     * Assign role to user (ICTMS assign-role payload item).
     * Finds or creates the user from HRP (getOrCreateUserByPfno) if they do not exist.
     */
    public function assignRoleToUser(array $item): void
    {
        $pfno = (string) ($item['PFNO'] ?? $item['pfno'] ?? '');
        $roleId = (int) ($item['ROLE_ID'] ?? $item['role_id'] ?? 0);
        $fromDate = $item['FROM_DATE'] ?? $item['from_date'] ?? now()->format('Y-m-d');
        $toDate = $item['TO_DATE'] ?? $item['to_date'] ?? null;
        $createdByPfno = $item['CREATED_BY'] ?? null;

        if ($pfno === '' || !$roleId) {
            return;
        }

        $createdByUserId = $this->resolveCreatedBy($createdByPfno);
        $user = $this->getOrCreateUserByPfno($pfno, $createdByUserId);

        $from = \Carbon\Carbon::parse($fromDate)->startOfDay();
        $to = $toDate ? \Carbon\Carbon::parse($toDate)->endOfDay() : null;

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'start_date' => $from,
            'end_date' => $to,
            'status' => 'active',
            'created_by' => $createdByUserId,
        ]);
    }

    /**
     * Revoke a role from a user (set status to inactive).
     */
    public function revokeUserRole(string $pfno, string $roleId, ?string $updatedBy = null): bool
    {
        $user = User::withoutTenant()->where('user_id', $pfno)->first();
        if (!$user) {
            return false;
        }

        $updatedByUserId = $updatedBy !== null ? $this->resolveCreatedBy((int) $updatedBy) : null;

        $affected = UserRole::withoutTenant()
            ->where('user_id', $user->id)
            ->where('role_id', (int) $roleId)
            ->where('status', 'active')
            ->update([
                'status' => 'inactive',
                'updated_by' => $updatedByUserId,
            ]);

        return $affected > 0;
    }
}
