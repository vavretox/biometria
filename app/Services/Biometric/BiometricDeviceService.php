<?php

namespace App\Services\Biometric;

use App\Models\Biometric\BiometricCommunicationLog;
use App\Models\Biometric\BiometricDevice;
use Illuminate\Support\Facades\Log;

class BiometricDeviceService
{
    protected $device;

    protected $protocol;

    protected $isConnected = false;

    protected string $connectionSource = 'operation';

    public ?bool $timeSynchronized = null;

    public ?string $timeSynchronizationError = null;

    public function connect(int $deviceId): bool
    {
        $device = BiometricDevice::find($deviceId);
        if (! $device) {
            throw new \Exception('Dispositivo no encontrado');
        }

        if ($this->isConnected) {
            $this->disconnect();
        }

        $this->device = $device;
        $this->isConnected = false;
        $this->protocol = $this->getProtocolInstance($device);

        $startedAt = hrtime(true);

        try {
            $result = $this->protocol->connect($device);

            if ($result) {
                $this->isConnected = true;
                $device->connection_status = 'connected';
                $device->connection_error = null;
                $device->last_connection_test = now();
                $device->save();

                $this->logCommunication('connect', 'success', ['source' => $this->connectionSource], responseTimeMs: (int) ((hrtime(true) - $startedAt) / 1_000_000));

                return true;
            }

            throw new \RuntimeException('No se pudo conectar al dispositivo');
        } catch (\Exception $e) {
            $this->isConnected = false;
            $device->last_connection_test = now();
            $device->connection_status = 'error';
            $device->connection_error = $e->getMessage();
            $device->save();

            $this->logCommunication('connect', 'failed', ['source' => $this->connectionSource], $e->getMessage(), responseTimeMs: (int) ((hrtime(true) - $startedAt) / 1_000_000));
            throw $e;
        }
    }

    public function disconnect(): bool
    {
        if ($this->protocol) {
            $this->protocol->disconnect();
        }
        $this->isConnected = false;

        return true;
    }

    public function getProtocolInstance($device)
    {
        $protocolClass = $device->getProtocolClassAttribute();

        if (! $protocolClass || ! class_exists($protocolClass)) {
            // Protocolo por defecto
            $protocolClass = Protocols\ZKProtocol::class;
        }

        return new $protocolClass;
    }

