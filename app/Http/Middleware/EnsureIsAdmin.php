<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only staff admins may pass (alias: admin.staff). Moderators get a 403, never a 404,
 * so the route is not hidden from them: they just are not allowed to use it.
 *
 * Use it after auth:staff. It is also registered as Livewire persistent middleware,
 * so it runs again on every request of a Livewire component mounted behind it.
 */
class EnsureIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth('staff')->user()?->isAdmin(), 403);

        return $next($request);
    }
}
