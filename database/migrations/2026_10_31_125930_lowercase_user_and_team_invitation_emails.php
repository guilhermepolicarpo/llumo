<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Emails are now stored in lowercase, so existing rows are normalized to match.
     */
    public function up(): void
    {
        foreach (['users', 'team_invitations'] as $table) {
            DB::table($table)
                ->whereRaw('email <> LOWER(email)')
                ->update(['email' => DB::raw('LOWER(email)')]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The original casing is not kept, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
