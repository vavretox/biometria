<?php

namespace App\Models;

use App\Models\Biometric\BiometricDevice;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'device_id', 'checked_at', 'type', 'status', 'late_minutes', 'observation', 'source', 'raw_data'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'raw_data' => 'array'];
    }
}
