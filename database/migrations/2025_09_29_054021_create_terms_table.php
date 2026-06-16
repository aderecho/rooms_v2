<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('term_name', 100)->unique();
            $table->string('term_code', 50)->unique();
            $table->enum('term_type', ['semester', 'trimester', 'quarter', 'summer', 'special'])->default('semester');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('enrollment_start')->nullable();
            $table->date('enrollment_end')->nullable();
            $table->date('classes_start');
            $table->date('classes_end');
            $table->date('examination_start')->nullable();
            $table->date('examination_end')->nullable();
            $table->boolean('is_current')->default(false);
            $table->enum('status', ['upcoming', 'active', 'completed', 'cancelled'])->default('upcoming');
            $table->integer('academic_year');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
