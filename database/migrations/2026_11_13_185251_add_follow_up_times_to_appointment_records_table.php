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
        Schema::table('appointment_records', function (Blueprint $table) {
            $table->time('infiltration_remove_at')->nullable()->after('infiltration_remove_on');
            $table->time('return_at')->nullable()->after('return_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_records', function (Blueprint $table) {
            $table->dropColumn(['infiltration_remove_at', 'return_at']);
        });
    }
};
