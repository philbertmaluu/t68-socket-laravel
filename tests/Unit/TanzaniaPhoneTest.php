<?php

namespace Tests\Unit;

use App\Domains\SelfService\Support\TanzaniaPhone;
use PHPUnit\Framework\TestCase;

class TanzaniaPhoneTest extends TestCase
{
    public function test_accepts_local_07_and_06_and_international_255(): void
    {
        $this->assertSame('0748304649', TanzaniaPhone::normalize('0748304649'));
        $this->assertSame('0648303639', TanzaniaPhone::normalize('0648303639'));
        $this->assertSame('0748304649', TanzaniaPhone::normalize('255748304649'));
        $this->assertSame('0748304649', TanzaniaPhone::normalize('+255 748 304 649'));
    }

    public function test_rejects_member_id_style_and_short_values(): void
    {
        $this->assertNull(TanzaniaPhone::normalize('D4269152'));
        $this->assertNull(TanzaniaPhone::normalize('4269152'));
        $this->assertNull(TanzaniaPhone::normalize('5'));
        $this->assertNull(TanzaniaPhone::normalize(''));
        $this->assertNull(TanzaniaPhone::normalize('2554269152'));
    }

    public function test_falls_back_to_first_valid_candidate(): void
    {
        $this->assertSame(
            '0748304649',
            TanzaniaPhone::firstValid('D4269152', '0748304649')
        );
        $this->assertNull(TanzaniaPhone::firstValid('D4269152', '4269152'));
    }
}
