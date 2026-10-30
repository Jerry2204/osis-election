<?php

namespace App\Http\Middleware;

use App\Services\ElectionService;
use Closure;
use Illuminate\Http\Request;

class ReconcileElectionSchedule
{
    public function handle(Request $request, Closure $next)
    {
        app(ElectionService::class)->reconcile();
        return $next($request);
    }
}
