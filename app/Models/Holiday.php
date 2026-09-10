<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = ['name', 'date', 'scope', 'is_recurring'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_recurring' => 'boolean'];
    }
}
