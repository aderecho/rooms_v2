<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('purpose', 255);
            $table->unsignedInteger('attendees');
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('admin_response')->nullable();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'reservation_date', 'status'], 'reservation_room_date_status_idx');
            $table->index(['student_id', 'status'], 'reservation_student_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_requests');
    }
};
