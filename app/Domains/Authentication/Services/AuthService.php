<?php

namespace App\Domains\Authentication\Services;

use App\Domains\Authentication\Repositories\AuthRepository;
use App\Domains\Authentication\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    private AuthRepository $repository;

    public function __construct()
    {
        $this->repository = new AuthRepository();
    }

    public function authenticate(string $token): array
    {
        $employee = $this->repository->getEmployeeByToken($token);
        
        if (empty($employee)) {
            throw new \Exception('Failed Authentication.');
        }

        $user = $this->repository->findUserByPfno($employee->pfno);
        $refreshToken = Str::random(60);

        if (empty($user)) {
            return $this->createNewUser($employee, $refreshToken);
        }

        return $this->authenticateExistingUser($employee, $user, $refreshToken);
    }

    public function refreshToken(string $refreshToken): array
    {
        $user = $this->repository->findUserByRefreshToken($refreshToken);
        
        if (empty($user)) {
            throw new \Exception('Invalid Authorization.');
        }

        $newAccessToken = $user->createToken($user->user_id)->plainTextToken;
        $newRefreshToken = Str::random(60);
        
        $this->repository->updateRefreshToken($user, $newRefreshToken);

        $employee = $this->repository->getEmployeeByPfno($user->user_id);
        
        if (empty($employee)) {
            throw new \Exception('Invalid Authorization.');
        }

        return $this->buildTokenResponse($user, $employee, $newAccessToken, $newRefreshToken);
    }

    public function getUserDetails(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('User not authenticated.');
        }
        
        $employee = $this->repository->getEmployeeByPfno($user->user_id);
        
        if (empty($employee)) {
            throw new \Exception('User not found.');
        }

        $roles = $this->repository->getUserRoles($employee->pfno);
        
        return [
            'pfno' => $employee->pfno,
            'name' => $user->name,
            'phone_number' => $employee->mobile,
            'email' => $employee->email,
            'gender' => $employee->gender,
            'office_id' => $employee->office_code,
            'office_name' => $employee->office_name,
            'position_id' => $employee->positionid,
            'has_payment_role' => count($roles) > 0,
        ];
    }

    public function getUserRoles(string $module): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('User not authenticated.');
        }
        
        $userProfile = $this->repository->getEmployeeProfile($user->user_id);
        
        if (empty($userProfile)) {
            throw new \Exception('User not found.');
        }

        $moduleId = $this->getModuleId($module);
        $roles = $this->repository->getUserRolesByModule($userProfile->pfno, $moduleId);
        $publicRoles = $this->repository->getPublicRoles($moduleId);

        return $roles->concat($publicRoles)->toArray();
    }

    public function getTransferRoles(): array
    {
        return $this->repository->getTransferRoles();
    }

    public function getModuleAccess(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('User not authenticated.');
        }
        
        $userId = $user->user_id;
        $localModules = $this->repository->getLocalModules($userId);
        $publicModules = $this->repository->getPublicModules();
        $externalModules = $this->repository->getExternalModules($userId);

        return $localModules->concat($externalModules)->concat($publicModules)->toArray();
    }

    /**
     * Dev-only authentication helper.
     *
     * This method is intended for LOCAL DEVELOPMENT ONLY.
     * It generates a Sanctum token for a configured dev user
     * and returns the same response structure as authenticateExistingUser().
     *
     * @throws \Exception
     */
    public function devAuthenticate(): array
    {
        if (!app()->environment('local')) {
            throw new \Exception('Dev authentication is only available in local environment.');
        }

        // Prefer an explicit dev PFNO; fall back to the first active user if not set.
        $devPfno = config('app.dev_auth_user_pfno', env('DEV_AUTH_USER_PFNO'));

        /** @var User|null $user */
        if ($devPfno) {
            $user = $this->repository->findUserByPfno($devPfno);
        } else {
            $user = User::query()->where('is_active', true)->orderBy('id')->first();
        }

        if (!$user) {
            throw new \Exception('Dev user not found. Please set DEV_AUTH_USER_PFNO and ensure a matching user exists.');
        }

        // In dev we may not have a real HR/employee record; try to build a minimal stub.
        $employee = $this->repository->getEmployeeByPfno($user->user_id);

        if (empty($employee)) {
            // Build a minimal employee-like object so existing helpers still work.
            $employee = (object) [
                'pfno' => $user->user_id,
                'positionid' => null,
                'mobile' => $user->phone_number ?? null,
                'email' => $user->email,
                'gender' => null,
                'office_code' => null,
                'office_name' => null,
                'du_id' => null,
            ];
        }

        $refreshToken = Str::random(60);

        // Update refresh token and generate Sanctum token (same as authenticateExistingUser)
        $this->repository->updateRefreshToken($user, $refreshToken);

        $token = $user->createToken($employee->pfno ?? $user->user_id)->plainTextToken;
        $roles = $this->repository->getUserRoles($employee->pfno);

        Log::warning('Dev authentication used', [
            'pfno' => $employee->pfno,
            'user_id' => $user->id,
        ]);

        return [
            'profile' => $this->buildProfileResponse($user, $employee, $token, $refreshToken),
            'roles' => $roles,
            'current_role' => sizeof($roles) > 0 ? $roles[0] : [],
        ];
    }

    private function createNewUser(object $employee, string $refreshToken): array
    {
        $user = $this->repository->createUser([
            'tenant_id' => 1, // Default tenant, adjust as needed
            'name' => trim($employee->fname . ' ' . $employee->mname . ' ' . $employee->sname),
            'user_id' => $employee->pfno,
            'user_type' => 'staff',
            'email' => $employee->email,
            'password' => bcrypt($employee->national_id),
            'email_verified_at' => now(),
            'refresh_token' => $refreshToken,
            'is_active' => true,
        ]);

        $token = $user->createToken($employee->pfno)->plainTextToken;
        $roles = $this->repository->getUserRoles($employee->pfno);

        return [
            'profile' => $this->buildProfileResponse($user, $employee, $token, $refreshToken),
            'roles' => $roles,
            'current_role' => sizeof($roles) > 0 ? $roles[0] : [],
        ];
    }

    private function authenticateExistingUser(object $employee, User $user, string $refreshToken): array
    {
        // Update refresh token
        $this->repository->updateRefreshToken($user, $refreshToken);
        
        // Generate Sanctum token directly (no password check needed since token was verified from HRPD)
        $token = $user->createToken($employee->pfno)->plainTextToken;
        $roles = $this->repository->getUserRoles($employee->pfno);

        return [
            'profile' => $this->buildProfileResponse($user, $employee, $token, $refreshToken),
            'roles' => $roles,
            'current_role' => sizeof($roles) > 0 ? $roles[0] : [],
        ];
    }

    private function buildProfileResponse(User $user, object $employee, string $token, string $refreshToken): array
    {
        return [
            'token' => $token,
            'pfno' => $employee->pfno,
            'position_id' => $employee->positionid,
            'name' => $user->name,
            'phone_number' => $employee->mobile,
            'email' => $employee->email,
            'gender' => $employee->gender,
            'office_id' => $employee->office_code,
            'office_name' => $employee->office_name,
            'du_id' => $employee->du_id ?? null,
            'refresh_token' => $refreshToken,
        ];
    }

    private function buildTokenResponse(User $user, object $employee, string $token, string $refreshToken): array
    {
        return [
            'token' => $token,
            'pfno' => $user->user_id,
            'position_id' => $employee->positionid,
            'name' => $user->name,
            'phone_number' => $employee->mobile,
            'email' => $employee->email,
            'gender' => $employee->gender,
            'office_id' => $employee->office_code,
            'du_id' => $employee->du_id ?? null,
            'office_name' => $employee->office_name,
            'refresh_token' => $refreshToken,
        ];
    }

    private function getModuleId(string $module): ?int
    {
        $moduleIds = [
            'transfer' => 3,
            'disciplinary' => 4,
            'grievance' => 5,
            'medical' => 6,
            'leave' => 1,
            'training' => 7,
            'fleet' => 8,
            'library' => 9,
            'travelling' => 10,
            'adminpayment' => 11,
        ];

        return $moduleIds[$module] ?? null;
    }
}
