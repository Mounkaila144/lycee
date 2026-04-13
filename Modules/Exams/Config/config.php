<?php

return [
    'name' => 'Exams',

    // Supervisor ratios
    'supervisor_student_ratio' => env('EXAM_SUPERVISOR_RATIO', 30), // 1 supervisor per X students

    // Time settings
    'late_arrival_threshold_minutes' => env('EXAM_LATE_THRESHOLD', 15),
    'early_submission_threshold_minutes' => env('EXAM_EARLY_SUBMISSION', 30),

    // Incident settings
    'auto_escalate_critical' => env('EXAM_AUTO_ESCALATE_CRITICAL', true),
    'incident_evidence_max_size' => env('EXAM_EVIDENCE_MAX_SIZE', 10240), // KB

    // Notification settings
    'notify_supervisors_hours_before' => env('EXAM_NOTIFY_HOURS', 24),
    'notify_students_hours_before' => env('EXAM_NOTIFY_STUDENTS', 48),
];
