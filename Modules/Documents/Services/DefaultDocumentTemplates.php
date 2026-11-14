<?php

namespace Modules\Documents\Services;

use Modules\Documents\Entities\DocumentTemplate;

/**
 * Built-in fallback templates used when a tenant has not configured
 * its own active DocumentTemplate for a given document type.
 */
class DefaultDocumentTemplates
{
    public const AUTO_DESCRIPTION = 'Modèle par défaut généré automatiquement.';

    /**
     * Marqueur de l'ancien en-tête (nom seul) des modèles générés automatiquement.
     */
    private const LEGACY_HEADER = '<div class="header"><strong>{{school_name}}</strong></div>';

    /**
     * @var array<string, array{name: string, title: string, body: string}>
     */
    private const DEFINITIONS = [
        'certificate_enrollment' => [
            'name' => 'Certificat d\'inscription (défaut)',
            'title' => 'CERTIFICAT D\'INSCRIPTION',
            'body' => '<p>{{head_title}} certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}} à {{student_birthplace}}, est régulièrement inscrit(e) dans notre établissement au titre de l\'année scolaire <strong>{{academic_year}}</strong>, en classe de <strong>{{level}}</strong>.</p><p>Le présent certificat est délivré pour servir et valoir ce que de droit. Motif : {{purpose}}.</p>',
        ],
        'certificate_status' => [
            'name' => 'Attestation de statut (défaut)',
            'title' => 'ATTESTATION DE STATUT',
            'body' => '<p>{{head_title}} atteste que <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}}, a le statut suivant : <strong>{{status}}</strong> pour l\'année scolaire <strong>{{academic_year}}</strong>.</p><p>Motif : {{purpose}}.</p>',
        ],
        'certificate_achievement' => [
            'name' => 'Attestation de réussite (défaut)',
            'title' => 'ATTESTATION DE RÉUSSITE',
            'body' => '<p>{{head_title}} atteste que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, a obtenu au cours de l\'année scolaire {{academic_year}} : <strong>{{achievement_title}}</strong> ({{achievement_date}}).</p><p>{{achievement_description}}</p>',
        ],
        'certificate_attendance' => [
            'name' => 'Attestation d\'assiduité (défaut)',
            'title' => 'ATTESTATION D\'ASSIDUITÉ',
            'body' => '<p>{{head_title}} atteste que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, a suivi les cours de l\'année scolaire <strong>{{academic_year}}</strong> avec un taux de présence de <strong>{{attendance_rate}} %</strong> ({{attended_sessions}} séances sur {{total_sessions}}).</p><p>Motif : {{purpose}}.</p>',
        ],
        'certificate_schooling' => [
            'name' => 'Certificat de scolarité (défaut)',
            'title' => 'CERTIFICAT DE SCOLARITÉ',
            'body' => '<p>{{head_title}} certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}} à {{student_birthplace}}, suit régulièrement les cours dans notre établissement au titre de l\'année scolaire <strong>{{current_academic_year}}</strong>, en classe de <strong>{{level}}</strong>.</p><p>Le présent certificat est délivré pour servir et valoir ce que de droit. Motif : {{purpose}}.</p>',
        ],
        'certificate_transfer' => [
            'name' => 'Certificat de transfert / exeat (défaut)',
            'title' => 'CERTIFICAT DE TRANSFERT (EXEAT)',
            'body' => '<p>{{head_title}} certifie que l\'élève <strong>{{student_name}}</strong>, matricule <strong>{{student_matricule}}</strong>, né(e) le {{student_birthdate}}, inscrit(e) en classe de <strong>{{level}}</strong> au titre de l\'année scolaire {{current_academic_year}}, est autorisé(e) à quitter l\'établissement.</p><p>Établissement de destination : <strong>{{transfer_destination}}</strong>.<br>Motif du transfert : {{transfer_reason}}.</p><p>L\'intéressé(e) est libre de tout engagement envers l\'établissement.</p>',
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
        $definition = self::DEFINITIONS[$type] ?? null;

        if ($template) {
            if ($definition && self::isLegacyDefault($template)) {
                $template->update(['content_template' => self::wrap($definition['title'], $definition['body'])]);
            }

            return $template;
        }

        if (! $definition) {
            return null;
        }

        return DocumentTemplate::create([
            'type' => $type,
            'name' => $definition['name'],
            'description' => self::AUTO_DESCRIPTION,
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

    /**
     * Modèle par défaut généré avant l'introduction du profil de l'établissement (jamais personnalisé).
     */
    private static function isLegacyDefault(DocumentTemplate $template): bool
    {
        $content = (string) $template->content_template;

        return $template->description === self::AUTO_DESCRIPTION
            && str_contains($content, self::LEGACY_HEADER)
            && ! str_contains($content, '{{school_header}}');
    }

    /**
     * Variables disponibles : en-tête/pied de page de l'établissement ({{school_header}}, {{school_footer}},
     * {{school_stamp}}), {{school_name}}, {{issue_place}}, {{head_title}}, {{head_name}}… (SchoolProfileService).
     */
    private static function wrap(string $title, string $body): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:12mm 14mm}'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:11.5pt;margin:0;color:#222}'
            .'h1{text-align:center;font-size:17pt;margin:28px 0 24px;text-decoration:underline;letter-spacing:1px}'
            .'.body p{line-height:1.7;text-align:justify}'
            .'.signature{margin-top:36px;margin-left:55%;text-align:center}'
            .'.signature .role{font-weight:bold;margin-top:4px}'
            .'.signature .stamp{height:85px}'
            .'.reference{margin-top:30px;font-size:8.5pt;color:#555}'
            .'</style></head><body>'
            .'{{school_header}}'
            .'<h1>'.$title.'</h1>'
            .'<div class="body">'.$body.'</div>'
            .'<div class="signature">Fait à {{issue_place}}, le {{issue_date}}'
            .'<div class="role">{{head_title}}</div>'
            .'<div class="stamp">{{school_stamp}}</div>'
            .'<strong>{{head_name}}</strong></div>'
            .'<div class="reference">N° {{document_number}} — Code de vérification : {{verification_code}}<br><img src="{{qr_code_data_uri}}" width="80" height="80" alt="QR"></div>'
            .'{{school_footer}}'
            .'</body></html>';
    }
}
