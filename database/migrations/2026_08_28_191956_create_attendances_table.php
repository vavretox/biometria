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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->string('type', 20)->default('in');
            $table->string('status', 20)->default('normal');
            $table->string('source', 20)->default('biometric');
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'device_id', 'checked_at']);
            $table->index(['checked_at', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
