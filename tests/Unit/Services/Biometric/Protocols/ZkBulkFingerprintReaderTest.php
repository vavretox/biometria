<?php

namespace Tests\Unit\Services\Biometric\Protocols;

use App\Services\Biometric\Protocols\ZkBulkFingerprintReader;
use PHPUnit\Framework\TestCase;

class ZkBulkFingerprintReaderTest extends TestCase
{
    public function test_it_parses_valid_vx9_templates_grouped_by_user_and_finger(): void
    {
        $first = pack('vvCC', 17, 1, 2, 1).'VX9TEMPLATE';
        $second = pack('vvCC', 14, 25, 7, 3).'DURESS99';
        $payload = pack('V', strlen($first.$second)).$first.$second;

        $templates = (new ZkBulkFingerprintReader)->parse($payload);

        $this->assertSame($first, $templates[1][2]);
        $this->assertSame($second, $templates[25][7]);
    }

    public function test_it_ignores_invalid_templates(): void
    {
        $invalid = pack('vvCC', 13, 1, 4, 0).'INVALID';
        $payload = pack('V', strlen($invalid)).$invalid;

        $templates = (new ZkBulkFingerprintReader)->parse($payload);

        $this->assertSame([], $templates);
    }

    public function test_it_stops_safely_when_an_entry_size_is_corrupt(): void
    {
        $payload = pack('VvvCC', 20, 500, 1, 2, 1).'SHORT';

        $templates = (new ZkBulkFingerprintReader)->parse($payload);

        $this->assertSame([], $templates);
    }
}
