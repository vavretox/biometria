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
        Schema::create('biometric_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('finger_index');
            $table->text('template_data');
            $table->string('format', 40)->default('zk-template');
            $table->string('checksum', 64);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['employee_id', 'device_id', 'finger_index']);
            $table->index(['device_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_templates');
    }
};
