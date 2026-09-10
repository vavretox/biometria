<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name')->unique();
            $table->string('device_ip', 45)->nullable();
            $table->integer('device_port')->default(4370);
            $table->string('device_model')->nullable();
            $table->string('manufacturer')->default('ZKSoftware');
            $table->string('serial_number')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('location')->nullable();
            $table->string('timezone', 50)->default('America/La_Paz');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->integer('sync_interval')->default(60);
            $table->integer('max_connections')->default(5);
            $table->integer('connection_timeout')->default(30);
            $table->string('connection_type')->default('TCP/IP');
            $table->string('protocol')->default('ZKProtocol');
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->json('additional_config')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_sync')->nullable();
            $table->timestamp('last_connection_test')->nullable();
            $table->string('connection_status')->default('disconnected');
            $table->text('connection_error')->nullable();
            $table->timestamps();

            $table->index(['device_ip', 'device_port']);
            $table->index(['manufacturer', 'device_model']);
            $table->index('connection_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_devices');
    }
}
