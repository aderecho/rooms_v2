<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add foreign keys to user_accounts
        Schema::table('user_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_accounts', 'college_id')) {
                $table->unsignedBigInteger('college_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('user_accounts', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('college_id');
            }

            $table->foreign('college_id')->references('id')->on('colleges')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });

        // 2. Add foreign keys to colleges
        Schema::table('colleges', function (Blueprint $table) {
            if (!Schema::hasColumn('colleges', 'dean_id')) {
                $table->unsignedBigInteger('dean_id')->nullable()->after('id');
            }
            $table->foreign('dean_id')->references('id')->on('user_accounts')->onDelete('set null');
        });

        // 3. Add foreign keys to departments
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'college_id')) {
                $table->unsignedBigInteger('college_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('departments', 'department_head_id')) {
                $table->unsignedBigInteger('department_head_id')->nullable()->after('college_id');
            }

            $table->foreign('college_id')->references('id')->on('colleges')->onDelete('cascade');
            $table->foreign('department_head_id')->references('id')->on('user_accounts')->onDelete('set null');
        });

        // 4. Add foreign keys to buildings
        Schema::table('buildings', function (Blueprint $table) {
            if (!Schema::hasColumn('buildings', 'college_id')) {
                $table->unsignedBigInteger('college_id')->nullable()->after('id');
            }
            $table->foreign('college_id')->references('id')->on('colleges')->onDelete('set null');
        });

        // 5. Add foreign keys to rooms
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'building_id')) {
                $table->unsignedBigInteger('building_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('rooms', 'college_id')) {
                $table->unsignedBigInteger('college_id')->nullable()->after('building_id');
            }
            if (!Schema::hasColumn('rooms', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('college_id');
            }
            if (!Schema::hasColumn('rooms', 'room_type_id')) {
                $table->unsignedBigInteger('room_type_id')->nullable()->after('department_id');
            }
            if (!Schema::hasColumn('rooms', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('room_type_id');
            }

            $table->foreign('building_id')->references('id')->on('buildings')->onDelete('set null');
            $table->foreign('college_id')->references('id')->on('colleges')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on('user_accounts')->onDelete('set null');
        });

        // 6. Add foreign keys to equipment
        Schema::table('equipment', function (Blueprint $table) {
            if (!Schema::hasColumn('equipment', 'room_id')) {
                $table->unsignedBigInteger('room_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('equipment', 'building_id')) {
                $table->unsignedBigInteger('building_id')->nullable()->after('room_id');
            }
            if (!Schema::hasColumn('equipment', 'college_id')) {
                $table->unsignedBigInteger('college_id')->nullable()->after('building_id');
            }
            if (!Schema::hasColumn('equipment', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('college_id');
            }
            if (!Schema::hasColumn('equipment', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('department_id');
            }

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
            $table->foreign('building_id')->references('id')->on('buildings')->onDelete('set null');
            $table->foreign('college_id')->references('id')->on('colleges')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on('user_accounts')->onDelete('set null');
        });

        // 7. Add foreign keys to schedules
        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'room_id')) {
                $table->unsignedBigInteger('room_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('schedules', 'faculty_id')) {
                $table->unsignedBigInteger('faculty_id')->nullable()->after('room_id');
            }
            if (!Schema::hasColumn('schedules', 'requester_id')) {
                $table->unsignedBigInteger('requester_id')->nullable()->after('faculty_id');
            }
            if (!Schema::hasColumn('schedules', 'term_id')) {
                $table->unsignedBigInteger('term_id')->nullable()->after('requester_id');
            }

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('faculty_id')->references('id')->on('user_accounts')->onDelete('set null');
            $table->foreign('requester_id')->references('id')->on('user_accounts')->onDelete('set null');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop all foreign keys in reverse order
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['faculty_id']);
            $table->dropForeign(['requester_id']);
            $table->dropForeign(['term_id']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['building_id']);
            $table->dropForeign(['college_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['assigned_user_id']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
            $table->dropForeign(['college_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['room_type_id']);
            $table->dropForeign(['assigned_user_id']);
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropForeign(['department_head_id']);
        });

        Schema::table('colleges', function (Blueprint $table) {
            $table->dropForeign(['dean_id']);
        });

        Schema::table('user_accounts', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropForeign(['department_id']);
        });
    }
};
