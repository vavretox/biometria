<?php

namespace App\Models\Biometric;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricCommunicationLog extends Model
{
    protected $table = 'biometric_communication_logs';

    protected $fillable = [
        'device_id', 'command', 'status', 'request_data',
        'response_data', 'error_message', 'response_time_ms',
        'additional_info',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'additional_info' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
