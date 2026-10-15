#!/bin/bash
# install_biometric_module.sh
# Script completo para configuración universal de dispositivos biométricos

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_message() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

check_laravel_project() {
    print_message "Verificando proyecto Laravel..."
    if [ ! -f "artisan" ]; then
        print_error "No se encontró artisan. ¿Estás en la raíz de un proyecto Laravel?"
        exit 1
    fi
    print_message "Proyecto Laravel encontrado."
}

install_biometric_dependencies() {
    print_header "Instalando dependencias para dispositivos biométricos..."
    
    # Librería principal para ZKSoftware
    print_message "Instalando ZKLibrary..."
    composer require zklibrary/zkphp
    
    # Librerías para comunicación serial (para marcas específicas)
    print_message "Instalando librerías de comunicación..."
    composer require php-serial/php-serial
    composer require phpseclib/phpseclib
    
    # Para comunicación con dispositivos vía HTTP/WebSocket
    print_message "Instalando librerías para comunicación HTTP..."
    composer require guzzlehttp/guzzle
    
    print_message "Dependencias instaladas correctamente."
}

create_biometric_migrations() {
    print_header "Creando migraciones para dispositivos biométricos..."
    
    # Migración principal de dispositivos
    cat > database/migrations/$(date +%Y_%m_%d_000001)_create_biometric_devices_table.php << 'EOF'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name')->unique();
            $table->string('device_ip', 45)->nullable();
            $table->integer('device_port')->default(4370);
            $table->string('device_model')->nullable();
            $table->string('manufacturer')->default('ZKSoftware');
            $table->string('serial_number')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('location')->nullable();
            $table->string('timezone', 50)->default('America/La_Paz');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->integer('sync_interval')->default(60);
            $table->integer('max_connections')->default(5);
            $table->integer('connection_timeout')->default(30);
            $table->string('connection_type')->default('TCP/IP');
            $table->string('protocol')->default('ZKProtocol');
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->json('additional_config')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_sync')->nullable();
            $table->timestamp('last_connection_test')->nullable();
            $table->string('connection_status')->default('disconnected');
            $table->text('connection_error')->nullable();
            $table->timestamps();
            
            $table->index(['device_ip', 'device_port']);
            $table->index(['manufacturer', 'device_model']);
            $table->index('connection_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_devices');
    }
}
EOF

    # Configuración de comunicación
    cat > database/migrations/$(date +%Y_%m_%d_000002)_create_biometric_communication_logs_table.php << 'EOF'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricCommunicationLogsTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_communication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('command');
            $table->string('status');
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();
            
            $table->foreign('device_id')->references('id')->on('biometric_devices')->onDelete('cascade');
            $table->index(['device_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_communication_logs');
    }
}
EOF

    # Marcas y modelos soportados
    cat > database/migrations/$(date +%Y_%m_%d_000003)_create_biometric_device_profiles_table.php << 'EOF'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricDeviceProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_device_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer');
            $table->string('model');
            $table->string('protocol');
            $table->json('default_ports')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('commands')->nullable();
            $table->json('config_parameters')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['manufacturer', 'model']);
            $table->index(['manufacturer', 'protocol']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_device_profiles');
    }
}
EOF

    print_message "Migraciones creadas."
}

