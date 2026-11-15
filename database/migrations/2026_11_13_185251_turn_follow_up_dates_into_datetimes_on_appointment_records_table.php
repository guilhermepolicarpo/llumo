<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The removal and return keep their day and gain a time; the days already saved get midnight, read as no time.
     */
    public function up(): void
    {
        Schema::table('appointment_records', function (Blueprint $table) {
            $table->dateTime('infiltration_remove_on')->nullable()->change();
            $table->dateTime('return_on')->nullable()->change();
        });

        Schema::table('appointment_records', function (Blueprint $table) {
            $table->renameColumn('infiltration_remove_on', 'infiltration_remove_at');
            $table->renameColumn('return_on', 'return_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_records', function (Blueprint $table) {
            $table->renameColumn('infiltration_remove_at', 'infiltration_remove_on');
            $table->renameColumn('return_at', 'return_on');
        });

        Schema::table('appointment_records', function (Blueprint $table) {
            $table->date('infiltration_remove_on')->nullable()->change();
            $table->date('return_on')->nullable()->change();
        });
    }
};
