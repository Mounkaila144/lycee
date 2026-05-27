<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links each student to its class (secondary school: a student belongs to one
 * class such as "6ème A"). The column was previously added ad-hoc on existing
 * tenants but never captured as a migration, so fresh tenants lacked it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('students', 'class_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('id')->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'class_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
