<?php

namespace App\Http\Middleware;

use App\Services\Lms\StudentSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStudent
{
    public function __construct(private StudentSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $student = $this->sessions->student($request->bearerToken());

        if ($student === null) {
            return response()->json(['message' => 'Sesi masuk sudah berakhir. Masuk lagi untuk melanjutkan.'], 401);
        }

        $request->attributes->set('student', $student);

        return $next($request);
    }
}
