<?php

namespace App\Domains\Authentication\Repositories;

use App\Domains\Authentication\Models\User;
use Illuminate\Support\Facades\DB;

class AuthRepository
{
    public function getEmployeeByToken(string $token): ?object
    {
        $query = "select a.national_id, a.pfno, b.positionid, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email, b.du_id 
                  from hrpd.employee@preprod a 
                  join hrpd.vw_employee_details@preprod b on b.pfno = a.pfno 
                  left join hrpd.office@preprod c on c.office_id = b.office_id 
                  where a.accesstoken=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$token]);
    }

    public function getEmployeeByPfno(string $pfno): ?object
    {
        $query = "select a.national_id, a.pfno, b.positionid, b.du_id, a.fname, a.mname, a.sname, a.gender, c.office_code, b.office_name, a.mobile, a.email 
                  from hrpd.employee a 
                  join hrpd.vw_employee_details b on b.pfno = a.pfno 
                  left join hrpd.office c on c.office_id = b.office_id 
                  where a.pfno=? and a.employee_status='A'";
        
        return DB::selectOne($query, [$pfno]);
    }

    public function getEmployeeProfile(string $pfno): ?object
    {
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
        return DB::table('user_roles as a')
            ->join('roles as b', 'a.role_id', '=', 'b.id')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('a.role_id', 'b.role_code', 'b.role_name', 'c.module_name')
            ->where('a.user_id', $pfno)
            ->whereRaw('SYSDATE BETWEEN a.start_date AND a.end_date')
            ->orderBy('c.module_name')
            ->orderBy('b.role_name')
            ->get()
            ->toArray();
    }

    public function getUserRolesByModule(string $pfno, ?int $moduleId): \Illuminate\Support\Collection
    {
        if (!$moduleId) {
            return collect();
        }

        return DB::table('user_roles as a')
            ->join('roles as b', 'a.role_id', '=', 'b.id')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('a.role_id', 'b.role_code', 'b.role_name', 'c.module_name')
            ->where('a.user_id', $pfno)
            ->where('c.id', $moduleId)
            ->whereRaw('SYSDATE BETWEEN a.start_date AND a.end_date')
            ->orderBy('c.module_name')
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
            ->select(DB::raw('b.id as role_id'), 'b.role_code', 'b.role_name', 'c.module_name')
            ->whereIn('b.id', [8, 10, 41, 55, 61, 77, 81, 98])
            ->orderBy('c.module_name')
            ->orderBy('b.role_name')
            ->get();
    }

    public function getTransferRoles(): array
    {
        return DB::table('roles as b')
            ->join('modules as c', 'c.id', '=', 'b.module_id')
            ->select('b.id', 'b.role_code', 'b.role_name', 'c.module_name')
            ->where('c.id', 8)
            ->orderBy('c.module_name')
            ->orderBy('b.role_name')
            ->get()
            ->toArray();
    }

    public function getLocalModules(string $userId): \Illuminate\Support\Collection
    {
        return DB::table('modules as a')
            ->select('a.id as module_id', 'a.module_name', DB::raw('COUNT(b.id) as role_count'))
            ->join('roles as b', 'a.id', '=', 'b.module_id')
            ->join('user_roles as c', 'c.role_id', '=', 'b.id')
            ->where('c.user_id', $userId)
            ->whereRaw('SYSDATE BETWEEN c.start_date AND c.end_date')
            ->groupBy('a.id', 'a.module_name')
            ->get();
    }

    public function getPublicModules(): \Illuminate\Support\Collection
    {
        return DB::table('modules as a')
            ->select('a.id as module_id', 'a.module_name', DB::raw('COUNT(b.id) as role_count'))
            ->join('roles as b', 'a.id', '=', 'b.module_id')
            ->whereIn('b.id', [8, 10, 41, 55, 61, 77, 81, 98])
            ->groupBy('a.id', 'a.module_name')
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
