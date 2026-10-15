<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('work_unit_id')->nullable()->after('office_id')->constrained()->nullOnDelete();
            $table->string('item_number', 50)->nullable()->unique()->after('employee_code');
            $table->string('employee_type', 30)->nullable()->after('item_number');
            $table->string('budget_line', 50)->nullable()->after('employee_type');
            $table->date('employment_end_date')->nullable()->after('hire_date');
            $table->text('job_description')->nullable()->after('position');
            $table->text('employment_observations')->nullable()->after('job_description');
            $table->date('memo_date')->nullable()->after('employment_observations');
            $table->string('memo_number', 50)->nullable()->after('memo_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_unit_id');
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn([
                'item_number', 'employee_type', 'budget_line', 'employment_end_date',
                'job_description', 'employment_observations', 'memo_date', 'memo_number',
            ]);
        });
    }
};
