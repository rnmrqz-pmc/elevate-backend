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
        Schema::dropIfExists('sys_login_attempt');
        Schema::create('sys_login_attempt', function (Blueprint $table) {
            $table->id('ID');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('username', 100);
            $table->string('ip_address', 20);
            $table->string('user_agent', 255);
            $table->tinyInteger('success')->default(0);
            $table->string('fail_reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at');
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
        Schema::dropIfExists('sys_login_attempt');
    }
};