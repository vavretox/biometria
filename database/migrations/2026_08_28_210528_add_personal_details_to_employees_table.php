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
            $table->string('maternal_last_name', 100)->nullable()->after('last_name');
            $table->string('identity_issued_in', 10)->nullable()->after('identity_document');
            $table->boolean('has_children')->default(false)->after('identity_issued_in');
            $table->string('address')->nullable()->after('has_children');
            $table->string('mobile', 30)->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('hire_date');
            $table->string('marital_status', 30)->nullable()->after('birth_date');
            $table->string('gender', 20)->nullable()->after('marital_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'maternal_last_name', 'identity_issued_in', 'has_children',
                'address', 'mobile', 'birth_date', 'marital_status', 'gender',
            ]);
        });
    }
};
