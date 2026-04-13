<?php

require __DIR__.'/../vendor/autoload.php';

header('Content-Type: application/json');

$constant = Modules\Enrollment\Entities\PedagogicalEnrollment::STATUS_VALIDATED;

echo json_encode([
    'constant' => $constant,
    'hex' => bin2hex($constant),
    'file_mtime' => filemtime(__DIR__.'/../Modules/Enrollment/Entities/PedagogicalEnrollment.php'),
    'opcache_status' => function_exists('opcache_get_status') ? opcache_get_status(false) : 'disabled',
]);
