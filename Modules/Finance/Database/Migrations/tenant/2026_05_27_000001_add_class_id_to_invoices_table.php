<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp the class on each invoice so payments can be tracked per class (not
 * only per academic year). A student who changes class re-pays for the new
 * class via a new invoice carrying that class_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'class_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('class_id')->nullable()->after('student_id')
                    ->constrained('classes')->nullOnDelete();
            });
        }

        // Backfill existing invoices from the student's current class.
        if (Schema::hasColumn('students', 'class_id')) {
            DB::connection('tenant')->statement(
                'UPDATE invoices i JOIN students s ON s.id = i.student_id
                 SET i.class_id = s.class_id WHERE i.class_id IS NULL AND s.class_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'class_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('class_id');
            });
        }
    }
};
