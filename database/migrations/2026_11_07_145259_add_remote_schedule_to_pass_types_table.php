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
        Schema::table('pass_types', function (Blueprint $table) {
            $table->json('remote_weekdays')->nullable()->after('name');
            $table->time('remote_starts_at')->nullable()->after('remote_weekdays');
            $table->time('remote_ends_at')->nullable()->after('remote_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_types', function (Blueprint $table) {
            $table->dropColumn(['remote_weekdays', 'remote_starts_at', 'remote_ends_at']);
        });
    }
};
