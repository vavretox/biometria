<?php

namespace App\Services\Biometric;

use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BiometricSyncService
{
    public function __construct(private BiometricDeviceService $deviceService) {}

    /** @return array{sent: int, fingerprints: int, errors: int} */
    public function pushEmployees(BiometricDevice $device, ?array $employeeIds = null, bool $includeFingerprints = false): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->when($employeeIds !== null, fn (Builder $query) => $query->whereIn('id', $employeeIds))
            ->with(['biometricTemplates' => fn ($query) => $query->where('device_id', $device->id)])
            ->orderBy('id')
            ->get();
        $sent = 0;
        $fingerprints = 0;
        $errors = 0;

        $this->deviceService->connect($device->id);

        try {
            foreach ($employees as $employee) {
                try {
                    $uid = $this->ensureBiometricId($employee);
                    $this->deviceService->setUser($device->id, [
                        'uid' => $uid,
                        'userid' => $uid,
                        'name' => mb_substr($employee->full_name, 0, 24),
                        'password' => $employee->attendance_method === 'access_code' ? $employee->access_code : '',
                        'role' => 0,
                        'cardno' => 0,
                    ]);
                    $sent++;

                    if ($includeFingerprints && $employee->biometricTemplates->isNotEmpty()) {
                        $templates = $employee->biometricTemplates
                            ->mapWithKeys(fn (BiometricTemplate $template) => [
                                $template->finger_index => base64_decode($template->template_data, true),
                            ])->filter(fn ($template) => $template !== false)->all();

                        if ($templates !== []) {
                            $this->deviceService->setFingerprints($device->id, $uid, $templates);
                            $fingerprints += count($templates);
                        }
                    }
                } catch (\Throwable) {
                    $errors++;
                }
            }
        } finally {
            $this->finishSync($device);
        }

        return compact('sent', 'fingerprints', 'errors');
    }

    /** @return array{matched: int, created: int, fingerprints: int, unmatched: int, without_fingerprints: int} */
    public function pullEmployeesAndFingerprints(BiometricDevice $device, bool $includeFingerprints = true): array
    {
        $matched = 0;
        $created = 0;
        $fingerprints = 0;
        $unmatched = 0;
        $withoutFingerprints = 0;
        $this->deviceService->connect($device->id);

        try {
            foreach ($this->deviceService->getUsers($device->id) as $user) {
                $uid = (int) ($user['uid'] ?? 0);
                $employeeCode = trim((string) ($user['userid'] ?? ''));
                if ($uid < 1 || $employeeCode === '' || mb_strlen($employeeCode) > 50) {
                    $unmatched++;

                    continue;
                }
                $employee = Employee::withTrashed()->where('employee_code', $employeeCode)->first()
                    ?? Employee::withTrashed()->where('biometric_id', $uid)->first();

                if ($employee?->trashed() || ($employee && $employee->biometric_id && (int) $employee->biometric_id !== $uid)) {
                    $unmatched++;

                    continue;
                }

                if (! $employee) {
                    $employee = Employee::create([
                        'employee_code' => $employeeCode, 'biometric_id' => $uid,
                        'first_name' => mb_substr(trim((string) ($user['name'] ?? '')), 0, 100) ?: 'Usuario '.$employeeCode,
                        'last_name' => '', 'identity_document' => null,
                        'attendance_method' => 'biometric', 'is_active' => true,
                    ]);
                    $created++;
                }

                if (! $employee->biometric_id) {
                    $employee->update(['biometric_id' => $uid]);
                }
                $matched++;

                if (! $includeFingerprints) {
                    continue;
                }

                $deviceFingerprints = $this->deviceService->getFingerprints($device->id, $uid);

                if ($deviceFingerprints === []) {
                    $withoutFingerprints++;
                }

                foreach ($deviceFingerprints as $fingerIndex => $templateData) {
                    if (! is_string($templateData) || $templateData === '') {
                        continue;
                    }

                    $encodedTemplate = base64_encode($templateData);
                    BiometricTemplate::updateOrCreate(
                        ['employee_id' => $employee->id, 'device_id' => $device->id, 'finger_index' => (int) $fingerIndex],
                        ['template_data' => $encodedTemplate, 'checksum' => hash('sha256', $templateData), 'synced_at' => now()],
                    );
                    $fingerprints++;
                }
            }
        } finally {
            $this->finishSync($device);
        }

        return [
            'matched' => $matched,
            'created' => $created,
            'fingerprints' => $fingerprints,
            'unmatched' => $unmatched,
            'without_fingerprints' => $withoutFingerprints,
        ];
    }

    /** @return array{synced: int, duplicates: int, unmatched: int, ignored: int, invalid: int} */
    public function pullAttendance(BiometricDevice $device, ?string $startDate = null, ?string $endDate = null): array
    {
        $synced = 0;
        $duplicates = 0;
        $unmatched = 0;
        $ignored = 0;
        $invalid = 0;
        $this->deviceService->connect($device->id);

        try {
            foreach ($this->deviceService->getAttendance($device->id, $startDate, $endDate) as $log) {
                $checkedAt = is_numeric($log['timestamp'] ?? null)
                    ? Carbon::createFromTimestamp((int) $log['timestamp'], $device->timezone)
                    : Carbon::parse($log['timestamp'], $device->timezone);

                if ($checkedAt->year < 2000 || $checkedAt->greaterThan(now($device->timezone)->addDay())) {
                    $invalid++;

                    continue;
                }

                $uid = (int) ($log['uid'] ?? 0);
                $employee = Employee::with(['workSchedule.days', 'scheduleAssignments.workSchedule.days'])
                    ->where('biometric_id', $uid)->first();

                if (! $employee) {
                    $unmatched++;

                    continue;
                }

                if ($employee->attendance_method === 'automatic') {
                    $ignored++;

                    continue;
                }

                $type = $this->resolveType($log, $employee, $checkedAt);
                $evaluation = $this->evaluateSchedule($employee, $checkedAt, $type);
                $attendance = Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'device_id' => $device->id, 'checked_at' => $checkedAt],
                    ['type' => $type, 'status' => $evaluation['status'], 'late_minutes' => $evaluation['late_minutes'], 'source' => $employee->attendance_method === 'access_code' ? 'access_code' : 'biometric', 'raw_data' => $log],
                );

                $attendance->wasRecentlyCreated ? $synced++ : $duplicates++;
            }
        } finally {
            $this->finishSync($device);
        }

        return compact('synced', 'duplicates', 'unmatched', 'ignored', 'invalid');
    }

    private function ensureBiometricId(Employee $employee): int
    {
        $uid = (int) ($employee->biometric_id ?: $employee->id);

        if ($uid < 1 || $uid > 65535) {
            throw new \RuntimeException('El identificador biométrico debe estar entre 1 y 65535.');
        }

        if (! $employee->biometric_id) {
            $employee->update(['biometric_id' => $uid]);
        }

        return $uid;
    }

    private function resolveType(array $log, Employee $employee, Carbon $checkedAt): string
    {
        $deviceType = (int) ($log['state'] ?? $log['type'] ?? -1);
        $mappedType = [0 => 'in', 1 => 'out', 2 => 'break_out', 3 => 'break_in', 4 => 'in', 5 => 'out'][$deviceType] ?? null;

        if ($mappedType) {
            return $mappedType;
        }

        return Attendance::where('employee_id', $employee->id)
            ->whereDate('checked_at', $checkedAt->toDateString())
            ->exists() ? 'out' : 'in';
    }

    /** @return array{status: string, late_minutes: int} */
    private function evaluateSchedule(Employee $employee, Carbon $checkedAt, string $type): array
    {
        $schedule = $employee->attendanceScheduleForDate($checkedAt);
        $scheduleDay = $schedule?->days->firstWhere('day_of_week', $checkedAt->isoWeekday());

        if (! $scheduleDay || ! $scheduleDay->is_working_day) {
            return ['status' => 'normal', 'late_minutes' => 0];
        }

        if ($type === 'in' && $scheduleDay->starts_at) {
            $firstEnd = Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->ends_at, $checkedAt->timezone);
            $scheduledStart = $scheduleDay->second_starts_at && $checkedAt->greaterThan($firstEnd)
                ? Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->second_starts_at, $checkedAt->timezone)
                : Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->starts_at, $checkedAt->timezone);
            $lateMinutes = $checkedAt->greaterThan($scheduledStart)
                ? (int) $scheduledStart->diffInMinutes($checkedAt)
                : 0;
            $previousLateMinutes = (int) Attendance::where('employee_id', $employee->id)
                ->whereDate('checked_at', $checkedAt->toDateString())
                ->sum('late_minutes');

            return [
                'status' => $previousLateMinutes + $lateMinutes > $scheduleDay->grace_minutes ? 'late' : 'normal',
                'late_minutes' => $lateMinutes,
            ];
        }

        if ($type === 'out' && $scheduleDay->ends_at) {
            $scheduledEnd = $scheduleDay->second_ends_at && $scheduleDay->second_starts_at
                && $checkedAt->greaterThan(Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->second_starts_at, $checkedAt->timezone))
                ? Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->second_ends_at, $checkedAt->timezone)
                : Carbon::parse($checkedAt->toDateString().' '.$scheduleDay->ends_at, $checkedAt->timezone);

            return ['status' => $checkedAt->lessThan($scheduledEnd) ? 'early' : 'normal', 'late_minutes' => 0];
        }

        return ['status' => 'normal', 'late_minutes' => 0];
    }

    private function finishSync(BiometricDevice $device): void
    {
        $this->deviceService->disconnect();
        $device->update(['last_sync' => now()]);
    }
}
