<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Repositories;

use App\Domains\SelfService\Support\SelfServiceLog;
use App\Domains\SelfService\Support\TanzaniaPhone;
use Illuminate\Support\Facades\DB;
use Throwable;

class CfmsMemberRepository
{
    /**
     * @return array{
     *     member_number: string,
     *     member_name: string,
     *     phone: string,
     *     email: string,
     *     status: string
     * }|null
     */
    public function findByMemberId(string $memberNumber): ?array
    {
        $table = (string) config('self_service.cfms.table', 'CFMSPRO.MEMBERS@CFMSPLUS');
        if (preg_match('/^[A-Z0-9_@.]+$/i', $table) !== 1) {
            throw new \RuntimeException('Invalid CFMS members table configuration');
        }
        $activeStatus = (string) config('self_service.cfms.active_status', '1');

        SelfServiceLog::step('lookup.cfms.query', [
            'member_number' => $memberNumber,
            'table' => $table,
        ]);

        try {
            $sql = "
                SELECT MEMBER_ID, FIRST_NAME, MIDDLE_NAME, LAST_NAME,
                       PHONE_NUMBER, EXTRA_PHONE_NUMBER, EMAIL, STATUS
                FROM {$table}
                WHERE TO_CHAR(MEMBER_ID) = ?
                  AND STATUS = ?
                  AND ROWNUM = 1
            ";

            $rows = DB::select($sql, [$memberNumber, $activeStatus]);
        } catch (Throwable $e) {
            SelfServiceLog::warning('lookup.cfms.failed', [
                'member_number' => $memberNumber,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to look up member in CFMS');
        }

        if ($rows === []) {
            SelfServiceLog::step('lookup.cfms.miss', ['member_number' => $memberNumber]);

            return null;
        }

        $row = $rows[0];
        $mapped = $this->mapRow($row, $memberNumber);

        SelfServiceLog::step('lookup.cfms.hit', [
            'member_number' => $mapped['member_number'],
            'member_name' => $mapped['member_name'],
            'phone' => $mapped['phone'],
            'email' => $mapped['email'],
            'status' => $mapped['status'],
        ]);

        return $mapped;
    }

    /**
     * @return array{member_number: string, member_name: string, phone: string, email: string, status: string}
     */
    private function mapRow(object $row, string $fallbackNumber): array
    {
        $primaryPhone = $this->value($row, 'PHONE_NUMBER', 'phone_number');
        $extraPhone = $this->value($row, 'EXTRA_PHONE_NUMBER', 'extra_phone_number');
        $phone = TanzaniaPhone::firstValid($primaryPhone, $extraPhone);
        if ($phone === null) {
            SelfServiceLog::warning('lookup.cfms.invalid_phone', [
                'member_number' => $fallbackNumber,
                'phone_number' => $primaryPhone,
                'extra_phone_number' => $extraPhone,
            ]);
            throw new \RuntimeException('Member has no valid registered phone number');
        }

        $name = trim(implode(' ', array_filter([
            $this->value($row, 'FIRST_NAME', 'first_name'),
            $this->value($row, 'MIDDLE_NAME', 'middle_name'),
            $this->value($row, 'LAST_NAME', 'last_name'),
        ])));

        $memberId = $this->value($row, 'MEMBER_ID', 'member_id');
        if ($memberId !== '' && preg_match('/^\d+(\.0+)?$/', $memberId) === 1) {
            $memberId = (string) (int) $memberId;
        }

        return [
            'member_number' => $memberId !== '' ? $memberId : $fallbackNumber,
            'member_name' => $name,
            'phone' => $phone,
            'email' => $this->value($row, 'EMAIL', 'email'),
            'status' => $this->value($row, 'STATUS', 'status'),
        ];
    }

    private function value(object $row, string $upper, string $lower): string
    {
        $raw = $row->{$upper} ?? $row->{$lower} ?? null;

        return trim((string) $raw);
    }
}
