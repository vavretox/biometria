<?php

namespace App\Services\Biometric\Protocols;

use App\Models\Biometric\BiometricDevice;
use Carbon\Carbon;
use GuzzleHttp\Client;

class HttpApiProtocol implements ProtocolInterface
{
    protected $client;

    protected $device;

    protected $connected = false;

    protected $apiKey;

    protected $apiSecret;

    public function connect(BiometricDevice $device)
    {
        $this->device = $device;
        $this->apiKey = $device->api_key;
        $this->apiSecret = $device->api_secret;

        $this->client = new Client([
            'base_uri' => "http://{$device->device_ip}:{$device->device_port}/",
            'timeout' => $device->connection_timeout ?? 30,
            'headers' => [
                'Authorization' => $this->apiKey ? "Bearer {$this->apiKey}" : '',
                'X-API-Key' => $this->apiKey ?? '',
                'Accept' => 'application/json',
            ],
        ]);

        try {
            $response = $this->client->get('/api/status');
            if ($response->getStatusCode() === 200) {
                $this->connected = true;

                return true;
            }
            throw new \Exception('Dispositivo no responde');
        } catch (\Exception $e) {
            throw new \Exception('Error de conexión HTTP: '.$e->getMessage());
        }
    }

    public function disconnect()
    {
        $this->connected = false;
        $this->client = null;

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

        try {
            $response = $this->client->get('/api/users');

            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener usuarios: '.$e->getMessage());
        }
    }

    public function setUser($userData)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->post('/api/users', [
                'json' => $userData,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al crear usuario: '.$e->getMessage());
        }
    }

    public function deleteUser(int $uid): bool
    {
        throw new \RuntimeException('La eliminación de usuarios no está disponible para este protocolo.');
    }

    public function getFingerprints($uid)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->get('/api/users/'.$uid.'/fingerprints');

            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener huellas: '.$e->getMessage());
        }
    }

    public function setFingerprints($uid, array $templates)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->put('/api/users/'.$uid.'/fingerprints', [
                'json' => ['templates' => $templates],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al enviar huellas: '.$e->getMessage());
        }
    }

    public function getAttendance($startDate = null, $endDate = null)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        $params = [];
        if ($startDate) {
            $params['start_date'] = Carbon::parse($startDate)->toDateString();
        }
        if ($endDate) {
            $params['end_date'] = Carbon::parse($endDate)->toDateString();
        }

        try {
            $response = $this->client->get('/api/attendance', [
                'query' => $params,
            ]);

            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener asistencias: '.$e->getMessage());
        }
    }

    public function clearAttendance()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->delete('/api/attendance');

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al limpiar asistencias: '.$e->getMessage());
        }
    }

    public function getDeviceInfo()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->get('/api/info');

            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener información: '.$e->getMessage());
        }
    }

    public function setTime($time)
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->post('/api/time', [
                'json' => ['timestamp' => $time->timestamp],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al establecer hora: '.$e->getMessage());
        }
    }

    public function restart()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->post('/api/restart');

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al reiniciar: '.$e->getMessage());
        }
    }

    public function powerOff()
    {
        if (! $this->connected) {
            throw new \Exception('Dispositivo no conectado');
        }

        try {
            $response = $this->client->post('/api/poweroff');

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Error al apagar: '.$e->getMessage());
        }
    }

    public function getCapabilities()
    {
        try {
            $response = $this->client->get('/api/capabilities');

            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            return [
                'fingerprint' => true,
                'card' => true,
                'password' => true,
                'face' => false,
                'tamper' => false,
            ];
        }
    }
}
