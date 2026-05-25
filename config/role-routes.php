<?php

/**
 * Role-based routing configuration.
 *
 * - "hierarchy" : ordered list (most prioritary first). When a user has multiple
 *   roles, the first match wins as primary role.
 * - "home_routes" : where to redirect a user after login (frontend route).
 * - "default_home" : fallback when the user has no recognised role.
 */

return [
    'hierarchy' => [
        'Administrator',
        'Manager',
        'Comptable',
        'Caissier',
        'Professeur',
        'Élève',
        'Parent',
        'User',
    ],

    'home_routes' => [
        'Administrator' => '/admin/dashboard',
        'Manager' => '/admin/dashboard',
        'Comptable' => '/admin/finance/reports',
        'Caissier' => '/admin/finance/payments',
        'Professeur' => '/admin/dashboard',
        'Élève' => '/admin/student/home',
        'Parent' => '/admin/parent/home',
        'User' => '/admin/dashboard',
    ],

    'default_home' => '/admin/dashboard',
];
