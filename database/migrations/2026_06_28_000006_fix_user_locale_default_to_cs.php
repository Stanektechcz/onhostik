<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* Fix users whose locale was auto-set to 'en' by the old APP_LOCALE default.
           Set them to 'cs' unless they explicitly requested 'en' via the locale switcher.
           Since the switcher wasn't visible/working before, all 'en' locales are defaults. */
        DB::table('users')
            ->where('locale', 'en')
            ->orWhereNull('locale')
            ->update(['locale' => 'cs']);
    }

    public function down(): void {}
};
