<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\User;

class CheckSingleRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        $user = Auth::user();
        if ($this->checkUserRole($user, $role)) {
            return $next($request);
        }
    
        return response()->json(['message' => 'Forbidden'], 403);
    }
    
    private function checkUserRole(?Authenticatable $user, string $role): bool
    {
        if ($user instanceof User) {
            return $user->roles()->where('name', $role)->exists();
        }
        return false;
    }

}