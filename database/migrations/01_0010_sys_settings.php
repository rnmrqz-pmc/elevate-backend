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
        Schema::dropIfExists('sys_setting');
        Schema::create('sys_setting', function (Blueprint $table) {
            $table->id('ID');
            $table->integer('max_login_attempts')->default(5);
            $table->integer('lockout_duration')->default(30);
            $table->integer('otp_resend_interval')->default(300);
            $table->integer('otp_validity')->default(10);
            $table->integer('reset_token_validity')->default(30);
            $table->timestamp('created_at')->useCurrent();            
        });


        DB::table('sys_setting')->insert([
            'max_login_attempts' => 5,
            'lockout_duration' => 30,
            'otp_resend_interval' => 300,
            'otp_validity' => 10,
            'reset_token_validity' => 30
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_setting');
    }
};