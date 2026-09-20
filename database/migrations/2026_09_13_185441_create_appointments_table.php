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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('assisted_person_id')->constrained('assisted_people')->cascadeOnDelete();
            $table->string('mode', 20);
            $table->date('scheduled_on');
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('attendant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'scheduled_on', 'status']);
            $table->index(['status', 'scheduled_on']);
            $table->index('appointment_type_id');
            $table->index('assisted_person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
