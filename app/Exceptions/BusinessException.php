<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a business rule is violated. Rendered as a friendly
 * flash message (web) or 422 JSON (API).
 */
class BusinessException extends RuntimeException
{
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
