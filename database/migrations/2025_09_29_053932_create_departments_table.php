<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->string('department_code')->unique()->nullable();
            $table->unsignedBigInteger('college_id')->nullable(); // Will be foreign key later
            $table->unsignedBigInteger('department_head_id')->nullable(); // Will be foreign key later
            $table->text('description')->nullable();
            $table->string('office_location')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
            // NO FOREIGN KEYS HERE - add later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
