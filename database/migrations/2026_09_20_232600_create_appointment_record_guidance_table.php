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
        Schema::create('appointment_record_guidance', function (Blueprint $table) {
            $table->foreignId('appointment_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guidance_id')->constrained()->cascadeOnDelete();
            $table->string('detail', 100)->nullable();

            $table->primary(['appointment_record_id', 'guidance_id']);
            $table->index('guidance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_record_guidance');
    }
};
