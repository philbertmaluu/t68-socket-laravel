<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Ticket\Models\TicketDailySequence;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

class TicketNumberAllocator
{
    public const BUSINESS_TIMEZONE = 'Africa/Dar_es_Salaam';

    /** Max numeric suffix per letter block (matches voice assets A–Z and 1–500). */
    public const TICKET_NUM_MAX = 500;

    /**
     * Allocate the next display number for an office on the Tanzania business date.
     *
     * @return array{ticket_number: string, issued_on: string}
     */
    public function allocate(string $officeId, int $tenantId, ?Carbon $now = null): array
    {
        $issuedOn = ($now ?? Carbon::now(self::BUSINESS_TIMEZONE))->toDateString();
        $sequence = $this->lockOrCreate($tenantId, $officeId, $issuedOn);
        $sequence->last_value = (int) $sequence->last_value + 1;
        $sequence->save();

        return [
            'ticket_number' => $this->encode((int) $sequence->last_value),
            'issued_on' => $issuedOn,
        ];
    }

    /**
     * Map 1 → A1, 500 → A500, 501 → B1, 13001 → AA1.
     */
    public function encode(int $value): string
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('Ticket sequence value must be at least 1');
        }

        $zeroBased = $value - 1;
        $num = ($zeroBased % self::TICKET_NUM_MAX) + 1;
        $prefixIndex = intdiv($zeroBased, self::TICKET_NUM_MAX);

        return $this->prefixFromIndex($prefixIndex).$num;
    }

    private function lockOrCreate(int $tenantId, string $officeId, string $issuedOn): TicketDailySequence
    {
        $existing = $this->lockedRow($tenantId, $officeId, $issuedOn);
        if ($existing) {
            return $existing;
        }

        try {
            TicketDailySequence::query()->create([
                'tenant_id' => $tenantId,
                'office_id' => $officeId,
                'issued_on' => $issuedOn,
                'last_value' => 0,
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        $sequence = $this->lockedRow($tenantId, $officeId, $issuedOn);
        if (!$sequence) {
            throw new \RuntimeException("Unable to lock ticket sequence for office [{$officeId}] on [{$issuedOn}].");
        }

        return $sequence;
    }

    private function lockedRow(int $tenantId, string $officeId, string $issuedOn): ?TicketDailySequence
    {
        return TicketDailySequence::query()
            ->where('tenant_id', $tenantId)
            ->where('office_id', $officeId)
            ->whereDate('issued_on', $issuedOn)
            ->lockForUpdate()
            ->first();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = strtolower($e->getMessage());

        return $sqlState === '23000'
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }

    /**
     * Prefix index 0=A … 25=Z, 26=AA, 27=AB, … (length then lexicographic).
     */
    private function prefixFromIndex(int $index): string
    {
        $length = 1;
        $countForLength = 26;

        while ($index >= $countForLength) {
            $index -= $countForLength;
            $length++;
            $countForLength *= 26;
        }

        $chars = array_fill(0, $length, 'A');
        $remaining = $index;
        for ($i = $length - 1; $i >= 0; $i--) {
            $chars[$i] = chr(ord('A') + ($remaining % 26));
            $remaining = intdiv($remaining, 26);
        }

        return implode('', $chars);
    }
}
