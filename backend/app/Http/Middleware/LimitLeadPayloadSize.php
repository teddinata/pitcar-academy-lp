<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The lead payload is a few hundred bytes. Anything meaningfully larger is
 * either a bug or someone probing, and is rejected before validation runs.
 */
class LimitLeadPayloadSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('leads.max_payload_bytes');
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($limit > 0 && $length > $limit) {
            return response()->json(
                ['message' => 'Ukuran data terlalu besar.'],
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE
            );
        }

        return $next($request);
    }
}
