<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create user_roles table
        Schema::dropIfExists('user_roles');
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id('ID');
            $table->string('role', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Insert default roles
        DB::table('user_roles')->insert([
            ['role' => 'Admin', 'description' => 'System administrator with full access'],
            ['role' => 'Trainer', 'description' => 'Training facilitator and content manager'],
            ['role' => 'Leader', 'description' => 'Team leader with monitoring capabilities'],
            ['role' => 'Trainee', 'description' => 'Training participant'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};