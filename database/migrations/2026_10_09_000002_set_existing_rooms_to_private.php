<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->update(['is_public' => 0]);
    }

    public function down(): void
    {
        // Previous visibility cannot be recovered; rollback must never make rooms public.
    }
};
