<?php

return [
    'name' => 'Payroll',
    'cnss_rate' => env('PAYROLL_CNSS_RATE', 4.48),
    'tax_brackets' => [[0, 30000], [30001, 50000], [50001, PHP_INT_MAX]],
];
