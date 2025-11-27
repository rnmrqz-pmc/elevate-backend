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
        Schema::dropIfExists('users_info');
        Schema::create('users_info', function (Blueprint $table) {
            $table->id('ID');
            $table->unsignedBigInteger('user_id')->unique()->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('nick_name', 100)->nullable();
            $table->string('position', 100);
            $table->string('department', 100);
            $table->string('branch_team', 100);
            $table->string('company_email', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('viber_number', 20)->nullable();
            $table->date('birth_date');
            $table->date('date_hired');
            $table->text('address')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Terminated', 'Resigned'])->default('Active');
            $table->timestamp('created_at')->useCurrent();
            $table->string('created_by', 45)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('updated_by', 45)->nullable();
            
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
        Schema::dropIfExists('users_info');
    }
};