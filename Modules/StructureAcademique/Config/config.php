<?php

return [
    'name' => 'StructureAcademique',

    // Teacher workload configuration
    'workload_threshold' => env('TEACHER_WORKLOAD_THRESHOLD', 200), // Hours per semester before overload
    'annual_normal_hours' => env('TEACHER_ANNUAL_NORMAL_HOURS', 192), // Normal annual hours before complementary hours
];
