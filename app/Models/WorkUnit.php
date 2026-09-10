<?php

namespace App\Models;

use Database\Factories\WorkUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkUnit extends Model
{
    /** @use HasFactory<WorkUnitFactory> */
    use HasFactory;

    protected $fillable = ['office_id', 'name', 'code', 'description', 'is_active'];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
