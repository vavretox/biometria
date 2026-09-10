<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'office_id', 'work_unit_id', 'work_schedule_id', 'employee_code', 'item_number',
        'employee_type', 'budget_line', 'first_name', 'last_name', 'maternal_last_name',
        'identity_document', 'identity_issued_in', 'has_children', 'address',
        'email', 'phone', 'mobile', 'position', 'job_description',
        'employment_observations', 'memo_date', 'memo_number', 'department',
        'biometric_id', 'attendance_method', 'access_code', 'hire_date', 'employment_end_date', 'birth_date',
        'marital_status', 'gender', 'is_active',
    ];

    protected $hidden = [
        'access_code',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function workUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class);
    }

    public function scheduleForDate(CarbonInterface $date): ?WorkSchedule
    {
        $dateString = $date->toDateString();

        if ($this->relationLoaded('scheduleAssignments')) {
            return $this->scheduleAssignments
                ->filter(fn (WorkScheduleAssignment $assignment): bool => $assignment->starts_on->toDateString() <= $dateString
                    && ($assignment->ends_on === null || $assignment->ends_on->toDateString() >= $dateString))
                ->sortByDesc('starts_on')
                ->first()?->workSchedule;
        }

        return $this->scheduleAssignments()
            ->whereDate('starts_on', '<=', $dateString)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $dateString))
            ->latest('starts_on')
            ->first()?->workSchedule;
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->last_name, $this->maternal_last_name])
            ->filter()
            ->join(' ');
    }

    public function getAttendanceMethodLabelAttribute(): string
    {
        return match ($this->attendance_method) {
            'access_code' => 'Código de acceso',
            'automatic' => 'Marcación automática',
            default => 'Biometría',
        };
    }

    public function getAttendanceIdentifierAttribute(): string
    {
        if ($this->attendance_method === 'access_code') {
            return $this->access_code ? 'Código configurado' : 'Código pendiente';
        }

        return $this->biometric_id ? 'ID biométrico '.$this->biometric_id : 'ID biométrico pendiente';
    }

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'birth_date' => 'date',
            'employment_end_date' => 'date',
            'memo_date' => 'date',
            'has_children' => 'boolean',
            'is_active' => 'boolean',
            'access_code' => 'encrypted',
        ];
    }
}
