<?php

namespace App\Console\Commands;

use App\Models\Biometric\BiometricDevice;
use App\Services\Biometric\BiometricDeviceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('biometric:check-connections')]
#[Description('Verifica la conexión de todos los biométricos activos y registra el resultado')]
class CheckBiometricConnections extends Command
{
    public function handle(BiometricDeviceService $service): int
    {
        $failed = 0;

        foreach (BiometricDevice::active()->lazyById() as $device) {
            try {
                if ($service->testConnection($device, 'automatic')) {
                    if ($service->timeSynchronized) {
                        $this->info($device->device_name.': conexión disponible y hora sincronizada con Bolivia');
                    } else {
                        $failed++;
                        $this->warn($device->device_name.': conectado, pero falló la sincronización de hora: '.$service->timeSynchronizationError);
                    }
                } else {
                    $failed++;
                    $this->error($device->device_name.': '.$device->connection_error);
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $device->update([
                    'connection_status' => 'error',
                    'connection_error' => $exception->getMessage(),
                    'last_connection_test' => now(),
                ]);
                $device->communicationLogs()->create([
                    'command' => 'connect', 'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'request_data' => ['source' => 'automatic'],
                ]);
                $this->error($device->device_name.': '.$exception->getMessage());
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