create_biometric_models() {
    print_header "Creando modelos para dispositivos biométricos..."
    
    mkdir -p app/Models/Biometric
    
    # Modelo principal de dispositivo
    cat > app/Models/Biometric/BiometricDevice.php << 'EOF'
<?php
namespace App\Models\Biometric;

use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Model;

class BiometricDevice extends Model
{
    protected $table = 'biometric_devices';

    protected $fillable = [
        'device_name', 'device_ip', 'device_port', 'device_model',
        'manufacturer', 'serial_number', 'mac_address', 'location',
        'timezone', 'is_active', 'auto_sync', 'sync_interval',
        'max_connections', 'connection_timeout', 'connection_type',
        'protocol', 'api_key', 'api_secret', 'additional_config',
        'capabilities', 'last_sync', 'last_connection_test',
        'connection_status', 'connection_error'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'additional_config' => 'array',
        'capabilities' => 'array',
        'last_sync' => 'datetime',
        'last_connection_test' => 'datetime'
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function communicationLogs()
    {
        return $this->hasMany(BiometricCommunicationLog::class);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'connected' => 'success',
            'disconnected' => 'danger',
            'connecting' => 'warning',
            'error' => 'danger'
        ];
        return $colors[$this->connection_status] ?? 'secondary';
    }

    public function getStatusTextAttribute()
    {
        $statuses = [
            'connected' => 'Conectado',
            'disconnected' => 'Desconectado',
            'connecting' => 'Conectando...',
            'error' => 'Error'
        ];
        return $statuses[$this->connection_status] ?? 'Desconocido';
    }

    public function getProtocolClassAttribute()
    {
        $protocols = [
            'ZKProtocol' => \App\Services\Biometric\Protocols\ZKProtocol::class,
            'HTTPAPI' => \App\Services\Biometric\Protocols\HttpApiProtocol::class,
            'WebSocket' => \App\Services\Biometric\Protocols\WebSocketProtocol::class,
            'Serial' => \App\Services\Biometric\Protocols\SerialProtocol::class,
        ];
        return $protocols[$this->protocol] ?? null;
    }

    public function scopeConnected($query)
    {
        return $query->where('connection_status', 'connected');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isConnected()
    {
        return $this->connection_status === 'connected';
    }

    public function testConnection()
    {
        $this->last_connection_test = now();
        $this->save();
        
        return app(BiometricDeviceService::class)->testConnection($this);
    }
}
EOF

    # Modelo de perfil de dispositivo
    cat > app/Models/Biometric/BiometricDeviceProfile.php << 'EOF'
<?php
namespace App\Models\Biometric;

use Illuminate\Database\Eloquent\Model;

class BiometricDeviceProfile extends Model
{
    protected $table = 'biometric_device_profiles';

    protected $fillable = [
        'manufacturer', 'model', 'protocol', 'default_ports',
        'capabilities', 'commands', 'config_parameters',
        'description', 'is_active'
    ];

    protected $casts = [
        'default_ports' => 'array',
        'capabilities' => 'array',
        'commands' => 'array',
        'config_parameters' => 'array',
        'is_active' => 'boolean'
    ];

    public function devices()
    {
        return $this->hasMany(BiometricDevice::class, 'device_model', 'model')
                    ->where('manufacturer', $this->manufacturer);
    }

    public static function getDefaultPorts($manufacturer, $model)
    {
        $profile = self::where('manufacturer', $manufacturer)
            ->where('model', $model)
            ->first();
            
        return $profile ? $profile->default_ports : [4370];
    }

    public static function getProtocolsByManufacturer($manufacturer)
    {
        return self::where('manufacturer', $manufacturer)
            ->where('is_active', true)
            ->pluck('protocol')
            ->unique();
    }

    public function getSupportedCommandsAttribute()
    {
        return $this->commands ?? [
            'connect', 'disconnect', 'get_user', 'set_user',
            'get_attendance', 'clear_attendance', 'get_device_info',
            'set_time', 'get_time', 'restart', 'power_off'
        ];
    }
}
EOF

    # Modelo de logs de comunicación
    cat > app/Models/Biometric/BiometricCommunicationLog.php << 'EOF'
<?php
namespace App\Models\Biometric;

use Illuminate\Database\Eloquent\Model;

class BiometricCommunicationLog extends Model
{
    protected $table = 'biometric_communication_logs';

    protected $fillable = [
        'device_id', 'command', 'status', 'request_data',
        'response_data', 'error_message', 'response_time_ms',
        'additional_info'
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'additional_info' => 'array'
    ];

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
EOF

    print_message "Modelos creados."
}

create_biometric_services() {
    print_header "Creando servicios para dispositivos biométricos..."
    
    mkdir -p app/Services/Biometric
    mkdir -p app/Services/Biometric/Protocols
    mkdir -p app/Services/Biometric/Adapters
    
    # Servicio principal
    cat > app/Services/Biometric/BiometricDeviceService.php << 'EOF'
<?php
namespace App\Services\Biometric;

use App\Models\Biometric\BiometricDevice;
use App\Models\Biometric\BiometricCommunicationLog;
use App\Services\Biometric\Protocols\ProtocolInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BiometricDeviceService
{
    protected $device;
    protected $protocol;
    protected $isConnected = false;

    public function connect($deviceId)
    {
        $device = BiometricDevice::find($deviceId);
        if (!$device) {
            throw new \Exception("Dispositivo no encontrado");
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
        
        if (!$protocolClass || !class_exists($protocolClass)) {
            // Protocolo por defecto
            $protocolClass = Protocols\ZKProtocol::class;
        }
        
        return new $protocolClass();
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

    public function getAttendance($deviceId, $startDate = null, $endDate = null)
    {
        $this->ensureConnection($deviceId);
        
        try {
            $attendance = $this->protocol->getAttendance($startDate, $endDate);
            $this->logCommunication('get_attendance', 'success', [
                'start' => $startDate,
                'end' => $endDate,
                'count' => count($attendance)
            ]);
            return $attendance;
        } catch (\Exception $e) {
            $this->logCommunication('get_attendance', 'failed', [
                'start' => $startDate,
                'end' => $endDate
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
        if (!$this->isConnected || !$this->device || $this->device->id != $deviceId) {
            $this->connect($deviceId);
        }
    }

    protected function logCommunication($command, $status, $requestData = [], $errorMessage = null, $responseCount = null)
    {
        $log = new BiometricCommunicationLog();
        $log->device_id = $this->device->id;
        $log->command = $command;
        $log->status = $status;
        $log->request_data = $requestData;
        $log->error_message = $errorMessage;
        $log->response_time_ms = null; // Se puede calcular
        $log->additional_info = ['response_count' => $responseCount];
        $log->save();
        
        if ($status === 'failed') {
            Log::error("Biometric communication error", [
                'device' => $this->device->device_name,
                'command' => $command,
                'error' => $errorMessage
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
EOF

    # Interfaz de protocolos
    cat > app/Services/Biometric/Protocols/ProtocolInterface.php << 'EOF'
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
    public function getAttendance($startDate = null, $endDate = null);
    public function clearAttendance();
    public function getDeviceInfo();
    public function setTime($time);
    public function restart();
    public function powerOff();
    public function getCapabilities();
}
EOF

    # Protocolo ZK (para ZKSoftware y compatibles)
    cat > app/Services/Biometric/Protocols/ZKProtocol.php << 'EOF'
<?php
namespace App\Services\Biometric\Protocols;

use App\Models\Biometric\BiometricDevice;
use ZKLib\ZKLibrary;
use Carbon\Carbon;

class ZKProtocol implements ProtocolInterface
{
    protected $zk;
    protected $device;
    protected $connected = false;

    public function connect(BiometricDevice $device)
    {
        $this->device = $device;
        
        try {
            $this->zk = new ZKLibrary(
                $device->device_ip,
                $device->device_port,
                $device->connection_timeout ?? 30
            );

            if ($this->zk->connect()) {
                $this->zk->disableDevice();
                $this->connected = true;
                return true;
            }
            
            throw new \Exception("No se pudo conectar al dispositivo");
        } catch (\Exception $e) {
            throw new \Exception("Error de conexión: " . $e->getMessage());
        }
    }

    public function disconnect()
    {
        if ($this->zk && $this->connected) {
            $this->zk->enableDevice();
            $this->zk->disconnect();
        }
        $this->connected = false;
        return true;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function getUsers()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        return $this->zk->getUser();
    }

    public function setUser($userData)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
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

    public function getAttendance($startDate = null, $endDate = null)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        $attendance = $this->zk->getAttendance();
        
        // Filtrar por fecha si se especifica
        if ($startDate || $endDate) {
            $startTimestamp = $startDate ? Carbon::parse($startDate)->timestamp : 0;
            $endTimestamp = $endDate ? Carbon::parse($endDate)->timestamp : PHP_INT_MAX;
            
            $attendance = array_filter($attendance, function($log) use ($startTimestamp, $endTimestamp) {
                return $log['timestamp'] >= $startTimestamp && $log['timestamp'] <= $endTimestamp;
            });
        }
        
        return $attendance;
    }

    public function clearAttendance()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        return $this->zk->clearAttendance();
    }

    public function getDeviceInfo()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        return [
            'serial_number' => $this->zk->getSerialNumber(),
            'fw_version' => $this->zk->getFirmwareVersion(),
            'platform' => $this->zk->getPlatform(),
            'device_name' => $this->zk->getDeviceName(),
            'user_count' => $this->zk->getUserCount(),
            'attendance_count' => $this->zk->getAttendanceCount()
        ];
    }

    public function setTime($time)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        return $this->zk->setTime($time->timestamp);
    }

    public function restart()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        return $this->zk->restart();
    }

    public function powerOff()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
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
            'wifi' => false
        ];
    }
}
EOF

    # Protocolo HTTP API (para dispositivos modernos)
    cat > app/Services/Biometric/Protocols/HttpApiProtocol.php << 'EOF'
<?php
namespace App\Services\Biometric\Protocols;

use App\Models\Biometric\BiometricDevice;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Carbon\Carbon;

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
                'Accept' => 'application/json'
            ]
        ]);

        try {
            $response = $this->client->get('/api/status');
            if ($response->getStatusCode() === 200) {
                $this->connected = true;
                return true;
            }
            throw new \Exception("Dispositivo no responde");
        } catch (\Exception $e) {
            throw new \Exception("Error de conexión HTTP: " . $e->getMessage());
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
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->get('/api/users');
            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener usuarios: " . $e->getMessage());
        }
    }

    public function setUser($userData)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->post('/api/users', [
                'json' => $userData
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error al crear usuario: " . $e->getMessage());
        }
    }

    public function getAttendance($startDate = null, $endDate = null)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
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
                'query' => $params
            ]);
            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener asistencias: " . $e->getMessage());
        }
    }

    public function clearAttendance()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->delete('/api/attendance');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error al limpiar asistencias: " . $e->getMessage());
        }
    }

    public function getDeviceInfo()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->get('/api/info');
            return json_decode($response->getBody(), true)['data'] ?? [];
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener información: " . $e->getMessage());
        }
    }

    public function setTime($time)
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->post('/api/time', [
                'json' => ['timestamp' => $time->timestamp]
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error al establecer hora: " . $e->getMessage());
        }
    }

    public function restart()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->post('/api/restart');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error al reiniciar: " . $e->getMessage());
        }
    }

    public function powerOff()
    {
        if (!$this->connected) {
            throw new \Exception("Dispositivo no conectado");
        }
        
        try {
            $response = $this->client->post('/api/poweroff');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error al apagar: " . $e->getMessage());
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
                'tamper' => false
            ];
        }
    }
}
EOF

    # Adapter para diferentes marcas
    cat > app/Services/Biometric/Adapters/DeviceAdapter.php << 'EOF'
