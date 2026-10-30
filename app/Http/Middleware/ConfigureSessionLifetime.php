<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConfigureSessionLifetime
{
    public function handle(Request $request, Closure $next)
    {
        // Read the immutable base configuration rather than session.lifetime:
        // a reused application may have just handled a teacher request.
        $base = max(1, (int) config('election.base_session_lifetime_minutes', 5));
        $teacher = max(30, $base, (int) config('election.teacher_session_lifetime_minutes', 30));
        config(['session.lifetime' => $request->is('ruang-guru/*') ? $teacher : $base]);
        if (config('session.driver') === 'file') {
            // Laravel's request-level GC uses this request's lifetime for ALL
            // files in the shared directory. A voter request must not purge
            // a valid longer-lived teacher preview; the scheduled purge
            // command applies the longest configured duration instead.
            config(['session.lottery' => [0, 100]]);
        }

        return $next($request);
    }
}
