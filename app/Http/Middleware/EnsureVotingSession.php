<?php

namespace App\Http\Middleware;

use App\Models\Participation;
use Closure;
use Illuminate\Http\Request;

class EnsureVotingSession
{
    public function handle(Request $request, Closure $next)
    {
        $context = $request->session()->get('voting', []);
        $message = null;
        if (! isset($context['student_id'], $context['token'], $context['expires_at']) ||
            $context['expires_at'] <= now()->timestamp) {
            $message = 'Sesi pemilihan berakhir. Silakan masukkan NIS kembali.';
        } elseif (Participation::query()->whereKey($context['student_id'])->exists()) {
            $message = 'NIS ini sudah digunakan untuk memilih.';
        } elseif ($request->isMethod('POST') &&
            (! is_string($request->input('flow_token')) || ! hash_equals($context['token'], $request->input('flow_token')))) {
            $message = 'Formulir sudah tidak berlaku. Silakan mulai kembali.';
        }
        if ($message) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('home')->with('notice', $message);
        }
        return $next($request);
    }
}
