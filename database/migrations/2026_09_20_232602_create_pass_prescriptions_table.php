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
        Schema::create('pass_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('mode', 20);
            $table->timestamps();

            $table->index('appointment_record_id');
            $table->index('pass_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pass_prescriptions');
    }
};