<?php
namespace App\Services\Biometric\Adapters;

use App\Models\Biometric\BiometricDevice;

class DeviceAdapter
{
    public static function getAdapter($manufacturer, $model)
    {
        $adapters = [
            'ZKSoftware' => [
                'default' => \App\Services\Biometric\Protocols\ZKProtocol::class,
                'K20' => \App\Services\Biometric\Protocols\ZKProtocol::class,
                'K40' => \App\Services\Biometric\Protocols\ZKProtocol::class,
                'F18' => \App\Services\Biometric\Protocols\ZKProtocol::class,
                'iClock' => \App\Services\Biometric\Protocols\ZKProtocol::class,
                'FaceID' => \App\Services\Biometric\Protocols\HttpApiProtocol::class,
                'SpeedFace' => \App\Services\Biometric\Protocols\HttpApiProtocol::class,
            ],
            'Suprema' => [
                'default' => \App\Services\Biometric\Protocols\SupremaProtocol::class,
                'BioEntry' => \App\Services\Biometric\Protocols\SupremaProtocol::class,
                'BioLite' => \App\Services\Biometric\Protocols\HttpApiProtocol::class,
            ],
            'HID' => [
                'default' => \App\Services\Biometric\Protocols\HIDProtocol::class,
            ],
            'Anviz' => [
                'default' => \App\Services\Biometric\Protocols\AnvizProtocol::class,
            ],
            'FingerTec' => [
                'default' => \App\Services\Biometric\Protocols\FingerTecProtocol::class,
            ],
            'Generic' => [
                'default' => \App\Services\Biometric\Protocols\ZKProtocol::class,
            ]
        ];
        
        // Buscar adaptador específico, si no existe usar el default de la marca
        if (isset($adapters[$manufacturer][$model])) {
            return $adapters[$manufacturer][$model];
        }
        
        if (isset($adapters[$manufacturer]['default'])) {
            return $adapters[$manufacturer]['default'];
        }
        
        // Si no hay adaptador para la marca, usar ZK como fallback
        return \App\Services\Biometric\Protocols\ZKProtocol::class;
    }

