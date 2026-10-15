<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Biometric\BiometricDeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:manage-users');
    Route::resource('employees', EmployeeController::class);
    Route::get('/office-units', [OfficeController::class, 'index'])->name('office-units.index');
    Route::post('/work-units', [OfficeController::class, 'storeWorkUnit'])->name('work-units.store');
    Route::put('/work-units/{workUnit}', [OfficeController::class, 'updateWorkUnit'])->name('work-units.update');
    Route::delete('/work-units/{workUnit}', [OfficeController::class, 'destroyWorkUnit'])->name('work-units.destroy');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    Route::get('/schedules', [WorkScheduleController::class, 'index'])->name('schedules.index');
    Route::post('/schedules', [WorkScheduleController::class, 'store'])->name('schedules.store');
    Route::post('/schedules/assign', [WorkScheduleController::class, 'assign'])->name('schedules.assign');
    Route::put('/schedules/{workSchedule}', [WorkScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::get('/leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::put('/leaves/{leaveRequest}', [LeaveRequestController::class, 'update'])->name('leaves.update');
    Route::delete('/leaves/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
    Route::put('/leaves/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
    Route::resource('leave-types', LeaveTypeController::class)->only(['store', 'update', 'destroy']);
    Route::post('/holidays', [LeaveRequestController::class, 'storeHoliday'])->name('holidays.store');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/daily-export', [ReportExportController::class, 'daily'])->name('reports.daily-export');
    Route::get('/reports/export', ReportExportController::class)->name('reports.export');

    Route::prefix('biometric')->group(function (): void {
        Route::get('/devices', [BiometricDeviceController::class, 'index'])
            ->name('biometric.devices.index');

        Route::get('/connection-history', [BiometricDeviceController::class, 'connectionHistory'])
            ->name('biometric.devices.connection-history');

        Route::post('/devices', [BiometricDeviceController::class, 'store'])
            ->name('biometric.devices.store');

        Route::get('/devices/{device}/edit', [BiometricDeviceController::class, 'edit'])->name('biometric.devices.edit');
        Route::put('/devices/{device}', [BiometricDeviceController::class, 'update'])->name('biometric.devices.update');

        Route::post('/devices/{deviceId}/test', [BiometricDeviceController::class, 'testConnection'])
            ->name('biometric.devices.test');

        Route::get('/employees-for-sync', [BiometricDeviceController::class, 'employeesForSync'])->name('biometric.employees-for-sync');

        Route::get('/devices/{device}/users', [BiometricDeviceController::class, 'deviceUsers'])->name('biometric.devices.users');
        Route::delete('/devices/{device}/users', [BiometricDeviceController::class, 'deleteDeviceUsers'])->name('biometric.devices.users.destroy');

        Route::post('/devices/{deviceId}/sync-employees', [BiometricDeviceController::class, 'syncEmployees'])
            ->name('biometric.devices.sync-employees');

        Route::post('/devices/{deviceId}/pull-biometrics', [BiometricDeviceController::class, 'pullBiometrics'])
            ->name('biometric.devices.pull-biometrics');

        Route::post('/devices/{deviceId}/sync-attendance', [BiometricDeviceController::class, 'syncAttendance'])
            ->name('biometric.devices.sync-attendance');

        Route::get('/devices/{deviceId}/info', [BiometricDeviceController::class, 'getDeviceInfo'])
            ->name('biometric.devices.info');

        Route::get('/devices/{deviceId}/logs', [BiometricDeviceController::class, 'getDeviceLogs'])
            ->name('biometric.devices.logs');

        Route::get('/profiles', [BiometricDeviceController::class, 'getAvailableProfiles'])
            ->name('biometric.devices.profiles');

        Route::post('/devices/bulk-import', [BiometricDeviceController::class, 'bulkImport'])
            ->name('biometric.devices.bulk-import');
    });
});
