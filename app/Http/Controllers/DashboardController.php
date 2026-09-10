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
        return view('dashboard', [
            'employeeCount' => Employee::where('is_active', true)->count(),
            'presentCount' => Attendance::whereDate('checked_at', today())->distinct('employee_id')->count('employee_id'),
            'lateCount' => Attendance::whereDate('checked_at', today())->where('status', 'late')->count(),
            'deviceCount' => BiometricDevice::where('is_active', true)->count(),
            'recentAttendances' => Attendance::with(['employee:id,first_name,last_name', 'device:id,device_name'])
                ->latest('checked_at')->limit(8)->get(),
        ]);
    }
}
