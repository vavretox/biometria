<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\Employee;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $employees = Employee::where('is_active', true)->count();
        $marks = Attendance::whereBetween('checked_at', [$today->copy()->subDays(13), $today->copy()->endOfDay()])
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->get(['employee_id', 'checked_at', 'status', 'type']);
        $todayMarks = $marks->filter(fn (Attendance $mark): bool => $mark->checked_at->isSameDay($today));
        $present = $todayMarks->pluck('employee_id')->unique()->count();
        $history = collect(range(13, 0))->map(function (int $offset) use ($marks, $today): array {
            $date = $today->copy()->subDays($offset);
            $dayMarks = $marks->filter(fn (Attendance $mark): bool => $mark->checked_at->isSameDay($date));

            return [
                'date' => $date->format('d/m'),
                'late' => $dayMarks->where('status', 'late')->pluck('employee_id')->unique()->count(),
                'early' => $dayMarks->where('status', 'early')->pluck('employee_id')->unique()->count(),
            ];
        });
        $hourlyMarks = collect(range(0, 23))->map(fn (int $hour): int => $todayMarks
            ->filter(fn (Attendance $mark): bool => $mark->checked_at->hour === $hour)->count());

        return view('dashboard', [
            'employeeCount' => $employees,
            'presentCount' => $present,
            'withoutMarksCount' => max(0, $employees - $present),
            'presencePercentage' => $employees > 0 ? (int) round($present / $employees * 100) : 0,
            'lateCount' => $todayMarks->where('status', 'late')->pluck('employee_id')->unique()->count(),
            'markCount' => $todayMarks->count(),
            'history' => $history,
            'hourlyMarks' => $hourlyMarks,
            'deviceCount' => BiometricDevice::where('is_active', true)->count(),
            'recentAttendances' => Attendance::with(['employee:id,first_name,last_name', 'device:id,device_name'])
                ->whereHas('employee')->whereDate('checked_at', $today)
                ->latest('checked_at')->limit(8)->get(),
        ]);
    }
}
