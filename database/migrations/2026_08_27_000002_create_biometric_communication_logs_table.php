<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricCommunicationLogsTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_communication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('command');
            $table->string('status');
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('biometric_devices')->onDelete('cascade');
            $table->index(['device_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_communication_logs');
    }
}
