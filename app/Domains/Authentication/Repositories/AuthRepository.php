<?php

namespace App\Domains\Authentication\Repositories;

use App\Domains\Authentication\Models\User;
use Illuminate\Support\Facades\DB;

class AuthRepository
{
    public function getEmployeeByToken(string $token): ?object
    {
        // Use local users table for authentication
        return $this->getEmployeeByTokenLocal($token);
        
        // Production: Use HRPD database link
        // TODO: Uncomment when moving to production with HRPD server and DB schema hosted
        /*
        $query = "select a.national_id, a.pfno, b.positionid, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email, b.du_id 
                  from hrpd.employee@preprod a 
                  join hrpd.vw_employee_details@preprod b on b.pfno = a.pfno 
                  left join hrpd.office@preprod c on c.office_id = b.office_id 
                  where a.accesstoken=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$token]);
        */
    }

    /**
     * Local development method: Get employee data from local users table
     * In production, this will be replaced by HRPD database queries
     */
    private function getEmployeeByTokenLocal(string $token): ?object
    {
        // For local development, try multiple strategies to find user:
        // 1. Try to match token with user_id
        // 2. Try to match token with email
        // 3. For testing: if token doesn't match, use first active user (for development convenience)
        $user = User::withoutTenant()
            ->where('is_active', true)
            ->where(function($query) use ($token) {
                $query->where('user_id', $token)
                      ->orWhere('email', $token);
            })
            ->first();

        // If no exact match found, for local dev convenience, use first active user
        // This allows any token to work in development
        if (!$user) {
            $user = User::withoutTenant()
                ->where('is_active', true)
                ->first();
        }

        if (!$user) {
            return null;
        }

        // Split name into parts
        $nameParts = explode(' ', trim($user->name), 3);
        
        // Return object in the same format as HRPD query
        return (object) [
            'national_id' => $user->user_id, // Use user_id as national_id for local
            'pfno' => $user->user_id,
            'positionid' => null,
            'fname' => $nameParts[0] ?? '',
            'mname' => $nameParts[1] ?? '',
            'sname' => $nameParts[2] ?? '',
            'gender' => null,
            'office_code' => null,
            'office_name' => null,
            'mobile' => null,
            'email' => $user->email,
            'du_id' => null,
        ];
    }

    public function getEmployeeByPfno(string $pfno): ?object
    {
        // Use local users table
        return $this->getEmployeeByPfnoLocal($pfno);
        
        // Production: Use HRPD database
        // TODO: Uncomment when moving to production with HRPD server and DB schema hosted
        /*
        $query = "select a.national_id, a.pfno, b.positionid, b.du_id, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email 
                  from hrpd.employee a 
                  join hrpd.vw_employee_details b on b.pfno = a.pfno 
                  left join hrpd.office c on c.office_id = b.office_id 
                  where a.pfno=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$pfno]);
        */
    }

    /**
     * Local development method: Get employee data from local users table by PFNO
     */
    private function getEmployeeByPfnoLocal(string $pfno): ?object
    {
        $user = User::withoutTenant()
            ->where('user_id', $pfno)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return null;
        }

        // Return object in the same format as HRPD query
        $nameParts = explode(' ', $user->name, 3);
        return (object) [
            'national_id' => $user->user_id,
            'pfno' => $user->user_id,
            'positionid' => null,
            'du_id' => null,
            'fname' => $nameParts[0] ?? '',
            'mname' => $nameParts[1] ?? '',
            'sname' => $nameParts[2] ?? '',
            'gender' => null,
            'office_code' => null,
            'office_name' => null,
            'mobile' => null,
            'email' => $user->email,
        ];
    }

    public function getEmployeeProfile(string $pfno): ?object
    {
        // Use local users table
        return $this->getEmployeeByPfnoLocal($pfno);
        
        // Production: Use HRPD database
        // TODO: Uncomment when moving to production with HRPD server and DB schema hosted
        /*
        $query = "SELECT A.NATIONAL_ID, A.PFNO, A.FNAME, A.MNAME, A.SNAME, A.GENDER, C.OFFICE_CODE, B.OFFICE_NAME, B.POSITIONID, A.MOBILE, A.EMAIL 
                  FROM HRPD.EMPLOYEE A 
                  JOIN HRPD.VW_EMPLOYEE_DETAILS B ON B.PFNO = A.PFNO 
                  LEFT JOIN HRPD.OFFICE C ON C.OFFICE_ID = B.OFFICE_ID 
                  WHERE A.PFNO=? AND A.EMPLOYEE_STATUS='A'";
        
        return DB::selectOne($query, [$pfno]);
        */
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
}
