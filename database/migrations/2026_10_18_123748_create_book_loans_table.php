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
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_copy_id')->constrained()->restrictOnDelete();
            $table->foreignId('assisted_person_id')->constrained('assisted_people')->restrictOnDelete();
            $table->date('loaned_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->unsignedTinyInteger('renewals_count')->default(0);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'returned_on', 'due_on']);
            $table->index(['assisted_person_id', 'returned_on']);
            $table->index(['book_copy_id', 'returned_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_loans');
    }
};
