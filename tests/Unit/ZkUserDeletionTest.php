<?php

namespace Tests\Unit;

use App\Services\Biometric\Protocols\ZKProtocol;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZkUserDeletionTest extends TestCase
{
    #[DataProvider('deletionResponses')]
    public function test_deletes_only_the_requested_uid_and_requires_a_success_ack(string|false $response, string $packet, bool $success): void
    {
        $clock = Mockery::mock(ZKTeco::class);
        $clock->_data_recv = $packet;
        $clock->shouldReceive('removeUser')->once()->with(42)->andReturn($response);
        $protocol = new class($clock) extends ZKProtocol
        {
            public function __construct(ZKTeco $clock)
            {
                $this->zk = $clock;
                $this->connected = true;
            }
        };

        $this->assertSame($success, $protocol->deleteUser(42));
        Mockery::close();
    }

    /** @return array<string, array{string|false, string, bool}> */
    public static function deletionResponses(): array
    {
        return [
            'empty successful payload' => ['', pack('vvvv', 2000, 0, 1, 1), true],
            'error acknowledgement' => ['', pack('vvvv', 2001, 0, 1, 1), false],
            'timeout' => [false, pack('vvvv', 2000, 0, 1, 1), false],
            'short response' => ['', 'x', false],
        ];
    }
}
