<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $expectedKey = 'Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs'; // Здесь твой ключ

        if ($request->header('X-Api-Secret') !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
