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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('identity_document', 30)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('employees')->whereNull('identity_document')->exists()) {
            throw new RuntimeException('Complete los documentos pendientes antes de revertir esta migración.');
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('identity_document', 30)->nullable(false)->change();
        });
    }
};
