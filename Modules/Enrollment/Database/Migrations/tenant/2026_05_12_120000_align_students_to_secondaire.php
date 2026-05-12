<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le schéma `students` au PRD Secondaire (Story 7.1).
 *
 * - Matricule devient nullable (Story 7.2 le remplira en finalisation Étape 3).
 * - Retire email, mobile, country (concepts LMD / universitaires).
 * - Ajoute city (default Niamey), quarter, blood_group, health_notes.
 * - Modifie sex enum → ('M','F') (retire 'O').
 * - Modifie status enum → ('Actif','Transféré','Exclu','Diplômé','Redoublant').
 * - nationality default → 'Nigérienne'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('matricule', 50)->nullable()->change();
            $table->string('nationality')->default('Nigérienne')->change();
        });

        $hasMobile = Schema::hasColumn('students', 'mobile');
        $hasEmail = Schema::hasColumn('students', 'email');
        $hasCountry = Schema::hasColumn('students', 'country');

        if ($hasEmail) {
            try {
                Schema::table('students', function (Blueprint $table) {
                    $table->dropUnique(['email']);
                });
            } catch (\Throwable $e) {
                // Index may not exist on test DBs — ignore.
            }
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        if ($hasMobile) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('mobile');
            });
        }

        if ($hasCountry) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('country');
            });
        }

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'quarter')) {
                $table->string('quarter')->nullable()->after('city');
            }
            if (! Schema::hasColumn('students', 'blood_group')) {
                $table->string('blood_group', 10)->nullable()->after('quarter');
            }
            if (! Schema::hasColumn('students', 'health_notes')) {
                $table->text('health_notes')->nullable()->after('blood_group');
            }
        });

        // city default 'Niamey'
        Schema::table('students', function (Blueprint $table) {
            $table->string('city')->nullable()->default('Niamey')->change();
        });

        // ENUM modifications (raw SQL — Doctrine ne gère pas les enums MySQL nativement)
        DB::statement("ALTER TABLE students MODIFY COLUMN sex ENUM('M','F') NOT NULL");
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('Actif','Transféré','Exclu','Diplômé','Redoublant','Suspendu','Abandon') DEFAULT 'Actif'");
    }

    public function down(): void
    {
        // Down volontairement minimal : on ne restaure pas le schéma LMD.
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'health_notes')) {
                $table->dropColumn('health_notes');
            }
            if (Schema::hasColumn('students', 'blood_group')) {
                $table->dropColumn('blood_group');
            }
            if (Schema::hasColumn('students', 'quarter')) {
                $table->dropColumn('quarter');
            }
        });
    }
};
