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
        Schema::create('appointment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_id')->nullable()->constrained()->nullOnDelete();
            $table->text('fluid_instructions')->nullable();
            $table->string('infiltration_site', 150)->nullable();
            $table->date('infiltration_remove_on')->nullable();
            $table->string('infiltration_removal_place', 20)->nullable();
            $table->foreignId('infiltration_removal_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->date('return_on')->nullable();
            $table->foreignId('return_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mentor_id');
            $table->index('infiltration_removal_appointment_id');
            $table->index('return_appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_records');
    }
};
