<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()->is_staff_account, 403, 'This area is for cafe owners. Your staff access is available in the scanner.');

        return $next($request);
    }
}
