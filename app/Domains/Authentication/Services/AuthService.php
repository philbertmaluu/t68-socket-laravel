<?php

namespace App\Domains\Authentication\Services;

use App\Domains\Authentication\Repositories\AuthRepository;
use App\Domains\Authentication\Models\User;
use Illuminate\Support\Facades\Auth;
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
