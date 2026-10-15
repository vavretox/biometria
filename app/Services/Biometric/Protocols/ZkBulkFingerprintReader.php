<?php

namespace App\Services\Biometric\Protocols;

use Jmrashed\Zkteco\Lib\Helper\Util;
use Jmrashed\Zkteco\Lib\ZKTeco;

class ZkBulkFingerprintReader
{
    private const CommandDatabaseRead = 7;

    private const CommandPrepareBuffer = 1503;

    private const CommandReadBuffer = 1504;

    private const MaximumChunkSize = 16384;

    /** @return array<int, array<int, string>> */
    public function read(ZKTeco $zk): array
    {
        $request = pack('CvVV', 1, self::CommandDatabaseRead, Util::FCT_FINGERTMP, 0);
        $session = $zk->_command(self::CommandPrepareBuffer, $request, Util::COMMAND_TYPE_DATA);

        if ($session === false || strlen($zk->_data_recv) < 8) {
            return [];
        }

        $response = unpack('vcommand', substr($zk->_data_recv, 0, 2));
        $command = $response['command'];

        if ($command === Util::CMD_DATA) {
            return $this->parse(substr($zk->_data_recv, 8));
        }

        if ($command === Util::CMD_PREPARE_DATA) {
            $receivedData = Util::recData($zk);

            return strlen($receivedData) >= 12
                ? $this->parse(substr($receivedData, 8))
                : [];
        }

        if ($command !== Util::CMD_ACK_OK) {
            return [];
        }

        $metadata = substr($zk->_data_recv, 8);

        if (strlen($metadata) < 5) {
            return [];
        }

        $announcedSize = unpack('Vsize', substr($metadata, 1, 4))['size'];

        if ($announcedSize < 4) {
            return [];
        }

        $data = '';

        try {
            for ($offset = 0; $offset < $announcedSize; $offset += self::MaximumChunkSize) {
                $chunkSize = min(self::MaximumChunkSize, $announcedSize - $offset);
                $chunk = $this->readChunk($zk, $offset, $chunkSize);

                if ($chunk === '') {
                    return [];
                }

                $data .= $chunk;
            }
        } finally {
            $zk->_command(Util::CMD_FREE_DATA, '');
        }

        return $this->parse(substr($data, 0, $announcedSize));
    }

    private function readChunk(ZKTeco $zk, int $offset, int $size): string
    {
        $session = $zk->_command(
            self::CommandReadBuffer,
            pack('VV', $offset, $size),
            Util::COMMAND_TYPE_DATA,
        );

        if ($session === false || strlen($zk->_data_recv) < 8) {
            return '';
        }

        $response = unpack('vcommand', substr($zk->_data_recv, 0, 2));

        if ($response['command'] === Util::CMD_DATA) {
            return substr($zk->_data_recv, 8);
        }

        if ($response['command'] === Util::CMD_PREPARE_DATA) {
            $receivedData = Util::recData($zk);

            return strlen($receivedData) >= 8 ? substr($receivedData, 8) : '';
        }

        return '';
    }

    /** @return array<int, array<int, string>> */
    public function parse(string $data): array
    {
        if (strlen($data) < 4) {
            return [];
        }

        $header = unpack('Vtotal_size', substr($data, 0, 4));
        $remainingSize = min($header['total_size'], strlen($data) - 4);
        $data = substr($data, 4, $remainingSize);
        $templates = [];

        while ($remainingSize >= 6 && strlen($data) >= 6) {
            $templateHeader = unpack('vsize/vuid/Cfinger/Cvalid', substr($data, 0, 6));
            $templateSize = $templateHeader['size'];

            if ($templateSize < 7 || $templateSize > $remainingSize || $templateSize > strlen($data)) {
                break;
            }

            if (in_array($templateHeader['valid'], [1, 3], true)) {
                $templates[$templateHeader['uid']][$templateHeader['finger']] = substr($data, 0, $templateSize);
            }

            $data = substr($data, $templateSize);
            $remainingSize -= $templateSize;
        }

        return $templates;
    }
}
