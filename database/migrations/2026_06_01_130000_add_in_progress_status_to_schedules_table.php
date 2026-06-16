<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schedules')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE schedules MODIFY COLUMN status ENUM('pending', 'in_progress', 'approved', 'completed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('schedules')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE schedules MODIFY COLUMN status ENUM('approved', 'pending', 'cancelled', 'completed') NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
