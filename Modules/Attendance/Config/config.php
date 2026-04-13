<?php

return [
    'name' => 'Attendance',

    // Seuils d'alerte
    'warning_threshold' => env('ATTENDANCE_WARNING_THRESHOLD', 10), // % d'absences pour avertissement
    'critical_threshold' => env('ATTENDANCE_CRITICAL_THRESHOLD', 20), // % d'absences pour alerte critique

    // Retard
    'late_threshold_minutes' => env('ATTENDANCE_LATE_THRESHOLD', 15), // Minutes de retard acceptable

    // Justificatifs
    'justification_max_file_size' => env('ATTENDANCE_JUSTIFICATION_MAX_SIZE', 5120), // Ko
    'justification_allowed_types' => ['pdf', 'jpg', 'jpeg', 'png'],
    'justification_validity_days' => env('ATTENDANCE_JUSTIFICATION_VALIDITY', 7), // Jours pour soumettre

    // QR Code
    'qr_code_validity_minutes' => env('ATTENDANCE_QR_VALIDITY', 30), // Durée validité QR code

    // Rapports
    'default_absence_rate_threshold' => 20.0, // Taux min pour liste absentéistes
];
