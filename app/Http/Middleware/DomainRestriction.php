<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DomainRestriction
{
    public function handle(Request $request, Closure $next)
    {
        $allowedDomains = env('ALLOWED_DOMAINS');
        
        if (empty($allowedDomains)) {
            return $next($request);
        }
        
        $domains = is_string($allowedDomains) ? explode(',', $allowedDomains) : $allowedDomains;
        $host = $request->getHost();
        
        if (!in_array($host, $domains)) {
            abort(403, 'Unauthorized domain');
        }
        
        return $next($request);
    }
}
