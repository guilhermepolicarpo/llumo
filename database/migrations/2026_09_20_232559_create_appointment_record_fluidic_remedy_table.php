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
        Schema::create('appointment_record_fluidic_remedy', function (Blueprint $table) {
            $table->foreignId('appointment_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fluidic_remedy_id')->constrained()->cascadeOnDelete();

            $table->primary(['appointment_record_id', 'fluidic_remedy_id']);
            $table->index('fluidic_remedy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_record_fluidic_remedy');
    }
};
