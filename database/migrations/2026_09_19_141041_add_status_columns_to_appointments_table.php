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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 20)->default('scheduled')->after('scheduled_on');
            $table->timestamp('received_at')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('received_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->foreignId('attendant_id')->nullable()->after('finished_at')->constrained('users')->nullOnDelete();

            $table->index(['team_id', 'scheduled_on', 'status']);
            $table->index(['status', 'scheduled_on']);
            $table->dropIndex(['team_id', 'scheduled_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['team_id', 'scheduled_on']);
            $table->dropIndex(['status', 'scheduled_on']);
            $table->dropIndex(['team_id', 'scheduled_on', 'status']);
            $table->dropConstrainedForeignId('attendant_id');
            $table->dropColumn(['status', 'received_at', 'started_at', 'finished_at']);
        });
    }
};
