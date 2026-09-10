<?php

namespace App\Services\Biometric\Protocols;

use App\Models\Biometric\BiometricDevice;

interface ProtocolInterface
{
    public function connect(BiometricDevice $device);

    public function disconnect();

    public function isConnected();

    public function getUsers();

    public function setUser($userData);

    public function getFingerprints($uid);

    public function setFingerprints($uid, array $templates);

    public function getAttendance($startDate = null, $endDate = null);

    public function clearAttendance();

    public function getDeviceInfo();

    public function setTime($time);

    public function restart();

    public function powerOff();

    public function getCapabilities();
}
