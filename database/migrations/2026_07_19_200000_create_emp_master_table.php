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
        Schema::create('emp_master', function (Blueprint $table) {
            $table->id();
            
            // Personal Info
            $table->string('employee_no')->unique();
            $table->string('hti_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('fullname')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('sex')->nullable(); // Male, Female
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable(); // Single, Married, etc.
            $table->string('nationality')->default('Indonesian');
            
            // Address & Contact
            $table->text('permanent_address')->nullable();
            $table->string('permanent_city')->nullable();
            $table->text('correspondence_address')->nullable();
            $table->string('correspondence_city')->nullable();
            $table->string('telp_no')->nullable();
            $table->string('handphone')->nullable();
            $table->string('email')->nullable();
            
            // Identifiers & Tax
            $table->string('ktp_no')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('npwp_no')->nullable();
            $table->string('jamsostek_no')->nullable();
            $table->string('tax_status')->nullable(); // TK/0, K/0, etc.
            
            // Professional Info
            $table->string('division')->nullable();
            $table->string('employee_status')->nullable(); // Permanent, Contract, etc.
            $table->date('rehired_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('temp_ext')->nullable();
            $table->string('status')->default('Active'); // Active, Resigned, etc.
            $table->boolean('is_freelance')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_master');
    }
};
