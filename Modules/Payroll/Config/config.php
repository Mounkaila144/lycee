<?php

/*
 * Paramètres de paie par défaut (Niger), utilisés quand l'école n'a encore
 * enregistré aucune version dans la table tenant `payroll_settings`.
 * Valeurs indicatives : À VÉRIFIER PAR LE COMPTABLE avant toute paie réelle.
 */
return [
    'name' => 'Payroll',

    'default_settings' => [
        'country' => 'NE',
        'currency' => 'XOF',
        'currency_label' => 'FCFA',
        'rounding_precision' => 0,
        'requires_review' => true,
        'review_notice' => 'Valeurs par défaut indicatives (Niger) — à vérifier par le comptable avant toute paie réelle. '
            .'Taux CNSS et plafond : source CLEISS au 01/01/2024. Le barème IUTS fourni est un EXEMPLE à remplacer par le barème officiel en vigueur.',
        'contributions' => [
            [
                'code' => 'cnss_pension',
                'label' => 'CNSS — Vieillesse, invalidité, décès',
                'employee_rate' => 5.25,
                'employer_rate' => 6.25,
                'ceiling' => 500000,
                'floor' => null,
                'active' => true,
                'tax_deductible' => true,
            ],
            [
                'code' => 'cnss_family',
                'label' => 'CNSS — Prestations familiales / maternité',
                'employee_rate' => 0,
                'employer_rate' => 8.40,
                'ceiling' => 500000,
                'floor' => null,
                'active' => true,
                'tax_deductible' => false,
            ],
            [
                'code' => 'cnss_work_injury',
                'label' => 'CNSS — Accidents du travail (taux indicatif)',
                'employee_rate' => 0,
                'employer_rate' => 1.75,
                'ceiling' => 500000,
                'floor' => null,
                'active' => true,
                'tax_deductible' => false,
            ],
        ],
        'income_tax' => [
            'label' => 'IUTS — Impôt sur les traitements et salaires',
            'brackets' => [
                ['from' => 0, 'to' => 25000, 'rate' => 0],
                ['from' => 25000, 'to' => 50000, 'rate' => 2],
                ['from' => 50000, 'to' => 100000, 'rate' => 6],
                ['from' => 100000, 'to' => 150000, 'rate' => 13],
                ['from' => 150000, 'to' => 300000, 'rate' => 25],
                ['from' => 300000, 'to' => 400000, 'rate' => 30],
                ['from' => 400000, 'to' => null, 'rate' => 35],
            ],
            'flat_allowance_rate' => 0,
            'deduct_employee_contributions' => true,
        ],
        'employer_charges' => [
            [
                'code' => 'apprenticeship_tax',
                'label' => "Taxe d'apprentissage / formation professionnelle (à confirmer)",
                'rate' => 3,
                'active' => false,
            ],
        ],
    ],
];
