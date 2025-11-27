<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    public function up(): void
    {
        // Create users table
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id('ID');
            $table->unsignedBigInteger('role_id')->nullable();

            $table->string('username', 100)->unique();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->tinyInteger('profile_complete')->default(0);
            $table->tinyInteger('agree_terms')->default(0);
            $table->timestamp('last_login')->nullable();
            $table->tinyInteger('with_2fa')->default(1);
            $table->tinyInteger('invalid_attempts')->default(0);
            $table->tinyInteger('is_locked')->default(0);
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('unlocked_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->string('created_by', 45)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('updated_by', 45)->nullable();
            
            $table->index('email', 'idx_email');
            $table->foreign('role_id')
                  ->references('ID')
                  ->on('user_roles')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });

          DB::table('users')->insert([
            ['role_id' => 1, 
            'username' => 'ron',
            'email' => 'ron@gmail.com',
            'password' => '$2y$12$Nrq97fXengFcF9bgPyZ4O.Q65MXmTHc2nimP/9Jg6TIGm8.uuZiuy',
            ],

        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};