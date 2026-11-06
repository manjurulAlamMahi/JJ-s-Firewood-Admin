<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PublicApiCors
{
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = [
            'https://jjsfirewoodperth.com.au',
            'https://www.jjsfirewoodperth.com.au',
            'http://localhost:3000', // For development
            'http://localhost:8000', // For development
        ];
        
        $origin = $request->header('Origin');
        
        if (in_array($origin, $allowedOrigins)) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->header('Access-Control-Allow-Credentials', 'true');
        }
        
        return $next($request);
    }
}