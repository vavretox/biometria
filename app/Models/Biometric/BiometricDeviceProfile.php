<?php

namespace App\Models\Biometric;

use Illuminate\Database\Eloquent\Model;

class BiometricDeviceProfile extends Model
{
    protected $table = 'biometric_device_profiles';

    protected $fillable = [
        'manufacturer', 'model', 'protocol', 'default_ports',
        'capabilities', 'commands', 'config_parameters',
        'description', 'is_active',
    ];

    protected $casts = [
        'default_ports' => 'array',
        'capabilities' => 'array',
        'commands' => 'array',
        'config_parameters' => 'array',
        'is_active' => 'boolean',
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
            'set_time', 'get_time', 'restart', 'power_off',
        ];
    }
}
