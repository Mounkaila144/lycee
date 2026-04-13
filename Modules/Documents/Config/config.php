<?php

return [
    'name' => 'Documents',

    /**
     * Document numbering configuration
     */
    'numbering' => [
        'transcript_semester' => [
            'prefix' => 'TS',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'transcript_global' => [
            'prefix' => 'TG',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'transcript_provisional' => [
            'prefix' => 'TP',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'diploma' => [
            'prefix' => 'DIP',
            'format' => '{prefix}-{programme}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'diploma_duplicate' => [
            'prefix' => 'DUP-DIP',
            'format' => '{prefix}-{programme}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_enrollment' => [
            'prefix' => 'CE',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_status' => [
            'prefix' => 'CS',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_achievement' => [
            'prefix' => 'CA',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_attendance' => [
            'prefix' => 'CAT',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_schooling' => [
            'prefix' => 'CSC',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
        'certificate_transfer' => [
            'prefix' => 'CT',
            'format' => '{prefix}-{year}-{sequence}',
            'sequence_length' => 5,
        ],
    ],

    /**
     * Certificate fees (in local currency)
     */
    'certificate_fees' => [
        'certificate_enrollment' => 5000,
        'certificate_status' => 5000,
        'certificate_achievement' => 3000,
        'certificate_attendance' => 5000,
        'certificate_schooling' => 5000,
        'certificate_transfer' => 10000,
        'urgent_fee_multiplier' => 1.5,
    ],

    /**
     * Request processing times (in business days)
     */
    'processing_times' => [
        'normal' => 5,
        'urgent' => 2,
    ],

    /**
     * Watermark settings
     */
    'watermark' => [
        'enabled' => env('DOCUMENTS_WATERMARK', true),
        'text' => config('app.name'),
        'opacity' => 0.1,
        'font_size' => 60,
        'angle' => 45,
    ],

    /**
     * PDF generation settings
     */
    'pdf' => [
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'margins' => [
            'top' => 10,
            'bottom' => 10,
            'left' => 10,
            'right' => 10,
        ],
    ],

    /**
     * QR Code settings
     */
    'qr_code' => [
        'size' => env('DOCUMENTS_QR_SIZE', 300),
        'format' => 'png',
        'error_correction' => 'H',
    ],

    /**
     * Student card settings
     */
    'student_card' => [
        'width_mm' => 85.6,
        'height_mm' => 53.98,
        'validity_months' => 12,
        'grace_period_months' => 3,
        'default_access_permissions' => [
            'library',
            'computer_lab',
            'cafeteria',
            'main_building',
        ],
    ],

    /**
     * Archive settings
     */
    'archive' => [
        'enabled' => true,
        'auto_archive_after_days' => 30,
        'move_to_cold_storage_after_days' => 365,
        'encryption_enabled' => false,
        'encryption_method' => 'AES-256-CBC',
    ],

    /**
     * Electronic signature settings
     */
    'electronic_signature' => [
        'enabled' => true,
        'hash_algorithm' => 'sha256',
        'certificate_validity_days' => 365,
    ],

    /**
     * Diploma honors configuration
     */
    'honors' => [
        'excellent' => ['min_gpa' => 16, 'label' => 'Excellent'],
        'tres_bien' => ['min_gpa' => 14, 'label' => 'Très Bien'],
        'bien' => ['min_gpa' => 12, 'label' => 'Bien'],
        'assez_bien' => ['min_gpa' => 10, 'label' => 'Assez Bien'],
        'passable' => ['min_gpa' => 0, 'label' => 'Passable'],
    ],

    /**
     * Verification settings
     */
    'verification' => [
        'public_verification_enabled' => true,
        'verification_url' => '/verify',
        'log_verifications' => true,
        'geolocation_enabled' => false,
    ],

    /**
     * Batch generation limits
     */
    'batch' => [
        'max_documents_per_batch' => 500,
        'chunk_size' => 50,
    ],
];
