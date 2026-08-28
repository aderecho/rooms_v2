<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
            $table->foreignId('reservation_request_id')
                ->nullable()
                ->after('schedule_id')
                ->constrained('reservation_requests')
                ->cascadeOnDelete();
            $table->string('action_url')->nullable()->after('message');
            $table->index(['reservation_request_id', 'user_id'], 'reservation_notification_user_idx');
        });
    }

    public function down(): void
    {
        DB::table('schedule_notifications')
            ->whereNotNull('reservation_request_id')
            ->delete();

        Schema::table('schedule_notifications', function (Blueprint $table) {
            $table->dropIndex('reservation_notification_user_idx');
            $table->dropConstrainedForeignId('reservation_request_id');
            $table->dropColumn('action_url');
            $table->unsignedBigInteger('schedule_id')->nullable(false)->change();
        });
    }
};
