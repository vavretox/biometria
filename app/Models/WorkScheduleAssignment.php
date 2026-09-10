<?php

namespace App\Models;

use Database\Factories\WorkScheduleAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleAssignment extends Model
{
    /** @use HasFactory<WorkScheduleAssignmentFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'work_schedule_id', 'starts_on', 'ends_on', 'assigned_by'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
