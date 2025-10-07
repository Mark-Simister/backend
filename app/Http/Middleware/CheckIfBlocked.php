<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckIfBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if the user is blocked
        if ($user && $user->is_blocked) {

            auth('api')->logout();
            
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }

        return $next($request);
    }
}
