<?php

namespace Modules\NotesEvaluations\Exceptions;

use Exception;

class CoefficientLockedException extends Exception
{
    /**
     * Create a new CoefficientLockedException instance.
     */
    public function __construct(string $message = 'Coefficient is locked and cannot be modified')
    {
        parent::__construct($message);
    }
}
