<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien Étudiant ↔ TenantUser (Stories Étudiant 01-08).
 *
 * Permet à un élève connecté de retrouver SON profil Student via auth()->user()->student.
 * Le user_id est nullable car les élèves mineurs n'ont pas tous un compte portail
 * (accès parfois via le portail parent seulement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index('user_id', 'students_user_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex('students_user_id_idx');
                $table->dropColumn('user_id');
            }
        });
    }
};
