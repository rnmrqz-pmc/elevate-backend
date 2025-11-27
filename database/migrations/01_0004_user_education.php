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
        Schema::dropIfExists('user_education');
        Schema::create('user_education', function (Blueprint $table) {
            $table->id('ID');
            $table->unsignedBigInteger('user_id')->unique()->nullable();
            $table->string('school', 255);
            $table->string('degree', 255);
            $table->string('course', 255)->nullable();
            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();

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
        Schema::dropIfExists('user_education');
    }
};