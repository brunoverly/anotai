<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $chatId = $request->route('chatId');

        if (!$chatId || !is_numeric($chatId)) {
            abort(404);
        } else if (session('chatId') !== (int) $chatId) {
            abort(403);
        }

        return $next($request);
    }
}
