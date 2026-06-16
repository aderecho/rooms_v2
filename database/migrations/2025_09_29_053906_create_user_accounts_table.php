<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('employee_id')->unique()->nullable();
            $table->string('profile_picture')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('college_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('user_type', ['admin', 'faculty', 'staff', 'student', 'guest'])->default('faculty');
            $table->json('roles')->nullable();
            $table->enum('account_status', ['active', 'inactive', 'suspended', 'pending'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            // NO FOREIGN KEYS HERE - add them later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accounts');
    }
};
