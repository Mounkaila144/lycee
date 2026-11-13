<?php

namespace Modules\Documents\Services;

use Modules\Documents\Entities\DocumentTemplate;

/**
 * Built-in fallback templates used when a tenant has not configured
 * its own active DocumentTemplate for a given document type.
 */
class DefaultDocumentTemplates
{
    /**
     * @var array<string, array{name: string, title: string, body: string}>
     */
    private const DEFINITIONS = [
        'certificate_enrollment' => [
            'name' => 'Certificat d\'inscription (défaut)',
            'title' => 'CERTIFICAT D\'INSCRIPTION',
            'body' => '<p>Le Proviseur certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}} à {{student_birthplace}}, est régulièrement inscrit(e) dans notre établissement au titre de l\'année scolaire <strong>{{academic_year}}</strong>, en classe de <strong>{{level}}</strong>.</p><p>Le présent certificat est délivré pour servir et valoir ce que de droit. Motif : {{purpose}}.</p>',
        ],
        'certificate_status' => [
            'name' => 'Attestation de statut (défaut)',
            'title' => 'ATTESTATION DE STATUT',
            'body' => '<p>Le Proviseur atteste que <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}}, a le statut suivant : <strong>{{status}}</strong> pour l\'année scolaire <strong>{{academic_year}}</strong>.</p><p>Motif : {{purpose}}.</p>',
        ],
        'certificate_achievement' => [
            'name' => 'Attestation de réussite (défaut)',
            'title' => 'ATTESTATION DE RÉUSSITE',
            'body' => '<p>Le Proviseur atteste que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, a obtenu au cours de l\'année scolaire {{academic_year}} : <strong>{{achievement_title}}</strong> ({{achievement_date}}).</p><p>{{achievement_description}}</p>',
        ],
        'certificate_attendance' => [
            'name' => 'Attestation d\'assiduité (défaut)',
            'title' => 'ATTESTATION D\'ASSIDUITÉ',
            'body' => '<p>Le Proviseur atteste que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, a suivi les cours de l\'année scolaire <strong>{{academic_year}}</strong> avec un taux de présence de <strong>{{attendance_rate}} %</strong> ({{attended_sessions}} séances sur {{total_sessions}}).</p><p>Motif : {{purpose}}.</p>',
        ],
        'certificate_schooling' => [
            'name' => 'Certificat de scolarité (défaut)',
            'title' => 'CERTIFICAT DE SCOLARITÉ',
            'body' => '<p>Le Proviseur certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}} à {{student_birthplace}}, suit régulièrement les cours dans notre établissement au titre de l\'année scolaire <strong>{{current_academic_year}}</strong>, en classe de <strong>{{level}}</strong>.</p><p>Le présent certificat est délivré pour servir et valoir ce que de droit. Motif : {{purpose}}.</p>',
        ],
        'certificate_transfer' => [
            'name' => 'Certificat de transfert / exeat (défaut)',
            'title' => 'CERTIFICAT DE TRANSFERT (EXEAT)',
            'body' => '<p>Le Proviseur certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}}, inscrit(e) en classe de <strong>{{level}}</strong> au titre de l\'année scolaire {{current_academic_year}}, est autorisé(e) à quitter l\'établissement.</p><p>Établissement de destination : <strong>{{transfer_destination}}</strong>.<br>Motif du transfert : {{transfer_reason}}.</p><p>L\'intéressé(e) est libre de tout engagement envers l\'établissement.</p>',
        ],
        'transcript_semester' => [
            'name' => 'Relevé de notes (défaut)',
            'title' => 'RELEVÉ DE NOTES',
            'body' => '<p>Élève : <strong>{{student_name}}</strong> — Matricule : <strong>{{student_matricule}}</strong></p><p>Période : {{semester_name}} — Année scolaire : {{academic_year}}</p><p>Moyenne générale : <strong>{{average}}</strong></p>',
        ],
        'transcript_provisional' => [
            'name' => 'Relevé de notes provisoire (défaut)',
            'title' => 'RELEVÉ DE NOTES PROVISOIRE',
            'body' => '<p>Élève : <strong>{{student_name}}</strong> — Matricule : <strong>{{student_matricule}}</strong></p><p>Période : {{semester_name}} — Année scolaire : {{academic_year}}</p><p>Moyenne générale : <strong>{{average}}</strong></p>',
        ],
        'transcript_global' => [
            'name' => 'Relevé de notes global (défaut)',
            'title' => 'RELEVÉ DE NOTES GLOBAL',
            'body' => '<p>Élève : <strong>{{student_name}}</strong> — Matricule : <strong>{{student_matricule}}</strong></p><p>Moyenne cumulée : <strong>{{cumulative_gpa}}</strong></p>',
        ],
    ];

    /**
     * Return the active template for the type, creating the built-in default
     * when none has been configured. Returns null for unsupported types.
     */
    public static function resolve(string $type): ?DocumentTemplate
    {
        $template = DocumentTemplate::active()->ofType($type)->first();

        if ($template) {
            return $template;
        }

        $definition = self::DEFINITIONS[$type] ?? null;

        if (! $definition) {
            return null;
        }

        return DocumentTemplate::create([
            'type' => $type,
            'name' => $definition['name'],
            'description' => 'Modèle par défaut généré automatiquement.',
            'content_template' => self::wrap($definition['title'], $definition['body']),
            'variables' => [],
            'settings' => ['paper_size' => 'a4', 'orientation' => 'portrait'],
            'is_active' => true,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    private static function wrap(string $title, string $body): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:12pt;margin:40px;color:#222}'
            .'.header{text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:30px}'
            .'h1{text-align:center;font-size:18pt;margin:30px 0;text-decoration:underline}'
            .'.body p{line-height:1.7;text-align:justify}'
            .'.footer{margin-top:50px;font-size:9pt;color:#555}'
            .'.signature{margin-top:40px;text-align:right}'
            .'</style></head><body>'
            .'<div class="header"><strong>{{school_name}}</strong></div>'
            .'<h1>'.$title.'</h1>'
            .'<div class="body">'.$body.'</div>'
            .'<div class="signature">Fait le {{issue_date}}<br><br>Le Proviseur</div>'
            .'<div class="footer">N° {{document_number}} — Code de vérification : {{verification_code}}<br><img src="{{qr_code_data_uri}}" width="90" height="90" alt="QR"></div>'
            .'</body></html>';
    }
}