    public static function getDefaultPort($manufacturer, $model)
    {
        $ports = [
            'ZKSoftware' => [4370, 4360, 4380],
            'Suprema' => [4000, 4001, 4002],
            'HID' => [4370, 4371],
            'Anviz' => [4370, 4375],
            'FingerTec' => [4370, 4373],
        ];
        
        return $ports[$manufacturer][0] ?? 4370;
    }

    public static function getCapabilities($manufacturer, $model)
    {
        $capabilities = [
            'ZKSoftware' => [
                'fingerprint' => true,
                'card' => true,
                'password' => true,
                'face' => in_array($model, ['FaceID', 'SpeedFace']),
                'tamper' => false,
                'battery' => false,
                'wifi' => false,
                'poe' => false,
                'color_display' => in_array($model, ['K40', 'iClock']),
                'voice' => false
            ],
            'Suprema' => [
                'fingerprint' => true,
                'card' => true,
                'password' => true,
                'face' => false,
                'tamper' => true,
                'battery' => true,
                'wifi' => true,
                'poe' => true,
                'color_display' => true,
                'voice' => false
            ]
        ];
        
        return $capabilities[$manufacturer] ?? [
            'fingerprint' => true,
            'card' => false,
            'password' => false,
            'face' => false,
            'tamper' => false,
            'battery' => false,
            'wifi' => false,
            'poe' => false,
            'color_display' => false,
            'voice' => false
        ];
    }
}
EOF

    print_message "Servicios creados."
}

