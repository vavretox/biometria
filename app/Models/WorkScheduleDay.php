<?php

namespace App\Models;

use Database\Factories\WorkScheduleDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleDay extends Model
{
    /** @use HasFactory<WorkScheduleDayFactory> */
    use HasFactory;

    protected $fillable = ['day_of_week', 'starts_at', 'ends_at', 'second_starts_at', 'second_ends_at', 'grace_minutes', 'is_working_day'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    protected function casts(): array
    {
        return ['is_working_day' => 'boolean'];
    }
}
