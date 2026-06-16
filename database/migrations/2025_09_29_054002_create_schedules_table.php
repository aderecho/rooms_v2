<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id'); // Will be foreign key later
            $table->string('event_title');
            $table->enum('event_type', ['class', 'meeting', 'event', 'other'])->default('class');
            $table->string('course_code')->nullable();
            $table->string('course_name')->nullable();
            $table->string('section')->nullable();
            $table->string('faculty_name')->nullable();
            $table->unsignedBigInteger('faculty_id')->nullable(); // Will be foreign key later
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('day_of_week', 20);
            $table->integer('number_of_participants')->nullable();
            $table->unsignedBigInteger('requester_id')->nullable(); // Will be foreign key later
            $table->string('requester_name')->nullable();
            $table->text('description')->nullable();
            $table->string('agenda')->nullable();
            $table->string('organizer')->nullable();
            $table->json('equipment_needed')->nullable();
            $table->json('additional_requirements')->nullable();
            $table->enum('status', ['approved', 'pending', 'cancelled', 'completed'])->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable();
            $table->unsignedBigInteger('term_id')->nullable(); // Will be foreign key later
            $table->string('cfic_id', 100)->nullable();
            $table->timestamps();
            // NO FOREIGN KEYS HERE - add later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