create_biometric_controller() {
    print_header "Creando controlador para dispositivos biométricos..."
    
    mkdir -p app/Http/Controllers/Biometric
    
    cat > app/Http/Controllers/Biometric/BiometricDeviceController.php << 'EOF'
<?php
namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Models\Biometric\BiometricDevice;
use App\Models\Biometric\BiometricDeviceProfile;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\Adapters\DeviceAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BiometricDeviceController extends Controller
{
    protected $deviceService;

    public function __construct(BiometricDeviceService $deviceService)
    {
        $this->deviceService = $deviceService;
        $this->middleware('auth');
        $this->middleware('permission:manage_biometric_devices');
    }

    public function index()
    {
        $devices = BiometricDevice::with('communicationLogs')
            ->orderBy('device_name')
            ->paginate(20);
            
        $profiles = BiometricDeviceProfile::where('is_active', true)->get();
        
        return view('biometric.devices.index', compact('devices', 'profiles'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:100|unique:biometric_devices,device_name',
            'device_ip' => 'required|ip',
            'device_port' => 'required|integer|min:1|max:65535',
            'device_model' => 'nullable|string|max:50',
            'manufacturer' => 'required|string|max:50',
            'serial_number' => 'nullable|string|max:50',
            'mac_address' => 'nullable|string|max:17',
            'location' => 'nullable|string|max:100',
            'connection_type' => 'required|in:TCP/IP,Serial,USB,WebSocket',
            'protocol' => 'required|string|max:50',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'connection_timeout' => 'required|integer|min:5|max:300',
            'auto_sync' => 'boolean',
            'sync_interval' => 'required|integer|min:5|max:1440',
            'additional_config' => 'nullable|json',
            'timezone' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Obtener capacidades automáticamente según marca y modelo
            $capabilities = DeviceAdapter::getCapabilities(
                $request->manufacturer,
                $request->device_model ?? 'default'
            );

            // Si no se especifica puerto, usar el por defecto para la marca
            if (!$request->device_port) {
                $request->merge([
                    'device_port' => DeviceAdapter::getDefaultPort(
                        $request->manufacturer,
                        $request->device_model ?? 'default'
                    )
                ]);
            }

            $device = BiometricDevice::create([
                'device_name' => $request->device_name,
                'device_ip' => $request->device_ip,
                'device_port' => $request->device_port,
                'device_model' => $request->device_model,
                'manufacturer' => $request->manufacturer,
                'serial_number' => $request->serial_number,
                'mac_address' => $request->mac_address,
                'location' => $request->location,
                'timezone' => $request->timezone,
                'is_active' => true,
                'auto_sync' => $request->auto_sync ?? true,
                'sync_interval' => $request->sync_interval ?? 60,
                'connection_type' => $request->connection_type ?? 'TCP/IP',
                'protocol' => $request->protocol,
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'connection_timeout' => $request->connection_timeout ?? 30,
                'additional_config' => $request->additional_config ? json_decode($request->additional_config, true) : null,
                'capabilities' => $capabilities,
                'connection_status' => 'disconnected',
                'max_connections' => 5
            ]);

            // Probar conexión automáticamente
            try {
                $device->testConnection();
            } catch (\Exception $e) {
                Log::warning("No se pudo conectar automáticamente: " . $e->getMessage());
            }

            return redirect()->route('biometric.devices.index')
                ->with('success', "Dispositivo '{$device->device_name}' registrado exitosamente");
                
        } catch (\Exception $e) {
            Log::error("Error al registrar dispositivo: " . $e->getMessage());
            return redirect()->back()
                ->with('error', "Error al registrar dispositivo: " . $e->getMessage())
                ->withInput();
        }
    }

    public function testConnection($deviceId)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $result = $this->deviceService->testConnection($device);
            
            return response()->json([
                'success' => $result,
                'message' => $result ? 'Conexión exitosa' : 'No se pudo conectar',
                'device' => $device->device_name,
                'status' => $device->connection_status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al probar conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncEmployees($deviceId)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            
            if (!$this->deviceService->connect($deviceId)) {
                throw new \Exception("No se pudo conectar al dispositivo");
            }
            
            $users = $this->deviceService->getUsers($deviceId);
            $this->deviceService->disconnect();
            
            $synced = 0;
            foreach ($users as $user) {
                $employee = \App\Models\Employee::updateOrCreate(
                    ['biometric_id' => $user['uid']],
                    [
                        'employee_code' => $user['userid'] ?? $user['uid'],
                        'first_name' => $user['name'] ?? 'Usuario',
                        'last_name' => '',
                        'is_active' => true
                    ]
                );
                $synced++;
            }

            $device->last_sync = now();
            $device->save();

            return response()->json([
                'success' => true,
                'message' => "Sincronizados {$synced} empleados",
                'data' => ['synced' => $synced]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncAttendance($deviceId, Request $request)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            if (!$this->deviceService->connect($deviceId)) {
                throw new \Exception("No se pudo conectar al dispositivo");
            }
            
            $attendance = $this->deviceService->getAttendance($deviceId, $startDate, $endDate);
            $this->deviceService->disconnect();
            
            $synced = 0;
            $errors = 0;
            
            foreach ($attendance as $log) {
                $employee = \App\Models\Employee::where('biometric_id', $log['uid'])->first();
                
                if (!$employee) {
                    $errors++;
                    continue;
                }

                $checkTime = \Carbon\Carbon::createFromTimestamp($log['timestamp']);
                
                $existing = \App\Models\Attendance::where('employee_id', $employee->id)
                    ->where('check_time', $checkTime)
                    ->where('device_id', $deviceId)
                    ->first();

                if (!$existing) {
                    \App\Models\Attendance::create([
                        'employee_id' => $employee->id,
                        'device_id' => $deviceId,
                        'check_time' => $checkTime,
                        'check_type' => $log['type'] ?? 'IN',
                        'status' => 'NORMAL',
                        'sync_status' => 'SYNCED',
                        'raw_data' => json_encode($log)
                    ]);
                    $synced++;
                }
            }

            $device->last_sync = now();
            $device->save();

            return response()->json([
                'success' => true,
                'message' => "Sincronizados {$synced} registros",
                'data' => [
                    'synced' => $synced,
                    'errors' => $errors,
                    'total' => count($attendance)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar asistencias: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDeviceInfo($deviceId)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            
            if (!$this->deviceService->connect($deviceId)) {
                throw new \Exception("No se pudo conectar al dispositivo");
            }
            
            $info = $this->deviceService->getDeviceInfo($deviceId);
            $this->deviceService->disconnect();
            
            return response()->json([
                'success' => true,
                'data' => $info,
                'device' => $device->device_name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableProfiles()
    {
        $profiles = BiometricDeviceProfile::where('is_active', true)->get();
        
        $manufacturers = $profiles->groupBy('manufacturer')->map(function($items) {
            return $items->map(function($item) {
                return [
                    'model' => $item->model,
                    'protocol' => $item->protocol,
                    'capabilities' => $item->capabilities,
                    'description' => $item->description
                ];
            });
        });
        
        return response()->json([
            'success' => true,
            'data' => $manufacturers
        ]);
    }

    public function getDeviceLogs($deviceId, Request $request)
    {
        $logs = \App\Models\Biometric\BiometricCommunicationLog::where('device_id', $deviceId)
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->command, function($query, $command) {
                return $query->where('command', $command);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);
            
        return response()->json($logs);
    }

    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'devices' => 'required|array',
            'devices.*.device_name' => 'required|string|max:100',
            'devices.*.device_ip' => 'required|ip',
            'devices.*.manufacturer' => 'required|string|max:50',
            'devices.*.device_model' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $created = 0;
        $errors = [];

        foreach ($request->devices as $deviceData) {
            try {
                // Si no hay puerto, usar el por defecto
                if (!isset($deviceData['device_port'])) {
                    $deviceData['device_port'] = DeviceAdapter::getDefaultPort(
                        $deviceData['manufacturer'],
                        $deviceData['device_model'] ?? 'default'
                    );
                }

                // Obtener capacidades
                $capabilities = DeviceAdapter::getCapabilities(
                    $deviceData['manufacturer'],
                    $deviceData['device_model'] ?? 'default'
                );

                BiometricDevice::create([
                    'device_name' => $deviceData['device_name'],
                    'device_ip' => $deviceData['device_ip'],
                    'device_port' => $deviceData['device_port'],
                    'device_model' => $deviceData['device_model'] ?? null,
                    'manufacturer' => $deviceData['manufacturer'],
                    'is_active' => true,
                    'connection_status' => 'disconnected',
                    'capabilities' => $capabilities,
                    'protocol' => $deviceData['protocol'] ?? 'ZKProtocol',
                    'connection_timeout' => 30,
                    'sync_interval' => 60,
                    'auto_sync' => true
                ]);
                
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error en {$deviceData['device_name']}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Creados {$created} dispositivos",
            'errors' => $errors
        ]);
    }
}
EOF

    print_message "Controlador creado."
}

create_biometric_seeder() {
    print_header "Creando seeder para perfiles de dispositivos..."
    
    cat > database/seeders/BiometricDeviceProfileSeeder.php << 'EOF'
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Biometric\BiometricDeviceProfile;

class BiometricDeviceProfileSeeder extends Seeder
{
    public function run()
    {
        $profiles = [
            // ZKSoftware
            [
                'manufacturer' => 'ZKSoftware',
                'model' => 'K20',
                'protocol' => 'ZKProtocol',
                'default_ports' => [4370, 4360],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => false,
                    'password' => true,
                    'face' => false,
                    'tamper' => false
                ],
                'description' => 'Dispositivo básico de huella digital'
            ],
            [
                'manufacturer' => 'ZKSoftware',
                'model' => 'K40',
                'protocol' => 'ZKProtocol',
                'default_ports' => [4370, 4380],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => true,
                    'password' => true,
                    'face' => false,
                    'tamper' => false
                ],
                'description' => 'Dispositivo con lector de tarjetas'
            ],
            [
                'manufacturer' => 'ZKSoftware',
                'model' => 'FaceID',
                'protocol' => 'HTTPAPI',
                'default_ports' => [80, 8080],
                'capabilities' => [
                    'fingerprint' => false,
                    'card' => true,
                    'password' => false,
                    'face' => true,
                    'tamper' => true
                ],
                'description' => 'Dispositivo de reconocimiento facial'
            ],
            [
                'manufacturer' => 'ZKSoftware',
                'model' => 'SpeedFace',
                'protocol' => 'HTTPAPI',
                'default_ports' => [80, 443, 8080],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => true,
                    'password' => true,
                    'face' => true,
                    'tamper' => true,
                    'wifi' => true,
                    'poe' => true
                ],
                'description' => 'Dispositivo avanzado de reconocimiento facial y huella'
            ],
            // Suprema
            [
                'manufacturer' => 'Suprema',
                'model' => 'BioEntry',
                'protocol' => 'SupremaProtocol',
                'default_ports' => [4000, 4001],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => true,
                    'password' => true,
                    'face' => false,
                    'tamper' => true,
                    'battery' => true,
                    'wifi' => true
                ],
                'description' => 'Dispositivo Suprema BioEntry'
            ],
            [
                'manufacturer' => 'Suprema',
                'model' => 'BioLite',
                'protocol' => 'HTTPAPI',
                'default_ports' => [80, 443],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => true,
                    'password' => true,
                    'face' => false,
                    'tamper' => true,
                    'wifi' => true,
                    'poe' => true,
                    'color_display' => true
                ],
                'description' => 'Dispositivo Suprema BioLite con pantalla a color'
            ],
            // HID
            [
                'manufacturer' => 'HID',
                'model' => 'HID Reader',
                'protocol' => 'HIDProtocol',
                'default_ports' => [4370, 4371],
                'capabilities' => [
                    'fingerprint' => false,
                    'card' => true,
                    'password' => false,
                    'face' => false,
                    'tamper' => false
                ],
                'description' => 'Lector de tarjetas HID'
            ],
            // Anviz
            [
                'manufacturer' => 'Anviz',
                'model' => 'Anviz EP',
                'protocol' => 'AnvizProtocol',
                'default_ports' => [4370, 4375],
                'capabilities' => [
                    'fingerprint' => true,
                    'card' => true,
                    'password' => true,
                    'face' => false,
                    'tamper' => false
                ],
                'description' => 'Dispositivo Anviz con lector de huella y tarjeta'
            ]
        ];

        foreach ($profiles as $profile) {
            BiometricDeviceProfile::create(array_merge($profile, [
                'is_active' => true,
                'commands' => [
                    'connect', 'disconnect', 'get_user', 'set_user',
                    'get_attendance', 'clear_attendance', 'get_device_info',
                    'set_time', 'get_time', 'restart', 'power_off'
                ]
            ]));
        }
    }
}
EOF

    print_message "Seeder creado."
}

create_biometric_views() {
    print_header "Creando vistas para dispositivos biométricos..."
    
    mkdir -p resources/views/biometric/devices
    
    cat > resources/views/biometric/devices/index.blade.php << 'EOF'
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Dispositivos Biométricos</h2>
        <div>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addDeviceModal">
                <i class="fas fa-plus"></i> Agregar Dispositivo
            </button>
            <button class="btn btn-success" data-toggle="modal" data-target="#bulkImportModal">
                <i class="fas fa-upload"></i> Importar Múltiples
            </button>
        </div>
    </div>

    <div class="row">
        @foreach($devices as $device)
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">{{ $device->device_name }}</h5>
                            <span class="badge badge-{{ $device->status_color }}">
                                {{ $device->status_text }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p><strong>IP:</strong> {{ $device->device_ip ?? 'N/A' }}</p>
                        <p><strong>Puerto:</strong> {{ $device->device_port }}</p>
                        <p><strong>Marca:</strong> {{ $device->manufacturer }}</p>
                        <p><strong>Modelo:</strong> {{ $device->device_model ?? 'No especificado' }}</p>
                        <p><strong>Ubicación:</strong> {{ $device->location ?? 'No definida' }}</p>
                        <p><strong>Última sincronización:</strong> 
                            {{ $device->last_sync ? $device->last_sync->format('d/m/Y H:i') : 'Nunca' }}
                        </p>
                        <div class="mt-2">
                            <strong>Capacidades:</strong>
                            @foreach($device->capabilities ?? [] as $capability => $value)
                                @if($value)
                                    <span class="badge badge-info">{{ ucfirst($capability) }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100">
                            <button class="btn btn-sm btn-secondary" onclick="testConnection({{ $device->id }})">
                                <i class="fas fa-plug"></i> Probar
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="syncEmployees({{ $device->id }})">
                                <i class="fas fa-users"></i> Empleados
                            </button>
                            <button class="btn btn-sm btn-success" onclick="syncAttendance({{ $device->id }})">
                                <i class="fas fa-clock"></i> Asistencias
                            </button>
                            <button class="btn btn-sm btn-info" onclick="getDeviceInfo({{ $device->id }})">
                                <i class="fas fa-info"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{ $devices->links() }}
</div>

<!-- Modal para agregar dispositivo -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Dispositivo Biométrico</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('biometric.devices.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nombre del Dispositivo *</label>
                                <input type="text" class="form-control" name="device_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dirección IP *</label>
                                <input type="text" class="form-control" name="device_ip" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Puerto *</label>
                                <input type="number" class="form-control" name="device_port" value="4370">
                                <small class="form-text text-muted">
                                    Puerto por defecto para ZK: 4370
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Marca *</label>
                                <select class="form-control" name="manufacturer" required>
                                    <option value="ZKSoftware">ZKSoftware</option>
                                    <option value="Suprema">Suprema</option>
                                    <option value="HID">HID</option>
                                    <option value="Anviz">Anviz</option>
                                    <option value="FingerTec">FingerTec</option>
                                    <option value="Generic">Genérico</option>
                                    <option value="Other">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Modelo</label>
                                <input type="text" class="form-control" name="device_model">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Protocolo</label>
                                <select class="form-control" name="protocol">
                                    <option value="ZKProtocol">ZK Protocol</option>
                                    <option value="HTTPAPI">HTTP API</option>
                                    <option value="WebSocket">WebSocket</option>
                                    <option value="Serial">Serial</option>
                                    <option value="SupremaProtocol">Suprema Protocol</option>
                                    <option value="HIDProtocol">HID Protocol</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ubicación</label>
                                <input type="text" class="form-control" name="location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Zona Horaria</label>
                                <select class="form-control" name="timezone">
                                    <option value="America/La_Paz">Bolivia (UTC-4)</option>
                                    <option value="America/Lima">Perú (UTC-5)</option>
                                    <option value="America/Santiago">Chile (UTC-3)</option>
                                    <option value="America/Bogota">Colombia (UTC-5)</option>
                                    <option value="America/Mexico_City">México (UTC-6)</option>
                                    <option value="America/Argentina/Buenos_Aires">Argentina (UTC-3)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Timeout de Conexión (segundos)</label>
                                <input type="number" class="form-control" name="connection_timeout" value="30">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Intervalo de Sincronización (minutos)</label>
                                <input type="number" class="form-control" name="sync_interval" value="60">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>API Key (si aplica)</label>
                                <input type="text" class="form-control" name="api_key">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>API Secret (si aplica)</label>
                                <input type="text" class="form-control" name="api_secret">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="auto_sync" checked>
                                <label class="form-check-label">Sincronización automática</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar Dispositivo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Importación Masiva -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importación Masiva de Dispositivos</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Pega los datos de los dispositivos en formato JSON:</p>
                <textarea class="form-control" id="bulkData" rows="10">[
    {
        "device_name": "Biometrico Oficina 1",
        "device_ip": "192.168.1.100",
        "manufacturer": "ZKSoftware",
        "device_model": "K20"
    },
    {
        "device_name": "Biometrico Oficina 2",
        "device_ip": "192.168.1.101",
        "manufacturer": "Suprema",
        "device_model": "BioEntry"
    }
]</textarea>
                <button class="btn btn-success mt-2" onclick="bulkImport()">
                    <i class="fas fa-upload"></i> Importar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function testConnection(deviceId) {
    $.ajax({
        url: `/biometric/devices/${deviceId}/test`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            alert(response.success ? '✅ Conexión exitosa' : '❌ ' + response.message);
            if (response.success) location.reload();
        },
        error: function(xhr) {
            alert('Error: ' + xhr.responseJSON.message);
        }
    });
}

function syncEmployees(deviceId) {
    if (!confirm('¿Sincronizar empleados desde el dispositivo?')) return;
    
    $.ajax({
        url: `/biometric/devices/${deviceId}/sync-employees`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            alert(response.message);
        },
        error: function(xhr) {
            alert('Error: ' + xhr.responseJSON.message);
        }
    });
}

function syncAttendance(deviceId) {
    if (!confirm('¿Sincronizar asistencias desde el dispositivo?')) return;
    
    $.ajax({
        url: `/biometric/devices/${deviceId}/sync-attendance`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            alert(response.message);
        },
        error: function(xhr) {
            alert('Error: ' + xhr.responseJSON.message);
        }
    });
}

function getDeviceInfo(deviceId) {
    $.ajax({
        url: `/biometric/devices/${deviceId}/info`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let info = response.data;
                let message = '📊 Información del dispositivo:\n\n';
                for (let key in info) {
                    message += `${key}: ${info[key]}\n`;
                }
                alert(message);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            alert('Error: ' + xhr.responseJSON.message);
        }
    });
}

function bulkImport() {
    try {
        let data = JSON.parse($('#bulkData').val());
        
        $.ajax({
            url: '/biometric/devices/bulk-import',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify({ devices: data }),
            contentType: 'application/json',
            success: function(response) {
                alert(response.message);
                if (response.errors.length > 0) {
                    alert('Errores:\n' + response.errors.join('\n'));
                }
                location.reload();
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseJSON.message);
            }
        });
    } catch (e) {
        alert('Error en el formato JSON: ' + e.message);
    }
}
</script>
@endsection
EOF

    print_message "Vistas creadas."
}

create_biometric_routes() {
    print_header "Configurando rutas para dispositivos biométricos..."
    
    cat >> routes/web.php << 'EOF'

// Rutas para dispositivos biométricos
Route::prefix('biometric')->middleware(['auth'])->group(function () {
    Route::get('/devices', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'index'])
        ->name('biometric.devices.index');
    
    Route::post('/devices', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'store'])
        ->name('biometric.devices.store');
    
    Route::post('/devices/{deviceId}/test', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'testConnection'])
        ->name('biometric.devices.test');
    
    Route::post('/devices/{deviceId}/sync-employees', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'syncEmployees'])
        ->name('biometric.devices.sync-employees');
    
    Route::post('/devices/{deviceId}/sync-attendance', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'syncAttendance'])
        ->name('biometric.devices.sync-attendance');
    
    Route::get('/devices/{deviceId}/info', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'getDeviceInfo'])
        ->name('biometric.devices.info');
    
    Route::get('/devices/{deviceId}/logs', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'getDeviceLogs'])
        ->name('biometric.devices.logs');
    
    Route::get('/profiles', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'getAvailableProfiles'])
        ->name('biometric.devices.profiles');
    
    Route::post('/devices/bulk-import', [App\Http\Controllers\Biometric\BiometricDeviceController::class, 'bulkImport'])
        ->name('biometric.devices.bulk-import');
});
EOF

    print_message "Rutas configuradas."
}

