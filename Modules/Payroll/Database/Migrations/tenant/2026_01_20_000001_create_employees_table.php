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
        Schema::connection('tenant')->create('employees', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('employee_code', 50)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('cin', 20)->nullable()->comment('Carte d\'identité nationale');
            $table->string('cnss_number', 50)->nullable()->comment('Numéro CNSS');

            // Personal Information
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->integer('number_of_dependents')->default(0);

            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Employment Information
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('job_title', 100)->nullable();

            // Banking Information
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('rib', 24)->nullable()->comment('Relevé d\'Identité Bancaire');

            // Tax Information
            $table->string('tax_id', 50)->nullable()->comment('Identifiant fiscal');
            $table->enum('tax_residence', ['morocco', 'foreign'])->default('morocco');

            // Status
            $table->enum('status', ['active', 'inactive', 'suspended', 'terminated'])->default('active');
            $table->text('termination_reason')->nullable();

            // Profile Picture
            $table->string('profile_picture')->nullable();

            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_code');
            $table->index('email');
            $table->index('department');
            $table->index('status');
            $table->index('hire_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('employees');
    }
};
