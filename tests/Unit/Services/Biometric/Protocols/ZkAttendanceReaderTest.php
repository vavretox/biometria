<?php

namespace Tests\Unit\Services\Biometric\Protocols;

use App\Services\Biometric\Protocols\ZkAttendanceReader;
use Jmrashed\Zkteco\Lib\Helper\Util;
use PHPUnit\Framework\TestCase;

class ZkAttendanceReaderTest extends TestCase
{
    public function test_it_parses_iclock_580_attendance_records_without_shifting_the_timestamp(): void
    {
        $first = pack('va24CVCa8', 1, '1', 0, Util::encodeTime('2026-09-01 08:15:27'), 0, '');
        $second = pack('va24CVCa8', 2, '2', 0, Util::encodeTime('2026-09-01 16:45:50'), 1, '');
        $payload = pack('V', strlen($first.$second)).$first.$second;

        $attendance = (new ZkAttendanceReader)->parse($payload);

        $this->assertSame([
            ['uid' => 1, 'id' => '1', 'state' => 0, 'timestamp' => '2026-09-01 08:15:27', 'type' => 0],
            ['uid' => 2, 'id' => '2', 'state' => 0, 'timestamp' => '2026-09-01 16:45:50', 'type' => 1],
        ], $attendance);
    }

    public function test_it_ignores_an_incomplete_record(): void
    {
        $payload = pack('V', 40).str_repeat(chr(0), 20);

        $attendance = (new ZkAttendanceReader)->parse($payload);

        $this->assertSame([], $attendance);
    }
}
