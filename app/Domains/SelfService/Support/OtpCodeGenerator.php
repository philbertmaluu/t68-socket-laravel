<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Support;

class OtpCodeGenerator
{
    public function generate(int $length = 6): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
