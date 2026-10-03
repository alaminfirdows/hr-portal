<?php

namespace Modules\Shared\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * A business rule was violated. Rendered as a validation error + toast, never a 500.
 */
class DomainException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'domain')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => [$this->field => [$this->getMessage()]],
            ], 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back()->withInput()->withErrors([$this->field => $this->getMessage()]);
    }
}