    public function testConnection(BiometricDevice $device, string $source = 'manual'): bool
    {
        $this->connectionSource = $source;
        $this->timeSynchronized = null;
        $this->timeSynchronizationError = null;

        try {
            $this->connect($device->id);
            try {
                $timezone = config('services.biometric.timezone');
                $this->setTime($device->id, now($timezone));
                $this->timeSynchronized = true;
                $this->device->update(['timezone' => $timezone]);
            } catch (\Exception $exception) {
                $this->timeSynchronized = false;
                $this->timeSynchronizationError = $exception->getMessage();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        } finally {
            if ($this->isConnected) {
                $this->disconnect();
            }

            $this->connectionSource = 'operation';
            $device->refresh();
        }
    }

    public function getUsers($deviceId)
    {
        $this->ensureConnection($deviceId);

        try {
            $users = $this->protocol->getUsers();
            $this->logCommunication('get_users', 'success', [], null, count($users));

            return $users;
        } catch (\Exception $e) {
            $this->logCommunication('get_users', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    public function setUser($deviceId, $userData)
    {
        $this->ensureConnection($deviceId);

        try {
            $result = $this->protocol->setUser($userData);
            $this->logCommunication('set_user', 'success', ['user' => $userData['userid'] ?? 'unknown']);

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('set_user', 'failed', ['user' => $userData['userid'] ?? 'unknown'], $e->getMessage());
            throw $e;
        }
    }

    public function deleteUser(int $deviceId, int $uid): bool
    {
        $this->ensureConnection($deviceId);

        try {
            if (! $this->protocol->deleteUser($uid)) {
                throw new \RuntimeException('El biométrico no confirmó la eliminación del usuario.');
            }
            $this->logCommunication('delete_user', 'success', ['uid' => $uid]);

            return true;
        } catch (\Exception $exception) {
            $this->logCommunication('delete_user', 'failed', ['uid' => $uid], $exception->getMessage());
            throw $exception;
        }
    }

    public function getFingerprints($deviceId, $uid): array
    {
        $this->ensureConnection($deviceId);

        try {
            $templates = $this->protocol->getFingerprints($uid);
            $this->logCommunication('get_fingerprints', 'success', ['uid' => $uid], null, count($templates));

            return $templates;
        } catch (\Exception $e) {
            $this->logCommunication('get_fingerprints', 'failed', ['uid' => $uid], $e->getMessage());
            throw $e;
        }
    }

    public function setFingerprints($deviceId, $uid, array $templates): mixed
    {
        $this->ensureConnection($deviceId);

        try {
            $result = $this->protocol->setFingerprints($uid, $templates);
            $this->logCommunication('set_fingerprints', 'success', ['uid' => $uid], null, count($templates));

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('set_fingerprints', 'failed', ['uid' => $uid], $e->getMessage());
            throw $e;
        }
    }

    public function getAttendance($deviceId, $startDate = null, $endDate = null)
    {
        $this->ensureConnection($deviceId);

        try {
            $attendance = $this->protocol->getAttendance($startDate, $endDate);
            $this->logCommunication('get_attendance', 'success', [
                'start' => $startDate,
                'end' => $endDate,
                'count' => count($attendance),
            ]);

            return $attendance;
        } catch (\Exception $e) {
            $this->logCommunication('get_attendance', 'failed', [
                'start' => $startDate,
                'end' => $endDate,
            ], $e->getMessage());
            throw $e;
        }
    }

    public function clearAttendance($deviceId)
    {
        $this->ensureConnection($deviceId);

        try {
            $result = $this->protocol->clearAttendance();
            $this->logCommunication('clear_attendance', 'success');

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('clear_attendance', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    public function getDeviceInfo($deviceId)
    {
        $this->ensureConnection($deviceId);

        try {
            $info = $this->protocol->getDeviceInfo();
            $this->logCommunication('get_device_info', 'success');

            return $info;
        } catch (\Exception $e) {
            $this->logCommunication('get_device_info', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    public function setTime($deviceId, $time = null)
    {
        $this->ensureConnection($deviceId);

        $time = $time ?? now(config('services.biometric.timezone'));

        try {
            $result = $this->protocol->setTime($time);
            if ($result === false || $result === null || (is_array($result) && ($result['success'] ?? true) === false)) {
                throw new \RuntimeException('El biométrico no confirmó la sincronización de hora.');
            }
            $this->logCommunication('set_time', 'success', ['time' => $time->toDateTimeString(), 'timezone' => $time->timezoneName, 'source' => $this->connectionSource]);

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('set_time', 'failed', ['time' => $time->toDateTimeString(), 'timezone' => $time->timezoneName, 'source' => $this->connectionSource], $e->getMessage());
            throw $e;
        }
    }

    public function restartDevice($deviceId)
    {
        $this->ensureConnection($deviceId);

        try {
            $result = $this->protocol->restart();
            $this->logCommunication('restart', 'success');

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('restart', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    public function powerOff($deviceId)
    {
        $this->ensureConnection($deviceId);

        try {
            $result = $this->protocol->powerOff();
            $this->logCommunication('power_off', 'success');

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('power_off', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    protected function ensureConnection($deviceId)
    {
        if (! $this->isConnected || ! $this->device || $this->device->id != $deviceId) {
            $this->connect($deviceId);
        }
    }

    protected function logCommunication($command, $status, $requestData = [], $errorMessage = null, $responseCount = null, ?int $responseTimeMs = null)
    {
        $log = new BiometricCommunicationLog;
        $log->device_id = $this->device->id;
        $log->command = $command;
        $log->status = $status;
        $log->request_data = $requestData;
        $log->error_message = $errorMessage;
        $log->response_time_ms = $responseTimeMs;
        $log->additional_info = ['response_count' => $responseCount];
        $log->save();

        if ($status === 'failed') {
            Log::error('Biometric communication error', [
                'device' => $this->device->device_name,
                'command' => $command,
                'error' => $errorMessage,
            ]);
        }
    }

    public function __destruct()
    {
        if ($this->isConnected) {
            $this->disconnect();
        }
    }
}
