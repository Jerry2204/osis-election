<?php

namespace App\Http\Controllers;

use App\Models\ElectionSetting;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VoterSessionController extends Controller
{
    public function home(Request $request)
    {
        $notice = $request->session()->get('notice');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return view('voting.home', ['setting' => ElectionSetting::current(), 'notice' => $notice]);
    }

    public function verify(Request $request)
    {
        // Replacing the NIS always destroys any previous voter's context.
        $request->session()->forget('voting');
        $validator = Validator::make($request->all(), ['nis' => ['required', 'string', 'max:32', 'regex:/\A[0-9]+\z/']], [
            'nis.required' => 'Masukkan Nomor Induk Siswa (NIS) Anda.',
            'nis.string' => 'NIS harus berupa teks berisi angka.',
            'nis.max' => 'NIS maksimal 32 angka.',
            'nis.regex' => 'NIS hanya boleh berisi angka, tanpa spasi di tengah.',
        ]);
        if ($validator->fails()) {
            return redirect()->route('home')->with('notice', $validator->errors()->first());
        }
        $student = Student::query()->where('nis', $request->string('nis')->toString())->first();
        if (! $student) {
            return redirect()->route('home')->with('notice', 'NIS tidak terdaftar. Silakan hubungi petugas.');
        }
        if ($student->participation()->exists()) {
            return redirect()->route('home')->with('notice', 'NIS ini sudah digunakan untuk memilih.');
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $closesAt = ElectionSetting::current()->closes_at;
        $expiresAt = now()->timestamp + config('election.voting_lifetime');
        if ($closesAt) {
            $expiresAt = min($expiresAt, $closesAt->timestamp);
        }
        $request->session()->put('voting', [
            'student_id' => $student->id,
            'expires_at' => $expiresAt,
            'token' => Str::random(64),
        ]);
        return redirect()->route('voting.candidates');
    }

    public function cancel(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
