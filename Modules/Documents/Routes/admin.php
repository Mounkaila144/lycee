<?php

use Illuminate\Support\Facades\Route;
use Modules\Documents\Http\Controllers\Admin\CertificateController;
use Modules\Documents\Http\Controllers\Admin\DiplomaController;
use Modules\Documents\Http\Controllers\Admin\DocumentController;
use Modules\Documents\Http\Controllers\Admin\TranscriptController;
use Modules\Documents\Http\Controllers\Admin\VerificationController;

// Consultation / téléchargement des documents émis : staff + Élève (ses documents)
// + Parent (documents de ses enfants). Ownership appliqué par DocumentPolicy.
Route::prefix('admin/documents')->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Élève|Parent,tenant'])
    ->controller(DocumentController::class)
    ->group(function () {
        Route::get('/students/{student}', 'studentDocuments')->whereNumber('student');
        Route::get('/files/{document}/download', 'download')->whereNumber('document');
    });

// RBAC durcissement (Stories Admin 10, Manager 08) : Admin, Manager et autres rôles
// finance accèdent (les Profs/Étudiants ont leurs propres endpoints frontend).
Route::prefix('admin/documents')->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager,tenant'])->group(function () {

    // Epic 1: Transcripts (Stories 01-05)
    Route::prefix('transcripts')->controller(TranscriptController::class)->group(function () {
        Route::post('/semester', 'generateSemesterTranscript'); // Story 01, 02, 04
        Route::post('/global', 'generateGlobalTranscript'); // Story 03
        Route::post('/batch', 'batchGenerateTranscripts'); // Story 05
    });

    // Epic 2: Diplomas (Stories 06-10)
    Route::prefix('diplomas')->controller(DiplomaController::class)->group(function () {
        Route::get('/', 'index'); // Story 07 - Diploma register
        Route::post('/', 'generateDiploma'); // Story 06, 08
        Route::post('/{diploma}/duplicate', 'generateDuplicate'); // Story 09
        Route::post('/{diploma}/supplement', 'generateSupplement'); // Story 10
        Route::patch('/{diploma}/deliver', 'markAsDelivered');
    });

    // Epic 3: Certificates (Stories 11-18)
    Route::prefix('certificates')->controller(CertificateController::class)->group(function () {
        // Certificate generation
        Route::post('/enrollment', 'generateEnrollmentCertificate'); // Story 11
        Route::post('/status', 'generateStatusCertificate'); // Story 12
        Route::post('/achievement', 'generateAchievementCertificate'); // Story 13
        Route::post('/attendance', 'generateAttendanceCertificate'); // Story 14
        Route::post('/schooling', 'generateSchoolingCertificate'); // Story 17
        Route::post('/transfer', 'generateTransferCertificate'); // Story 18

        // Certificate requests workflow (Stories 15-16)
        Route::get('/requests', 'listRequests'); // Story 16
        Route::post('/requests', 'createRequest'); // Story 15
        Route::post('/requests/{request}/approve', 'approveRequest'); // Story 16
        Route::post('/requests/{request}/reject', 'rejectRequest'); // Story 16
    });

    // Epic 4 (cartes) : les cartes scolaires sont gérées par le module Enrollment
    // (admin/enrollment/student-cards). L'ancien CardController (badges / table student_cards
    // au schéma universitaire) a été retiré car il faisait doublon et provoquait des erreurs SQL.

    // Epic 5: Verification & Archive (Stories 21-24)
    Route::prefix('verification')->controller(VerificationController::class)->group(function () {
        // Document verification (Story 21)
        Route::post('/qr-code', 'verifyByQrCode');
        Route::post('/document-number', 'verifyByDocumentNumber');
        Route::get('/statistics', 'getVerificationStatistics');

        // Document register (Story 22)
        Route::get('/register', 'getDocumentRegister');
        Route::get('/documents/{document}', 'show');

        // Document archiving (Story 23)
        Route::post('/documents/{document}/archive', 'archiveDocument');
        Route::get('/archives', 'listArchives');
        Route::get('/archives/{archive}/verify-integrity', 'verifyArchiveIntegrity');
        Route::post('/archives/{archive}/cold-storage', 'moveToColdStorage');

        // Electronic signatures (Story 24)
        Route::post('/documents/{document}/signature', 'addElectronicSignature');
        Route::get('/signatures', 'listElectronicSignatures');
        Route::get('/signatures/{signature}/verify', 'verifyElectronicSignature');
        Route::post('/signatures/{signature}/invalidate', 'invalidateElectronicSignature');

        // Document cancellation
        Route::post('/documents/{document}/cancel', 'cancelDocument');
    });
});
