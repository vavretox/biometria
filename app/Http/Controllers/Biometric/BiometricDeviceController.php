<?php

namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Models\Biometric\BiometricCommunicationLog;
use App\Models\Biometric\BiometricDevice;
use App\Models\Biometric\BiometricDeviceProfile;
use App\Services\Biometric\Adapters\DeviceAdapter;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\BiometricSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BiometricDeviceController extends Controller
{
    public function __construct(
        private BiometricDeviceService $deviceService,
        private BiometricSyncService $syncService,
    ) {}

    public function index()
    {
        $devices = BiometricDevice::withCount('biometricTemplates')
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
            'connection_type' => 'nullable|in:TCP/IP,Serial,USB,WebSocket',
            'protocol' => 'required|string|max:50',
            'api_key' => 'exclude_unless:protocol,HTTPAPI|nullable|string|max:255',
            'api_secret' => 'exclude_unless:protocol,HTTPAPI|nullable|string|max:255',
            'connection_timeout' => 'required|integer|min:5|max:300',
            'auto_sync' => 'boolean',
            'sync_interval' => 'required|integer|min:5|max:1440',
            'additional_config' => 'nullable|json',
            'timezone' => 'required|string|max:50',
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
            if (! $request->device_port) {
                $request->merge([
                    'device_port' => DeviceAdapter::getDefaultPort(
                        $request->manufacturer,
                        $request->device_model ?? 'default'
                    ),
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
                'max_connections' => 5,
            ]);

            // Probar conexión automáticamente
            try {
                $device->testConnection();
            } catch (\Exception $e) {
                Log::warning('No se pudo conectar automáticamente: '.$e->getMessage());
            }

            return redirect()->route('biometric.devices.index')
                ->with('success', "Dispositivo '{$device->device_name}' registrado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error al registrar dispositivo: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'Error al registrar dispositivo: '.$e->getMessage())
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
                'status' => $device->connection_status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al probar conexión: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncEmployees($deviceId, Request $request)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);

            $employeeIds = $request->validate([
                'employee_ids' => ['nullable', 'array'],
                'employee_ids.*' => ['integer', 'exists:employees,id'],
                'include_fingerprints' => ['nullable', 'boolean'],
            ])['employee_ids'] ?? null;
            $result = $this->syncService->pushEmployees($device, $employeeIds, $request->boolean('include_fingerprints'));
            $synced = $result['sent'];

            return response()->json([
                'success' => true,
                'message' => "Sincronizados {$synced} empleados",
                'data' => ['synced' => $synced],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pullBiometrics($deviceId)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $result = $this->syncService->pullEmployeesAndFingerprints($device);
            $result['stored_fingerprints'] = $device->biometricTemplates()->count();
            $message = 'Vinculados '.$result['matched'].' empleados y descargadas '.$result['fingerprints'].' huellas.';

            if ($result['matched'] > 0 && $result['fingerprints'] === 0) {
                $message = 'El biométrico entregó '.$result['matched'].' usuario(s), pero ninguna plantilla de huella. Verifique que las huellas estén enroladas en el equipo y que su firmware permita exportarlas.';
            }

            if ($result['unmatched'] > 0) {
                $message .= ' Hay '.$result['unmatched'].' usuario(s) del equipo sin empleado asociado en el sistema.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar usuarios y huellas: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncAttendance($deviceId, Request $request)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $result = $this->syncService->pullAttendance($device, $startDate, $endDate);
            $synced = $result['synced'];
            $errors = $result['unmatched'];
            $attendance = array_fill(0, array_sum($result), null);

            return response()->json([
                'success' => true,
                'message' => "Sincronizados {$synced} registros",
                'data' => [
                    'synced' => $synced,
                    'errors' => $errors,
                    'total' => count($attendance),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar asistencias: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getDeviceInfo($deviceId)
    {
        try {
            $device = BiometricDevice::findOrFail($deviceId);

            if (! $this->deviceService->connect($deviceId)) {
                throw new \Exception('No se pudo conectar al dispositivo');
            }

            $info = $this->deviceService->getDeviceInfo($deviceId);
            $this->deviceService->disconnect();

            return response()->json([
                'success' => true,
                'data' => $info,
                'device' => $device->device_name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableProfiles()
    {
        $profiles = BiometricDeviceProfile::where('is_active', true)->get();

        $manufacturers = $profiles->groupBy('manufacturer')->map(function ($items) {
            return $items->map(function ($item) {
                return [
                    'model' => $item->model,
                    'protocol' => $item->protocol,
                    'capabilities' => $item->capabilities,
                    'description' => $item->description,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $manufacturers,
        ]);
    }

    public function getDeviceLogs($deviceId, Request $request)
    {
        $logs = BiometricCommunicationLog::where('device_id', $deviceId)
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->command, function ($query, $command) {
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
            'devices.*.device_model' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $created = 0;
        $errors = [];

        foreach ($request->devices as $deviceData) {
            try {
                // Si no hay puerto, usar el por defecto
                if (! isset($deviceData['device_port'])) {
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
                    'auto_sync' => true,
                ]);

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error en {$deviceData['device_name']}: ".$e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Creados {$created} dispositivos",
            'errors' => $errors,
        ]);
    }
}
