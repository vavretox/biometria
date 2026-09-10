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
        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->time('second_starts_at')->nullable()->after('ends_at');
            $table->time('second_ends_at')->nullable()->after('second_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->dropColumn(['second_starts_at', 'second_ends_at']);
        });
    }
};
