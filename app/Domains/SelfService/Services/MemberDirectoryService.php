<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Services;

use App\Domains\SelfService\Models\SelfServiceMember;
use App\Domains\SelfService\Repositories\CfmsMemberRepository;
use App\Domains\SelfService\Support\SelfServiceLog;

class MemberDirectoryService
{
    public function __construct(
        private CfmsMemberRepository $cfmsMemberRepository
    ) {
    }

    /**
     * @return array{member_number: string, member_name: string, phone: string, email?: string}
     */
    public function findByMemberNumber(string $memberNumber, int|string|null $tenantId = null): array
    {
        $normalized = $this->normalizeMemberNumber($memberNumber);
        SelfServiceLog::step('lookup.start', [
            'member_number' => $normalized,
            'tenant_id' => $tenantId,
        ]);

        if ($normalized === '') {
            throw new \RuntimeException('Member number is required');
        }

        $cfms = $this->cfmsMemberRepository->findByMemberId($normalized);
        if ($cfms === null) {
            SelfServiceLog::warning('lookup.not_found', ['member_number' => $normalized]);
            throw new \RuntimeException('Member not found');
        }

        $synced = $this->upsertFromCfms($cfms, $tenantId);

        SelfServiceLog::step('lookup.synced', [
            'member_number' => $synced['member_number'],
            'member_name' => $synced['member_name'],
            'phone' => $synced['phone'],
            'email' => $synced['email'] ?? '',
        ]);

        return $synced;
    }

    public function normalizeMemberNumber(string $memberNumber): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($memberNumber)) ?? '');
    }

    /**
     * @param  array{member_number: string, member_name: string, phone: string, email?: string}  $cfms
     * @return array{member_number: string, member_name: string, phone: string, email: string}
     */
    private function upsertFromCfms(array $cfms, int|string|null $tenantId): array
    {
        $query = SelfServiceMember::query()
            ->withoutGlobalScope('tenant')
            ->where('member_number', $cfms['member_number']);

        if ($tenantId !== null && $tenantId !== '') {
            $query->where(function ($inner) use ($tenantId) {
                $inner->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        $local = $query
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        $payload = [
            'member_number' => $cfms['member_number'],
            'member_name' => $cfms['member_name'],
            'phone' => $cfms['phone'],
            'email' => $cfms['email'] ?? null,
            'active' => true,
            'synced_at' => now(),
        ];

        if ($local === null) {
            $payload['tenant_id'] = $tenantId;
            $local = SelfServiceMember::withoutGlobalScope('tenant')->create($payload);
            SelfServiceLog::step('sync.inserted', [
                'member_number' => $local->member_number,
                'phone' => $local->phone,
            ]);
        } else {
            $changed = [];
            foreach (['member_name', 'phone', 'email'] as $field) {
                $incoming = (string) ($payload[$field] ?? '');
                $current = (string) ($local->{$field} ?? '');
                if ($incoming !== $current) {
                    $changed[$field] = ['from' => $current, 'to' => $incoming];
                }
            }

            $local->fill($payload);
            if ($tenantId !== null && $tenantId !== '' && empty($local->tenant_id)) {
                $local->tenant_id = $tenantId;
            }
            $local->save();

            if ($changed !== []) {
                SelfServiceLog::step('sync.updated', [
                    'member_number' => $local->member_number,
                    'changes' => $changed,
                ]);
            } else {
                SelfServiceLog::step('sync.unchanged', [
                    'member_number' => $local->member_number,
                ]);
            }
        }

        return [
            'member_number' => (string) $local->member_number,
            'member_name' => (string) ($local->member_name ?? ''),
            'phone' => (string) $local->phone,
            'email' => (string) ($local->email ?? ''),
        ];
    }
}
