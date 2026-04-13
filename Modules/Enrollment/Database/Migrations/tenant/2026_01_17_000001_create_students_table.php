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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Matricule unique
            $table->string('matricule', 50)->unique();

            // Informations personnelles
            $table->string('firstname');
            $table->string('lastname');
            $table->date('birthdate');
            $table->string('birthplace')->nullable();
            $table->enum('sex', ['M', 'F', 'O']);
            $table->string('nationality')->default('Niger');

            // Contact
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20);

            // Adresse
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Niger');

            // Documents
            $table->string('photo')->nullable();

            // Statut
            $table->enum('status', ['Actif', 'Suspendu', 'Exclu', 'Diplômé'])->default('Actif');

            // Contact d'urgence
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('matricule');
            $table->index('email');
            $table->index('status');
            $table->index(['lastname', 'firstname']);
            $table->index('birthdate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
