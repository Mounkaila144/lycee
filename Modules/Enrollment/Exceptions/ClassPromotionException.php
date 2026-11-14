<?php

namespace Modules\Enrollment\Exceptions;

use Exception;

/**
 * Erreur métier lors d'un passage de classe (classe cible invalide, classe complète, doublon…).
 */
class ClassPromotionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422);
    }
}
