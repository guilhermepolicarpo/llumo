<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCurrentTeam
{
    /**
     * Send users without a team to onboarding, since the app layout links to team routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->resolveCurrentTeam()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
