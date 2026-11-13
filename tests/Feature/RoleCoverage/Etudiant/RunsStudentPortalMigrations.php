<?php

namespace Tests\Feature\RoleCoverage\Etudiant;

use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithTenancy;

/**
 * Migrations partagées + tables lues par le portail Élève
 * (notes, emploi du temps, présences, documents).
 */
trait RunsStudentPortalMigrations
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $studentPortalMigrationsRan = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$migrationsRan = false;
        self::$testTenant = null;
        self::$studentPortalMigrationsRan = false;
    }

    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$studentPortalMigrationsRan) {
            return;
        }

        foreach ([
            'Modules/NotesEvaluations/Database/Migrations/tenant',
            'Modules/Timetable/Database/Migrations/tenant',
            'Modules/Attendance/Database/Migrations/tenant',
            'Modules/Documents/Database/Migrations/tenant',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$studentPortalMigrationsRan = true;
    }
}
