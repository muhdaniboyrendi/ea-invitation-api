<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CookieAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if token exists in cookie
        $token = $request->cookie('auth_token');
        
        if ($token) {
            // Find the token in database
            $accessToken = PersonalAccessToken::findToken($token);
            
            if ($accessToken && !$accessToken->expires_at?->isPast()) {
                // Set the user for the request
                $request->setUserResolver(function () use ($accessToken) {
                    return $accessToken->tokenable;
                });
                
                // Add Authorization header for Sanctum
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}