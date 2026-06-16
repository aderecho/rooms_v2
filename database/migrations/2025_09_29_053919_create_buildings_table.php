<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('building_name');
            $table->string('address');
            $table->text('description')->nullable();
            $table->integer('total_floors')->nullable();
            $table->integer('total_rooms')->nullable();
            $table->boolean('has_elevator')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->integer('restroom_count')->nullable();
            $table->integer('ramp_count')->nullable();
            $table->unsignedBigInteger('college_id')->nullable(); // Will be foreign key later
            $table->timestamps();
            // NO FOREIGN KEY HERE - add later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
