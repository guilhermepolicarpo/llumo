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
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('loan_period_days')->default(14)->after('state');
            $table->unsignedTinyInteger('loan_max_renewals')->nullable()->after('loan_period_days');
            $table->unsignedTinyInteger('loan_max_active_per_person')->nullable()->after('loan_max_renewals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['loan_period_days', 'loan_max_renewals', 'loan_max_active_per_person']);
        });
    }
};
