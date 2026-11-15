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
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->json('weekday_times')->nullable()->after('weekdays');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn('weekday_times');
        });
    }
};
