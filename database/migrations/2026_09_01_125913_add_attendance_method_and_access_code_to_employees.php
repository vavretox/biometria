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
            $table->string('attendance_method', 20)->default('biometric')->after('biometric_id');
            $table->text('access_code')->nullable()->after('attendance_method');
            $table->index(['attendance_method', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['attendance_method', 'is_active']);
            $table->dropColumn(['attendance_method', 'access_code']);
        });
    }
};
