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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_collection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->char('isbn', 13)->nullable();
            $table->unsignedSmallInteger('collection_volume')->nullable();
            $table->string('edition', 50)->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'title']);
            $table->index(['team_id', 'isbn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
