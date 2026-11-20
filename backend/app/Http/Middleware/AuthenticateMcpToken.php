<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A bearer token made in the admin, owned by an active admin or manager. */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = McpToken::findPlain($request->bearerToken());
        $user = $token?->user;

        if ($user === null || ! $user->is_active || ! $user->seesAllLeads()) {
            return response()->json(['error' => 'Token MCP tidak valid atau sudah dicabut.'], 401);
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        Auth::setUser($user);

        return $next($request);
    }
}
