<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create users_info table
        Schema::dropIfExists('sys_password_reset_token');
        Schema::create('sys_password_reset_token', function (Blueprint $table) {
            $table->id('ID');
            $table->unsignedBigInteger('user_id')->unique()->nullable();

            $table->string('token', 255);
            $table->timestamp('expires_at');
            $table->tinyInteger('used')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->string('created_by', 45)->nullable();

            $table->foreign('user_id')
                  ->references('ID')
                  ->on('users')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_password_reset_token');
    }
};