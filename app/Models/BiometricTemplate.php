<?php

namespace App\Models;

use App\Models\Biometric\BiometricDevice;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricTemplate extends Model
{
    /** @use HasFactory<BiometricTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id', 'device_id', 'finger_index', 'template_data', 'format', 'checksum', 'synced_at',
    ];

    protected $hidden = ['template_data'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    protected function casts(): array
    {
        return [
            'template_data' => 'encrypted',
            'synced_at' => 'datetime',
        ];
    }
}
