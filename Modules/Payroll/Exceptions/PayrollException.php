<?php

namespace Modules\Payroll\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Business rule violation in the payroll workflow (rendered as HTTP 422).
 */
class PayrollException extends Exception
{
    /**
     * Render the exception as a JSON 422 response.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
