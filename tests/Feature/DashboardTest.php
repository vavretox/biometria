<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_count_active_employees_once_and_limit_activity_to_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00'));
        $employee = Employee::factory()->create(['is_active' => true]);
        Employee::factory()->create(['is_active' => true]);
        $inactive = Employee::factory()->create(['is_active' => false]);
        foreach (['08:00', '09:00'] as $time) {
            Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-10-07 '.$time, 'status' => 'late']);
        }
        Attendance::factory()->create(['employee_id' => $inactive->id, 'checked_at' => '2026-10-07 08:00', 'status' => 'late']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'checked_at' => '2026-10-06 08:00', 'status' => 'early']);
        $this->withoutVite()->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertViewHas('employeeCount', 2)
            ->assertViewHas('presentCount', 1)
            ->assertViewHas('withoutMarksCount', 1)
            ->assertViewHas('presencePercentage', 50)
            ->assertViewHas('lateCount', 1)
            ->assertViewHas('markCount', 2)
            ->assertViewHas('history', fn ($history): bool => $history->count() === 14 && $history->last()['late'] === 1 && $history[12]['early'] === 1)
            ->assertViewHas('hourlyMarks', fn ($hours): bool => $hours[8] === 1 && $hours[9] === 1)
            ->assertSee('Incidencias de asistencia');
    }

    public function test_empty_dashboard_shows_zero_metrics(): void
    {
        $this->withoutVite()->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('presencePercentage', 0)
            ->assertViewHas('withoutMarksCount', 0)
            ->assertSee('Todavía no hay marcaciones hoy');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Resumen de hoy');
    }
}