# Función principal
main_install() {
    print_header "INSTALACIÓN DEL MÓDULO BIOMÉTRICO UNIVERSAL"
    echo ""
    echo "Soporte para múltiples marcas y modelos:"
    echo "  ✓ ZKSoftware (Todos los modelos)"
    echo "  ✓ Suprema (BioEntry, BioLite, etc)"
    echo "  ✓ HID (Lectores de tarjetas)"
    echo "  ✓ Anviz (Dispositivos de huella)"
    echo "  ✓ FingerTec (Dispositivos biométricos)"
    echo "  ✓ Genérico (Compatible con otros)"
    echo "  ✓ Protocolos: TCP/IP, HTTP, WebSocket, Serial"
    echo ""
    
    check_laravel_project
    install_biometric_dependencies
    create_biometric_migrations
    create_biometric_models
    create_biometric_services
    create_biometric_controller
    create_biometric_seeder
    create_biometric_views
    create_biometric_routes
    
    print_header "EJECUTANDO MIGRACIONES Y SEEDERS"
    
    php artisan migrate
    php artisan db:seed --class=BiometricDeviceProfileSeeder
    
    print_header "INSTALACIÓN COMPLETADA"
    echo ""
    echo "El módulo biométrico universal ha sido instalado."
    echo ""
    echo "Características:"
    echo "  ✅ Soporte para múltiples marcas y modelos"
    echo "  ✅ Múltiples protocolos de comunicación"
    echo "  ✅ Puerto personalizable para cada dispositivo"
    echo "  ✅ Configuración flexible por dispositivo"
    echo "  ✅ Logs de comunicación detallados"
    echo "  ✅ Importación masiva de dispositivos"
    echo "  ✅ Auto-detección de capacidades"
    echo "  ✅ Adaptadores específicos por marca"
    echo ""
    echo "Marcas soportadas:"
    echo "  - ZKSoftware: K20, K40, F18, iClock, FaceID, SpeedFace"
    echo "  - Suprema: BioEntry, BioLite, BioEntry W"
    echo "  - HID: HID Reader"
    echo "  - Anviz: Anviz EP, Anviz FP"
    echo "  - FingerTec: AC100, R2, TA100"
    echo ""
    echo "Rutas disponibles:"
    echo "  /biometric/devices  - Gestión de dispositivos"
    echo ""
    echo "Comandos Artisan:"
    echo "  php artisan biometric:sync  - Sincronizar todos los dispositivos"
    echo ""
    echo "¡Sistema listo para cualquier dispositivo biométrico!"
}

main_install