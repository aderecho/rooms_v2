<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_name', 100);
            $table->string('room_code', 50)->unique();
            $table->unsignedBigInteger('building_id')->nullable();
            $table->unsignedBigInteger('college_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('room_type_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->integer('floor_number')->nullable();
            $table->string('location', 255)->nullable();
            $table->integer('capacity')->default(30);
            $table->integer('area_sqm')->nullable();
            $table->json('facilities')->nullable();
            $table->json('equipments')->nullable(); // Add equipments field
            $table->text('description')->nullable(); // Add description field
            $table->enum('status', ['available', 'occupied', 'maintenance', 'closed'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
