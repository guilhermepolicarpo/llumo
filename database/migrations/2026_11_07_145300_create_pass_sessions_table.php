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
        Schema::create('pass_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_prescription_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->timestamps();

            $table->unique(['pass_prescription_id', 'scheduled_on']);
            $table->index('scheduled_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pass_sessions');
    }
};
