<?php

namespace App\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'abbreviation', 'color', 'is_active'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    /** @var list<string> */
    public const COLOR_PALETTE = [
        '#2563EB', '#DC2626', '#7C3AED', '#059669', '#D97706',
        '#0891B2', '#DB2777', '#4F46E5', '#65A30D', '#EA580C',
    ];

    public static function nextAvailableAbbreviation(string $name): string
    {
        $words = collect(preg_split('/[^A-Z0-9]+/', Str::upper(Str::ascii($name))) ?: [])
            ->filter()
            ->reject(fn (string $word): bool => in_array($word, ['DE', 'DEL', 'LA', 'EL', 'POR', 'PARA', 'Y'], true))
            ->values();
        $base = $words->count() > 1
            ? $words->map(fn (string $word): string => $word[0])->take(3)->implode('')
            : substr($words->first() ?? 'TP', 0, 2);
        $abbreviation = $base;
        $suffix = 2;

        while (static::where('abbreviation', $abbreviation)->exists()) {
            $abbreviation = substr($base, 0, 3).$suffix;
            $suffix++;
        }

        return $abbreviation;
    }

    public static function nextColor(): string
    {
        return self::COLOR_PALETTE[static::count() % count(self::COLOR_PALETTE)];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
