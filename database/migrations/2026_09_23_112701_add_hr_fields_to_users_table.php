<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_role')) {
                $table->string('department_role')->nullable();
            }
            if (!Schema::hasColumn('users', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'branch')) {
                $table->string('branch')->nullable();
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'department_role',
                'manager_id',
                'branch',
                'is_active',
                'username'
            ]);
        });
    }
};
