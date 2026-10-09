<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'id'], 'reservation_status_submission_idx');
            $table->index('created_at', 'reservation_submission_idx');
            $table->index('reservation_date', 'reservation_date_idx');
        });
        Schema::create('reservation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('reservation_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_request_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->foreignId('recipient_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['reservation_request_id', 'event', 'recipient_email'], 'reservation_mail_event_recipient_unique');
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_mail_deliveries');
        Schema::dropIfExists('reservation_status_histories');
        Schema::table('reservation_requests', function (Blueprint $table) {
            $table->dropIndex('reservation_status_submission_idx');
            $table->dropIndex('reservation_submission_idx');
            $table->dropIndex('reservation_date_idx');
        });
    }
};
