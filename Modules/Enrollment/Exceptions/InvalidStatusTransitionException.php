<?php

namespace Modules\Enrollment\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidStatusTransitionException extends Exception
{
    protected string $fromStatus;

    protected string $toStatus;

    public function __construct(string $fromStatus, string $toStatus, ?string $message = null)
    {
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;

        $message = $message ?? "Transition de statut non autorisée: {$fromStatus} → {$toStatus}";

        parent::__construct($message, 422);
    }

    /**
     * Get the from status.
     */
    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    /**
     * Get the to status.
     */
    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_status_transition',
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ], 422);
    }
}
