<?php

namespace App\Services\Biometric\Protocols;

use Jmrashed\Zkteco\Lib\Helper\Util;
use Jmrashed\Zkteco\Lib\ZKTeco;

class ZkAttendanceReader
{
    private const RecordSize = 40;

    /** @return array<int, array{uid: int, id: string, state: int, timestamp: string, type: int}> */
    public function read(ZKTeco $zk): array
    {
        $session = $zk->_command(Util::CMD_ATT_LOG_RRQ, '', Util::COMMAND_TYPE_DATA);

        if ($session === false) {
            return [];
        }

        $receivedData = Util::recData($zk);

        if (strlen($receivedData) < 12) {
            return [];
        }

        return $this->parse(substr($receivedData, 8));
    }

    /** @return array<int, array{uid: int, id: string, state: int, timestamp: string, type: int}> */
    public function parse(string $data): array
    {
        if (strlen($data) < 4) {
            return [];
        }

        $totalSize = unpack('Vsize', substr($data, 0, 4))['size'];
        $recordsData = substr($data, 4, min($totalSize, strlen($data) - 4));
        $attendance = [];

        while (strlen($recordsData) >= self::RecordSize) {
            $record = unpack('vuid/a24user_id/Cstate/Vtimestamp/Ctype/a8reserved', substr($recordsData, 0, self::RecordSize));
            $attendance[] = [
                'uid' => $record['uid'],
                'id' => rtrim($record['user_id'], chr(0).' '),
                'state' => $record['state'],
                'timestamp' => $this->decodeTime($record['timestamp']),
                'type' => $record['type'],
            ];
            $recordsData = substr($recordsData, self::RecordSize);
        }

        return $attendance;
    }

    private function decodeTime(int $value): string
    {
        $second = $value % 60;
        $value = intdiv($value, 60);
        $minute = $value % 60;
        $value = intdiv($value, 60);
        $hour = $value % 24;
        $value = intdiv($value, 24);
        $day = ($value % 31) + 1;
        $value = intdiv($value, 31);
        $month = ($value % 12) + 1;
        $year = intdiv($value, 12) + 2000;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
