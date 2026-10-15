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
            $table->string('abbreviation', 8)->nullable()->after('code');
            $table->string('color', 7)->nullable()->after('abbreviation');
        });

        $definitions = [
            'vacation' => ['VL', '#2563EB'],
            'medical' => ['BM', '#DC2626'],
            'personal' => ['AP', '#7C3AED'],
            'commission' => ['CO', '#059669'],
            'other' => ['OT', '#D97706'],
        ];

        DB::table('leave_types')->orderBy('id')->get()->each(function (object $leaveType) use ($definitions): void {
            [$abbreviation, $color] = $definitions[$leaveType->code]
                ?? ['TP'.$leaveType->id, '#0891B2'];

            DB::table('leave_types')->where('id', $leaveType->id)->update([
                'abbreviation' => $abbreviation,
                'color' => $color,
            ]);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->unique('abbreviation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropUnique(['abbreviation']);
            $table->dropColumn(['abbreviation', 'color']);
        });
    }
};
