<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'is_active'];

    public function workUnits(): HasMany
    {
        return $this->hasMany(WorkUnit::class);
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
