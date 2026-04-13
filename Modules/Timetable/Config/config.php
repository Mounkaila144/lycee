<?php

return [
    'name' => 'Timetable',

    // Contraintes emploi du temps
    'max_hours_teacher' => env('TIMETABLE_MAX_HOURS_TEACHER', 6), // Heures max par jour pour un enseignant
    'max_consecutive_hours' => env('TIMETABLE_MAX_CONSECUTIVE_HOURS', 6), // Heures consécutives max étudiants
    'max_modules_per_generation' => env('TIMETABLE_MAX_MODULES', 100), // Limite génération auto

    // Créneaux standards
    'standard_slot_duration' => 2, // Durée standard en heures
    'min_slot_duration' => 1, // Durée minimale en heures
    'max_slot_duration' => 4, // Durée maximale en heures

    // Horaires de travail
    'work_start_time' => '08:00:00',
    'work_end_time' => '18:00:00',
    'lunch_break_start' => '12:00:00',
    'lunch_break_end' => '14:00:00',

    // Exceptions
    'max_exceptions_per_slot' => env('TIMETABLE_MAX_EXCEPTIONS_PER_SLOT', 3), // Nombre max d'exceptions par créneau
];
