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
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('name', 100)->after('id');
            $table->string('code', 30)->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('code');
        });

        $now = now();
        DB::table('leave_types')->insert([
            ['name' => 'Vacación', 'code' => 'vacation', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Baja médica', 'code' => 'medical', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Asunto personal', 'code' => 'personal', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Comisión', 'code' => 'commission', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Otro', 'code' => 'other', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
        });

        DB::table('leave_types')->orderBy('id')->each(function (object $leaveType): void {
            DB::table('leave_requests')->where('type', $leaveType->code)->update(['leave_type_id' => $leaveType->id]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_type_id');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'is_active']);
        });
    }
};
