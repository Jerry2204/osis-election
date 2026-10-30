<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTeacherKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('election.teacher_key');
        abort_unless(strlen($expected) === 64 && hash_equals($expected, (string) $request->route('teacherKey')), 404);
        return $next($request);
    }
}
