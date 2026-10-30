<?php

namespace App\Http\Middleware;

use App\Models\ElectionSetting;
use Closure;
use Illuminate\Http\Request;

class EnsureElectionOpen
{
    public function handle(Request $request, Closure $next)
    {
        if (ElectionSetting::current()->status !== 'open') {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('home')->with('notice', 'Pemilihan belum dibuka atau sudah ditutup. Silakan hubungi petugas.');
        }
        return $next($request);
    }
}
