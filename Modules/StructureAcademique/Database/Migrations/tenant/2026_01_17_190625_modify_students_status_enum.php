<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Modify the status enum to include 'Abandon' and 'Transféré'
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('Actif', 'Suspendu', 'Exclu', 'Diplômé', 'Abandon', 'Transféré') DEFAULT 'Actif'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('Actif', 'Suspendu', 'Exclu', 'Diplômé') DEFAULT 'Actif'");
    }
};
