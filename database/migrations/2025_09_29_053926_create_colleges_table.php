<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->string('college_name', 150)->unique();
            $table->string('college_code', 50)->unique()->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('dean_id')->nullable(); // Will be foreign key later
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
            // NO FOREIGN KEY HERE - add later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colleges');
    }
};
