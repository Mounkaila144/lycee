<?php

return [
    'name' => 'Finance',
    'late_fee_percentage' => env('FINANCE_LATE_FEE_PERCENTAGE', 5),
    'reminder_days' => [7, 14, 30],
    'payment_methods' => ['cash', 'check', 'transfer', 'card', 'online'],

    /*
     * Recouvrement : calendrier des relances (jours après l'échéance).
     * Sans ce calendrier, la génération des relances ne produisait rien.
     */
    'collection' => [
        'auto_reminders' => true,
        'reminder_schedule' => [
            ['days_after_due' => 7, 'type' => 'first_reminder', 'method' => 'email'],
            ['days_after_due' => 14, 'type' => 'second_reminder', 'method' => 'email_sms'],
            ['days_after_due' => 30, 'type' => 'final_notice', 'method' => 'email_sms'],
        ],
    ],
];
