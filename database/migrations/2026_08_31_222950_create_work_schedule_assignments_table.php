<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'starts_on', 'ends_on'], 'schedule_assignments_employee_dates_index');
            $table->index(['work_schedule_id', 'starts_on', 'ends_on'], 'schedule_assignments_schedule_dates_index');
        });

        $now = now();
        DB::table('employees')
            ->whereNotNull('work_schedule_id')
            ->orderBy('id')
            ->eachById(function (object $employee) use ($now): void {
                DB::table('work_schedule_assignments')->insert([
                    'employee_id' => $employee->id,
                    'work_schedule_id' => $employee->work_schedule_id,
                    'starts_on' => $now->toDateString(),
                    'ends_on' => null,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_assignments');
    }
};
