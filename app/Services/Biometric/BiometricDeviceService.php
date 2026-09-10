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

    public function connect($deviceId)
    {
        $device = BiometricDevice::find($deviceId);
        if (! $device) {
            throw new \Exception('Dispositivo no encontrado');
        }

        $this->device = $device;
        $this->protocol = $this->getProtocolInstance($device);

        try {
            $result = $this->protocol->connect($device);

            if ($result) {
                $this->isConnected = true;
                $device->connection_status = 'connected';
                $device->connection_error = null;
                $device->last_connection_test = now();
                $device->save();

                $this->logCommunication('connect', 'success', ['device' => $device->device_name]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $device->connection_status = 'error';
            $device->connection_error = $e->getMessage();
            $device->save();

            $this->logCommunication('connect', 'failed', [], $e->getMessage());
            throw $e;
        }
    }

    public function disconnect()
    {
        if ($this->protocol) {
            $this->protocol->disconnect();
        }
        $this->isConnected = false;

        if ($this->device) {
            $this->device->connection_status = 'disconnected';
            $this->device->save();
        }

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

    public function testConnection($device)
    {
        try {
            $this->connect($device->id);
            $this->disconnect();

            return true;
        } catch (\Exception $e) {
            return false;
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

        $time = $time ?? now();

        try {
            $result = $this->protocol->setTime($time);
            $this->logCommunication('set_time', 'success', ['time' => $time->toDateTimeString()]);

            return $result;
        } catch (\Exception $e) {
            $this->logCommunication('set_time', 'failed', ['time' => $time->toDateTimeString()], $e->getMessage());
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

    protected function logCommunication($command, $status, $requestData = [], $errorMessage = null, $responseCount = null)
    {
        $log = new BiometricCommunicationLog;
        $log->device_id = $this->device->id;
        $log->command = $command;
        $log->status = $status;
        $log->request_data = $requestData;
        $log->error_message = $errorMessage;
        $log->response_time_ms = null; // Se puede calcular
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
