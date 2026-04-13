<?php

return [
    'name' => 'Finance',
    'late_fee_percentage' => env('FINANCE_LATE_FEE_PERCENTAGE', 5),
    'reminder_days' => [7, 14, 30],
    'payment_methods' => ['cash', 'check', 'transfer', 'card', 'online'],
];
