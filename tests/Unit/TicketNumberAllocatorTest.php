<?php

namespace Tests\Unit;

use App\Domains\Ticket\Services\TicketNumberAllocator;
use Tests\TestCase;

class TicketNumberAllocatorTest extends TestCase
{
    public function test_encode_matches_letter_block_sequence(): void
    {
        $allocator = new TicketNumberAllocator();

        $this->assertSame('A1', $allocator->encode(1));
        $this->assertSame('A2', $allocator->encode(2));
        $this->assertSame('A500', $allocator->encode(500));
        $this->assertSame('B1', $allocator->encode(501));
        $this->assertSame('Z500', $allocator->encode(26 * 500));
        $this->assertSame('AA1', $allocator->encode(26 * 500 + 1));
    }
}
