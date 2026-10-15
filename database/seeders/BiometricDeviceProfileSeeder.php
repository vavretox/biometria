<?php

namespace Database\Seeders;

use App\Models\Biometric\BiometricDeviceProfile;
use Illuminate\Database\Seeder;

class BiometricDeviceProfileSeeder extends Seeder
{
    public function run(): void
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
                    'tamper' => false,
                ],
                'description' => 'Dispositivo básico de huella digital',
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
                    'tamper' => false,
                ],
                'description' => 'Dispositivo con lector de tarjetas',
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
                    'tamper' => true,
                ],
                'description' => 'Dispositivo de reconocimiento facial',
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
                    'poe' => true,
                ],
                'description' => 'Dispositivo avanzado de reconocimiento facial y huella',
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
                    'wifi' => true,
                ],
                'description' => 'Dispositivo Suprema BioEntry',
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
                    'color_display' => true,
                ],
                'description' => 'Dispositivo Suprema BioLite con pantalla a color',
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
                    'tamper' => false,
                ],
                'description' => 'Lector de tarjetas HID',
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
                    'tamper' => false,
                ],
                'description' => 'Dispositivo Anviz con lector de huella y tarjeta',
            ],
        ];

        foreach ($profiles as $profile) {
            BiometricDeviceProfile::updateOrCreate([
                'manufacturer' => $profile['manufacturer'],
                'model' => $profile['model'],
            ], array_merge($profile, [
                'is_active' => true,
                'commands' => [
                    'connect', 'disconnect', 'get_user', 'set_user',
                    'get_attendance', 'clear_attendance', 'get_device_info',
                    'set_time', 'get_time', 'restart', 'power_off',
                ],
            ]));
        }
    }
}
