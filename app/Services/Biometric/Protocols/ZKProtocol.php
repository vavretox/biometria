<?php

namespace App\Services\Biometric\Protocols;

use App\Models\Biometric\BiometricDevice;
use Carbon\Carbon;
use Jmrashed\Zkteco\Lib\ZKTeco;

class ZKProtocol implements ProtocolInterface
{
    protected $zk;

    protected $device;

    protected $connected = false;

    /** @var array<int, array<int, string>>|null */
    protected ?array $bulkFingerprints = null;

    public function connect(BiometricDevice $device)
    {
        $this->device = $device;

        try {
            $this->zk = new ZKTeco(
                $device->device_ip,
                $device->device_port,
            );

            if ($this->zk->connect()) {
                $this->zk->disableDevice();
                $this->connected = true;
                $this->bulkFingerprints = null;

                return true;
            }

            throw new \Exception('No se pudo conectar al dispositivo');
        } catch (\Exception $e) {
            throw new \Exception('Error de conexión: '.$e->getMessage());
        }
    }

    public function disconnect()
    {
        if ($this->zk && $this->connected) {
            $this->zk->enableDevice();
            $this->zk->disconnect();
        }
        $this->connected = false;
        $this->bulkFingerprints = null;

        return true;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function getUsers()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->getUser();
    }

    public function setUser($userData)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->setUser(
            $userData['uid'] ?? null,
            $userData['userid'] ?? null,
            $userData['name'] ?? null,
            $userData['password'] ?? null,
            $userData['role'] ?? 0,
            $userData['cardno'] ?? 0
        );
    }

    public function getFingerprints($uid)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        if ($this->bulkFingerprints === null) {
            $this->bulkFingerprints = (new ZkBulkFingerprintReader)->read($this->zk);
        }

        if ($this->bulkFingerprints !== []) {
            return $this->bulkFingerprints[(int) $uid] ?? [];
        }

        return $this->zk->getFingerprint((int) $uid);
    }

    public function setFingerprints($uid, array $templates)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->setFingerprint((int) $uid, $templates);
    }

    public function getAttendance($startDate = null, $endDate = null)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        $attendance = (new ZkAttendanceReader)->read($this->zk);

        // Filtrar por fecha si se especifica
        if ($startDate || $endDate) {
            $startTimestamp = $startDate ? Carbon::parse($startDate)->startOfDay()->timestamp : 0;
            $endTimestamp = $endDate ? Carbon::parse($endDate)->endOfDay()->timestamp : PHP_INT_MAX;

            $attendance = array_filter($attendance, function ($log) use ($startTimestamp, $endTimestamp) {
                $timestamp = Carbon::parse($log['timestamp'])->timestamp;

                return $timestamp >= $startTimestamp && $timestamp <= $endTimestamp;
            });
        }

        return $attendance;
    }

    public function clearAttendance()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->clearAttendance();
    }

    public function getDeviceInfo()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return [
            'serial_number' => $this->zk->serialNumber(),
            'fw_version' => $this->zk->fmVersion(),
            'platform' => $this->zk->platform(),
            'device_name' => $this->zk->deviceName(),
            'user_count' => count($this->zk->getUser()),
            'attendance_count' => count($this->zk->getAttendance()),
        ];
    }

    public function setTime($time)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->setTime($time->format('Y-m-d H:i:s'));
    }

    public function restart()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->restart();
    }

    public function powerOff()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        return $this->zk->powerOff();
    }

    public function getCapabilities()
    {
        return [
            'fingerprint' => true,
            'card' => true,
            'password' => true,
            'face' => false,
            'tamper' => false,
            'battery' => false,
            'wifi' => false,
        ];
    }
}
