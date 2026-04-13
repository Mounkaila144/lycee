<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'Validé' and 'Rejeté' statuses to the student_enrollments table
     * for the enrollment validation workflow.
     */
    public function up(): void
    {
        // Alter ENUM to add new statuses
        DB::statement("ALTER TABLE student_enrollments MODIFY COLUMN status ENUM('Actif', 'Suspendu', 'Annulé', 'Terminé', 'Validé', 'Rejeté') DEFAULT 'Actif'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any rows with new statuses back to 'Actif'
        DB::statement("UPDATE student_enrollments SET status = 'Actif' WHERE status IN ('Validé', 'Rejeté')");

        // Then revert the ENUM
        DB::statement("ALTER TABLE student_enrollments MODIFY COLUMN status ENUM('Actif', 'Suspendu', 'Annulé', 'Terminé') DEFAULT 'Actif'");
    }
};
