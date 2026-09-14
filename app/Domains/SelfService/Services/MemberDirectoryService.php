<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Services;

use App\Domains\SelfService\Models\SelfServiceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Domains\SelfService\Support\SelfServiceLog;

class MemberDirectoryService
{
    /**
     * @return array{member_number: string, member_name: string, phone: string}
     */
    public function findByMemberNumber(string $memberNumber, int|string|null $tenantId = null): array
    {
        $normalized = $this->normalizeMemberNumber($memberNumber);
        SelfServiceLog::step('lookup.start', [
            'member_number' => $normalized,
            'tenant_id' => $tenantId,
            'demo_enabled' => (bool) config('self_service.demo_enabled'),
            'oracle_enabled' => (bool) config('self_service.oracle.enabled'),
        ]);
        if ($normalized === '') {
            throw new \RuntimeException('Member number is required');
        }

        $local = $this->fromLocalDirectory($normalized, $tenantId);
        if ($local !== null) {
            SelfServiceLog::step('lookup.hit', [
                'source' => 'self_service_members',
                'member_number' => $local['member_number'],
                'member_name' => $local['member_name'],
                'phone' => $local['phone'],
            ]);
            return $local;
        }
        SelfServiceLog::step('lookup.miss', ['source' => 'self_service_members']);

        $oracle = $this->fromOracleMembers($normalized);
        if ($oracle !== null) {
            SelfServiceLog::step('lookup.hit', [
                'source' => 'oracle_members',
                'member_number' => $oracle['member_number'],
                'member_name' => $oracle['member_name'],
                'phone' => $oracle['phone'],
            ]);
            return $oracle;
        }
        SelfServiceLog::step('lookup.miss', ['source' => 'oracle_members']);

        if (config('self_service.demo_enabled')) {
            $demo = $this->fromDemo($normalized);
            SelfServiceLog::step('lookup.hit', [
                'source' => 'demo',
                'member_number' => $demo['member_number'],
                'phone' => $demo['phone'],
            ]);
            return $demo;
        }

        SelfServiceLog::warning('lookup.not_found', ['member_number' => $normalized]);
        throw new \RuntimeException('Member not found');
    }

    public function normalizeMemberNumber(string $memberNumber): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($memberNumber)) ?? '');
    }

    /**
     * @return array{member_number: string, member_name: string, phone: string}|null
     */
    private function fromLocalDirectory(string $memberNumber, int|string|null $tenantId): ?array
    {
        $query = SelfServiceMember::query()
            ->withoutGlobalScope('tenant')
            ->where('member_number', $memberNumber)
            ->where('active', true);

        if ($tenantId !== null && $tenantId !== '') {
            $query->where(function ($inner) use ($tenantId) {
                $inner->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        $member = $query
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->first();
        if (!$member) {
            return null;
        }

        return [
            'member_number' => (string) $member->member_number,
            'member_name' => (string) ($member->member_name ?? ''),
            'phone' => (string) $member->phone,
        ];
    }

    /**
     * @return array{member_number: string, member_name: string, phone: string}|null
     */
    private function fromOracleMembers(string $memberNumber): ?array
    {
        if (!config('self_service.oracle.enabled')) {
            return null;
        }

        $table = (string) config('self_service.oracle.table', 'MEMBERS');
        $idColumn = (string) config('self_service.oracle.member_number_column', 'MEMBER_ID');
        $phoneColumn = (string) config('self_service.oracle.phone_column', 'PHONE_NUMBER');

        try {
            if (!Schema::hasTable($table)) {
                return null;
            }

            $row = DB::table($table)->where($idColumn, $memberNumber)->first();
            if (!$row) {
                return null;
            }

            $phone = trim((string) ($row->{$phoneColumn} ?? $row->{strtolower($phoneColumn)} ?? ''));
            if ($phone === '') {
                throw new \RuntimeException('Member has no registered phone number');
            }

            $name = trim(implode(' ', array_filter([
                $this->column($row, (string) config('self_service.oracle.first_name_column')),
                $this->column($row, (string) config('self_service.oracle.middle_name_column')),
                $this->column($row, (string) config('self_service.oracle.last_name_column')),
            ])));

            return [
                'member_number' => $memberNumber,
                'member_name' => $name,
                'phone' => $phone,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Self-service Oracle member lookup failed', [
                'member_number' => $memberNumber,
                'error' => $e->getMessage(),
            ]);
            SelfServiceLog::warning('lookup.oracle_failed', [
                'member_number' => $memberNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{member_number: string, member_name: string, phone: string}
     */
    private function fromDemo(string $memberNumber): array
    {
        if (str_ends_with($memberNumber, '0000')) {
            throw new \RuntimeException('Member not found');
        }

        return [
            'member_number' => $memberNumber,
            'member_name' => (string) config('self_service.demo_name', 'Mwanachama'),
            'phone' => (string) config('self_service.demo_phone', '0712345678'),
        ];
    }

    private function column(object $row, string $column): string
    {
        if ($column === '') {
            return '';
        }

        return trim((string) ($row->{$column} ?? $row->{strtolower($column)} ?? ''));
    }
}
