<?php

namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Models\Biometric\BiometricCommunicationLog;
use App\Models\Biometric\BiometricDevice;
use App\Models\Biometric\BiometricDeviceProfile;
use App\Models\Employee;
use App\Services\Biometric\Adapters\DeviceAdapter;
use App\Services\Biometric\BiometricDeviceService;
use App\Services\Biometric\BiometricSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $connectionAlerts = BiometricDevice::active()
            ->where(function ($query): void {
                $query->where('connection_status', '!=', 'connected')
                    ->orWhereNull('last_connection_test')
                    ->orWhere('last_connection_test', '<', now()->subMinutes(35));
            })
            ->orderBy('device_name')
            ->get(['id', 'device_name', 'connection_status', 'connection_error', 'last_connection_test']);

        return view('biometric.devices.index', compact('devices', 'profiles', 'connectionAlerts'));
    }

    public function connectionHistory(Request $request): View
    {
        $filters = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:biometric_devices,id'],
            'status' => ['nullable', Rule::in(['success', 'failed'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
        ]);

        $logs = BiometricCommunicationLog::with('device:id,device_name,device_ip,device_port')
            ->where('command', 'connect')
            ->when($filters['device_id'] ?? null, fn ($query, $id) => $query->where('device_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['start_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(30)->withQueryString();
        $devices = BiometricDevice::orderBy('device_name')->get(['id', 'device_name']);

        return view('biometric.devices.connection-history', compact('logs', 'devices', 'filters'));
    }

    public function edit(BiometricDevice $device): View
    {
        return view('biometric.devices.edit', compact('device'));
    }

    public function update(Request $request, BiometricDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:100', Rule::unique('biometric_devices', 'device_name')->ignore($device)],
            'device_ip' => ['required', 'ip'],
            'device_port' => ['required', 'integer', 'between:1,65535'],
            'manufacturer' => ['required', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'protocol' => ['required', Rule::in(['ZKProtocol', 'HTTPAPI', 'Serial', 'WebSocket'])],
            'connection_timeout' => ['required', 'integer', 'between:5,300'],
            'sync_interval' => ['required', 'integer', 'between:5,1440'],
            'auto_sync' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'api_key' => ['exclude_unless:protocol,HTTPAPI', 'nullable', 'string', 'max:255'],
            'api_secret' => ['exclude_unless:protocol,HTTPAPI', 'nullable', 'string', 'max:255'],
        ]);

        foreach (['api_key', 'api_secret'] as $credential) {
            if (! isset($data[$credential]) || $data[$credential] === '') {
                unset($data[$credential]);
            }
        }

        $device->fill($data);
        if ($device->isDirty(['manufacturer', 'device_model'])) {
            $device->capabilities = DeviceAdapter::getCapabilities($device->manufacturer, $device->device_model ?? 'default');
        }
        if ($device->isDirty(['device_ip', 'device_port', 'protocol', 'api_key', 'api_secret'])) {
            $device->connection_status = 'disconnected';
            $device->connection_error = null;
            $device->last_connection_test = null;
        }
        $device->save();

        return redirect()->route('biometric.devices.index')
            ->with('success', 'Dispositivo actualizado correctamente.');
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
                'message' => $result
                    ? ($this->deviceService->timeSynchronized
                        ? 'Conexión exitosa. Hora sincronizada con Bolivia (UTC−4).'
                        : 'Conexión exitosa, pero no se pudo sincronizar la hora: '.$this->deviceService->timeSynchronizationError)
                    : ($device->connection_error ?: 'No se pudo conectar'),
                'time_synchronized' => $this->deviceService->timeSynchronized,
                'timezone' => config('services.biometric.timezone'),
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

    public function deviceUsers(BiometricDevice $device, Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $deviceId = $device->id;
        abort_unless($device->protocol === 'ZKProtocol', 422, 'Esta función está disponible para dispositivos ZKTeco.');

        try {
            $users = collect($this->deviceService->getUsers($deviceId))->map(fn (array $user): array => $this->deviceUserIdentity($user))->values();
            $request->session()->put('biometric_user_selection.'.$deviceId, $users->keyBy('uid')->all());

            return response()->json(['users' => $users]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => 'No se pudieron consultar los usuarios: '.$exception->getMessage()], 500);
        } finally {
            $this->deviceService->disconnect();
        }
    }

    public function deleteDeviceUsers(BiometricDevice $device, Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'min:1', 'max:65535', 'distinct'],
            'confirmed' => ['required', 'accepted'],
        ]);
        $deviceId = $device->id;
        $snapshot = $request->session()->get('biometric_user_selection.'.$deviceId, []);
        foreach ($validated['user_ids'] as $uid) {
            abort_unless(isset($snapshot[$uid]) && $snapshot[$uid]['role'] === 0, 422, 'Consulte la lista y seleccione usuarios sin privilegios de administrador.');
        }
        abort_unless($device->protocol === 'ZKProtocol', 422, 'Esta función está disponible para dispositivos ZKTeco.');
        $deleted = [];

        try {
            $currentUsers = collect($this->deviceService->getUsers($deviceId))->map(fn (array $user): array => $this->deviceUserIdentity($user))->keyBy('uid');
            foreach ($validated['user_ids'] as $uid) {
                if ($currentUsers->get($uid) !== $snapshot[$uid]) {
                    return response()->json(['success' => false, 'message' => 'La lista del reloj cambió. Actualícela antes de eliminar usuarios.'], 409);
                }
            }
            foreach ($validated['user_ids'] as $uid) {
                $this->deviceService->deleteUser($deviceId, (int) $uid);
                $deleted[] = (int) $uid;
                unset($snapshot[$uid]);
                $request->session()->put('biometric_user_selection.'.$deviceId, $snapshot);
            }

            return response()->json(['success' => true, 'message' => count($deleted).' usuarios eliminados del biométrico.', 'deleted' => $deleted]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => 'Eliminados '.count($deleted).' usuarios. No se pudo completar la operación: '.$exception->getMessage(), 'deleted' => $deleted], 500);
        } finally {
            $this->deviceService->disconnect();
        }
    }

    /** @return array{uid: int, userid: string, name: string, role: int} */
    private function deviceUserIdentity(array $user): array
    {
        return ['uid' => (int) ($user['uid'] ?? 0), 'userid' => (string) ($user['userid'] ?? ''), 'name' => (string) ($user['name'] ?? ''), 'role' => (int) ($user['role'] ?? -1)];
    }

    public function employeesForSync(): JsonResponse
    {
        abort_unless(request()->user()?->is_admin, 403);

        $employees = Employee::query()->where('is_active', true)
            ->with('workUnit:id,name')->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'maternal_last_name', 'employee_code', 'work_unit_id'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'code' => $employee->employee_code,
                'unit_id' => $employee->work_unit_id,
                'unit_name' => $employee->workUnit?->name ?? 'Sin unidad',
            ]);

        return response()->json(['employees' => $employees]);
    }

    public function syncEmployees($deviceId, Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct', Rule::exists('employees', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'include_fingerprints' => ['nullable', 'boolean'],
        ], ['employee_ids.required' => 'Seleccione al menos un empleado para enviar.', 'employee_ids.min' => 'Seleccione al menos un empleado para enviar.']);

        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $result = $this->syncService->pushEmployees($device, $validated['employee_ids'], $request->boolean('include_fingerprints'));
            $synced = $result['sent'];
            $errors = $result['errors'];

            return response()->json([
                'success' => $errors === 0,
                'message' => "Enviados o actualizados {$synced} empleados. Errores: {$errors}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pullBiometrics($deviceId, Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);
        $request->validate(['include_fingerprints' => ['nullable', 'boolean']]);
        try {
            $device = BiometricDevice::findOrFail($deviceId);
            $includeFingerprints = $request->boolean('include_fingerprints', true);
            $result = $this->syncService->pullEmployeesAndFingerprints($device, $includeFingerprints);
            $result['stored_fingerprints'] = $device->biometricTemplates()->count();
            $message = $includeFingerprints
                ? 'Vinculados '.$result['matched'].' empleados y descargadas '.$result['fingerprints'].' huellas.'
                : 'Importados o vinculados '.$result['matched'].' usuarios del biométrico.';

            if ($includeFingerprints && $result['matched'] > 0 && $result['fingerprints'] === 0) {
                $message = 'El biométrico entregó '.$result['matched'].' usuario(s), pero ninguna plantilla de huella. Verifique que las huellas estén enroladas en el equipo y que su firmware permita exportarlas.';
            }

            if ($result['created'] > 0) {
                $message .= ' Importados '.$result['created'].' empleado(s) nuevos; complete sus datos personales y laborales.';
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
