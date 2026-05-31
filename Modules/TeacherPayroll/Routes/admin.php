<?php

use Illuminate\Support\Facades\Route;
use Modules\TeacherPayroll\Http\Controllers\Admin\AffectationController;
use Modules\TeacherPayroll\Http\Controllers\Admin\AjustementController;
use Modules\TeacherPayroll\Http\Controllers\Admin\ContratController;
use Modules\TeacherPayroll\Http\Controllers\Admin\DashboardController;
use Modules\TeacherPayroll\Http\Controllers\Admin\HeureCoursController;
use Modules\TeacherPayroll\Http\Controllers\Admin\HistoriqueController;
use Modules\TeacherPayroll\Http\Controllers\Admin\PaieController;
use Modules\TeacherPayroll\Http\Controllers\Admin\PeriodePaieController;
use Modules\TeacherPayroll\Http\Controllers\Admin\ProfesseurController;

/*
| Paie des professeurs.
| Accès : Administrator (ADMIN), Manager (DIRECTEUR), Comptable (COMPTABLE).
| Le binding manuel par `int $id` est requis (SubstituteBindings s'exécute
| avant l'init de tenancy).
*/
Route::prefix('admin/teacher-payroll')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Comptable,tenant'])
    ->group(function () {
        // Tableau de bord
        Route::get('stats', [DashboardController::class, 'stats']);

        // Professeurs
        Route::get('professeurs', [ProfesseurController::class, 'index']);
        Route::get('professeurs/candidats-utilisateurs', [ProfesseurController::class, 'candidatsUtilisateurs']);
        Route::post('professeurs/importer', [ProfesseurController::class, 'importerUtilisateur']);
        Route::post('professeurs/importer-tous', [ProfesseurController::class, 'importerTous']);
        Route::post('professeurs', [ProfesseurController::class, 'store']);
        Route::get('professeurs/{id}', [ProfesseurController::class, 'show']);
        Route::put('professeurs/{id}', [ProfesseurController::class, 'update']);
        Route::delete('professeurs/{id}', [ProfesseurController::class, 'destroy']);
        Route::post('professeurs/{id}/toggle-actif', [ProfesseurController::class, 'toggleActif']);

        // Contrats (configuration du mode de paiement)
        Route::get('contrats', [ContratController::class, 'index']);
        Route::post('professeurs/{professeurId}/contrats', [ContratController::class, 'store']);
        Route::put('contrats/{id}', [ContratController::class, 'update']);
        Route::post('contrats/{id}/activer', [ContratController::class, 'activate']);
        Route::post('contrats/{id}/terminer', [ContratController::class, 'terminate']);

        // Affectations professeur-classe-matière
        Route::get('affectations', [AffectationController::class, 'index']);
        Route::post('affectations', [AffectationController::class, 'store']);
        Route::put('affectations/{id}', [AffectationController::class, 'update']);
        Route::delete('affectations/{id}', [AffectationController::class, 'destroy']);

        // Heures de cours + validation
        Route::get('heures', [HeureCoursController::class, 'index']);
        Route::post('heures', [HeureCoursController::class, 'store']);
        Route::put('heures/{id}', [HeureCoursController::class, 'update']);
        Route::delete('heures/{id}', [HeureCoursController::class, 'destroy']);
        Route::post('heures/valider', [HeureCoursController::class, 'valider']);
        Route::post('heures/rejeter', [HeureCoursController::class, 'rejeter']);
        Route::post('heures/{id}/remplacer', [HeureCoursController::class, 'marquerRemplace']);

        // Primes
        Route::get('primes', [AjustementController::class, 'indexPrimes']);
        Route::post('primes', [AjustementController::class, 'storePrime']);
        Route::delete('primes/{id}', [AjustementController::class, 'destroyPrime']);

        // Retenues
        Route::get('retenues', [AjustementController::class, 'indexRetenues']);
        Route::post('retenues', [AjustementController::class, 'storeRetenue']);
        Route::delete('retenues/{id}', [AjustementController::class, 'destroyRetenue']);

        // Avances
        Route::get('avances', [AjustementController::class, 'indexAvances']);
        Route::post('avances', [AjustementController::class, 'storeAvance']);
        Route::post('avances/{id}/approuver', [AjustementController::class, 'approuverAvance']);
        Route::post('avances/{id}/annuler', [AjustementController::class, 'annulerAvance']);

        // Absences
        Route::get('absences', [AjustementController::class, 'indexAbsences']);
        Route::post('absences', [AjustementController::class, 'storeAbsence']);
        Route::delete('absences/{id}', [AjustementController::class, 'destroyAbsence']);

        // Périodes de paie
        Route::get('periodes', [PeriodePaieController::class, 'index']);
        Route::post('periodes', [PeriodePaieController::class, 'store']);
        Route::get('periodes/{id}', [PeriodePaieController::class, 'show']);
        Route::put('periodes/{id}', [PeriodePaieController::class, 'update']);
        Route::post('periodes/{id}/calculer', [PeriodePaieController::class, 'calculer']);
        Route::post('periodes/{id}/valider', [PeriodePaieController::class, 'valider']);
        Route::post('periodes/{id}/marquer-payee', [PeriodePaieController::class, 'marquerPayee']);
        Route::post('periodes/{id}/cloturer', [PeriodePaieController::class, 'cloturer']);

        // Paies / fiches de paie
        Route::get('paies', [PaieController::class, 'index']);
        Route::get('paies/{id}', [PaieController::class, 'show']);
        Route::post('paies/{id}/recalculer', [PaieController::class, 'recalculer']);
        Route::post('paies/{id}/valider', [PaieController::class, 'valider']);
        Route::post('paies/{id}/annuler', [PaieController::class, 'annuler']);
        Route::post('paies/{id}/paiement', [PaieController::class, 'enregistrerPaiement']);
        Route::get('paies/{id}/fiche-pdf', [PaieController::class, 'fichePaie']);

        // Journalisation
        Route::get('historique', [HistoriqueController::class, 'index']);
    });
