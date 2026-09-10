<?php

namespace App\Models\Biometric;

use App\Models\Attendance;
use App\Models\BiometricTemplate;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\Protocols\HttpApiProtocol;
use App\Services\Biometric\Protocols\SerialProtocol;
use App\Services\Biometric\Protocols\WebSocketProtocol;
use App\Services\Biometric\Protocols\ZKProtocol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'connection_status', 'connection_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'additional_config' => 'array',
        'capabilities' => 'array',
        'last_sync' => 'datetime',
        'last_connection_test' => 'datetime',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'device_id');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(BiometricCommunicationLog::class, 'device_id');
    }

    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'device_id');
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'connected' => 'success',
            'disconnected' => 'danger',
            'connecting' => 'warning',
            'error' => 'danger',
        ];

        return $colors[$this->connection_status] ?? 'secondary';
    }

    public function getStatusTextAttribute()
    {
        $statuses = [
            'connected' => 'Conectado',
            'disconnected' => 'Desconectado',
            'connecting' => 'Conectando...',
            'error' => 'Error',
        ];

        return $statuses[$this->connection_status] ?? 'Desconocido';
    }

    public function getProtocolClassAttribute()
    {
        $protocols = [
            'ZKProtocol' => ZKProtocol::class,
            'HTTPAPI' => HttpApiProtocol::class,
            'WebSocket' => WebSocketProtocol::class,
            'Serial' => SerialProtocol::class,
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
