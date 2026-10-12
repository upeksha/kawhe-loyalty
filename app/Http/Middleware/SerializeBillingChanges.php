<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerializeBillingChanges
{
    public function handle(Request $request, Closure $next)
    {
        // Checkout and deletion must not race using a stale authenticated user.
        return DB::transaction(function () use ($request, $next) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->first();
            abort_unless($user, 401);
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        });
    }
}
