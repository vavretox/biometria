<?php

namespace App\Services\Biometric\Adapters;

use App\Services\Biometric\Protocols\AnvizProtocol;
use App\Services\Biometric\Protocols\FingerTecProtocol;
use App\Services\Biometric\Protocols\HIDProtocol;
use App\Services\Biometric\Protocols\HttpApiProtocol;
use App\Services\Biometric\Protocols\SupremaProtocol;
use App\Services\Biometric\Protocols\ZKProtocol;

class DeviceAdapter
{
    public static function getAdapter($manufacturer, $model)
    {
        $adapters = [
            'ZKSoftware' => [
                'default' => ZKProtocol::class,
                'K20' => ZKProtocol::class,
                'K40' => ZKProtocol::class,
                'F18' => ZKProtocol::class,
                'iClock' => ZKProtocol::class,
                'FaceID' => HttpApiProtocol::class,
                'SpeedFace' => HttpApiProtocol::class,
            ],
            'Suprema' => [
                'default' => SupremaProtocol::class,
                'BioEntry' => SupremaProtocol::class,
                'BioLite' => HttpApiProtocol::class,
            ],
            'HID' => [
                'default' => HIDProtocol::class,
            ],
            'Anviz' => [
                'default' => AnvizProtocol::class,
            ],
            'FingerTec' => [
                'default' => FingerTecProtocol::class,
            ],
            'Generic' => [
                'default' => ZKProtocol::class,
            ],
        ];

        // Buscar adaptador específico, si no existe usar el default de la marca
        if (isset($adapters[$manufacturer][$model])) {
            return $adapters[$manufacturer][$model];
        }

        if (isset($adapters[$manufacturer]['default'])) {
            return $adapters[$manufacturer]['default'];
        }

        // Si no hay adaptador para la marca, usar ZK como fallback
        return ZKProtocol::class;
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
                'voice' => false,
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
                'voice' => false,
            ],
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
            'voice' => false,
        ];
    }
}
