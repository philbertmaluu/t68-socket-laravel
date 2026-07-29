<?php

namespace App\Domains\ModuleAdmin\Services;

use Illuminate\Support\Facades\DB;

class ModuleAdminService
{
    // ------------------------------------------------------------------ Modules

    public function listModules(): array
    {
        return DB::table('modules')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'module_id', 'code', 'name', 'description', 'is_active'])
            ->map(fn ($row) => [
                'id'          => (int) $row->id,
                'module_id'   => $row->module_id,
                'code'        => $row->code,
                'name'        => $row->name,
                'description' => $row->description,
                'is_active'   => (bool) $row->is_active,
            ])
            ->values()
            ->all();
    }

    public function createModule(array $data, int $createdBy): array
    {
        // Unique code check
        $exists = DB::table('modules')->whereNull('deleted_at')->where('code', $data['code'])->exists();
        if ($exists) {
            throw new \RuntimeException("A module with code '{$data['code']}' already exists.");
        }

        // Derive module_id (next integer string)
        $maxModuleId = DB::table('modules')->max(DB::raw('CAST(module_id AS UNSIGNED)'));
        $nextModuleId = (string) (((int) $maxModuleId) + 1);

        $id = DB::table('modules')->insertGetId([
            'module_id'   => $nextModuleId,
            'code'        => strtoupper(trim($data['code'])),
            'name'        => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'is_active'   => $data['is_active'] ?? true,
            'created_by'  => $createdBy,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $this->findModule($id);
    }

    public function updateModule(int $id, array $data, int $updatedBy): array
    {
        $module = DB::table('modules')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$module) {
            throw new \RuntimeException('Module not found.');
        }

        // Code uniqueness (excluding self)
        if (!empty($data['code'])) {
            $exists = DB::table('modules')
                ->whereNull('deleted_at')
                ->where('code', $data['code'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                throw new \RuntimeException("A module with code '{$data['code']}' already exists.");
            }
        }

        $updates = ['updated_at' => now(), 'updated_by' => $updatedBy];
        if (!empty($data['code']))        $updates['code']        = strtoupper(trim($data['code']));
        if (!empty($data['name']))        $updates['name']        = trim($data['name']);
        if (array_key_exists('description', $data)) $updates['description'] = $data['description'] ? trim($data['description']) : null;
        if (array_key_exists('is_active', $data))   $updates['is_active']   = (bool) $data['is_active'];

        DB::table('modules')->where('id', $id)->update($updates);

        return $this->findModule($id);
    }

    public function deleteModule(int $id): void
    {
        $module = DB::table('modules')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$module) {
            throw new \RuntimeException('Module not found.');
        }

        // Soft-delete roles too
        DB::table('roles')->whereNull('deleted_at')->where('module_id', $id)->update(['deleted_at' => now()]);

        DB::table('modules')->where('id', $id)->update(['deleted_at' => now()]);
    }

    private function findModule(int $id): array
    {
        $row = DB::table('modules')->where('id', $id)->first(['id', 'module_id', 'code', 'name', 'description', 'is_active']);
        return [
            'id'          => (int) $row->id,
            'module_id'   => $row->module_id,
            'code'        => $row->code,
            'name'        => $row->name,
            'description' => $row->description,
            'is_active'   => (bool) $row->is_active,
        ];
    }

    // ------------------------------------------------------------------ Roles

    public function listRoles(int $moduleId): array
    {
        return DB::table('roles')
            ->whereNull('deleted_at')
            ->where('module_id', $moduleId)
            ->orderBy('role_name')
            ->get(['id', 'module_id', 'role_code', 'role_name'])
            ->map(fn ($row) => [
                'id'        => (int) $row->id,
                'module_id' => (int) $row->module_id,
                'role_code' => $row->role_code,
                'role_name' => $row->role_name,
            ])
            ->values()
            ->all();
    }

    public function createRole(int $moduleId, array $data, int $createdBy): array
    {
        $module = DB::table('modules')->whereNull('deleted_at')->where('id', $moduleId)->first();
        if (!$module) {
            throw new \RuntimeException('Module not found.');
        }

        $exists = DB::table('roles')
            ->whereNull('deleted_at')
            ->where('module_id', $moduleId)
            ->where('role_code', strtoupper(trim($data['role_code'])))
            ->exists();
        if ($exists) {
            throw new \RuntimeException("Role code '{$data['role_code']}' already exists in this module.");
        }

        $id = DB::table('roles')->insertGetId([
            'module_id'  => $moduleId,
            'role_code'  => strtoupper(trim($data['role_code'])),
            'role_name'  => trim($data['role_name']),
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findRole($id);
    }

    public function updateRole(int $moduleId, int $roleId, array $data, int $updatedBy): array
    {
        $role = DB::table('roles')->whereNull('deleted_at')->where('id', $roleId)->where('module_id', $moduleId)->first();
        if (!$role) {
            throw new \RuntimeException('Role not found.');
        }

        if (!empty($data['role_code'])) {
            $exists = DB::table('roles')
                ->whereNull('deleted_at')
                ->where('module_id', $moduleId)
                ->where('role_code', strtoupper(trim($data['role_code'])))
                ->where('id', '!=', $roleId)
                ->exists();
            if ($exists) {
                throw new \RuntimeException("Role code '{$data['role_code']}' already exists in this module.");
            }
        }

        $updates = ['updated_at' => now(), 'updated_by' => $updatedBy];
        if (!empty($data['role_code'])) $updates['role_code'] = strtoupper(trim($data['role_code']));
        if (!empty($data['role_name'])) $updates['role_name'] = trim($data['role_name']);

        DB::table('roles')->where('id', $roleId)->update($updates);

        return $this->findRole($roleId);
    }

    public function deleteRole(int $moduleId, int $roleId): void
    {
        $role = DB::table('roles')->whereNull('deleted_at')->where('id', $roleId)->where('module_id', $moduleId)->first();
        if (!$role) {
            throw new \RuntimeException('Role not found.');
        }

        DB::table('roles')->where('id', $roleId)->update(['deleted_at' => now()]);
    }

    private function findRole(int $id): array
    {
        $row = DB::table('roles')->where('id', $id)->first(['id', 'module_id', 'role_code', 'role_name']);
        return [
            'id'        => (int) $row->id,
            'module_id' => (int) $row->module_id,
            'role_code' => $row->role_code,
            'role_name' => $row->role_name,
        ];
    }
}
