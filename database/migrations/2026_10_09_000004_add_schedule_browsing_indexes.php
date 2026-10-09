<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['date', 'start_time', 'id'], 'schedules_browse_idx');
            $table->index(['room_id', 'date', 'status'], 'schedules_room_conflict_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_browse_idx');
            $table->dropIndex('schedules_room_conflict_idx');
        });
    }
};
