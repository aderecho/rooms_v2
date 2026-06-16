<?php
// database/migrations/xxxx_xx_xx_create_equipment_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_name', 100);
            $table->string('inventory_id', 50)->unique();
            $table->string('property_id', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('building_id')->nullable();
            $table->unsignedBigInteger('college_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('cfic_id', 100)->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'damaged', 'retired'])->default('available');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->json('specifications')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys will be added in separate migration
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
