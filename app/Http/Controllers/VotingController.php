<?php

namespace App\Http\Controllers;

use App\Exceptions\VotingRejected;
use App\Models\Candidate;
use App\Services\CastVoteService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VotingController extends Controller
{
    public function candidates(Request $request)
    {
        return view('voting.candidates', [
            'candidates' => Candidate::query()->orderBy('number')->get(),
            'context' => $request->session()->get('voting'),
        ]);
    }

    public function choose(Request $request)
    {
        $validator = Validator::make($request->all(), ['candidate_id' => ['required', 'integer', 'exists:candidates,id']]);
        if ($validator->fails()) {
            return redirect()->route('voting.candidates')->with('notice', 'Pilih satu calon yang tersedia terlebih dahulu.');
        }
        $request->session()->put('voting.candidate_id', (int) $request->input('candidate_id'));
        // Each confirmation is single-use, including when another tab changes the choice.
        $request->session()->put('voting.token', \Illuminate\Support\Str::random(64));
        return redirect()->route('voting.confirm');
    }

    public function confirm(Request $request)
    {
        $candidate = Candidate::query()->find($request->session()->get('voting.candidate_id'));
        if (! $candidate) {
            return redirect()->route('voting.candidates');
        }
        return view('voting.confirm', ['candidate' => $candidate, 'context' => $request->session()->get('voting')]);
    }

    public function store(Request $request, CastVoteService $service)
    {
        try {
            $service->cast($request->session()->get('voting', []), (string) $request->input('flow_token'));
        } catch (VotingRejected $exception) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('home')->with('notice', $exception->getMessage());
        } catch (QueryException) {
            // Never log query bindings: they could reveal the choice or identity.
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('home')->with('notice', 'Status pengiriman belum dapat dipastikan. Silakan hubungi petugas sebelum mencoba kembali.');
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('vote_completed', true);
        return redirect()->route('voting.success');
    }

    public function success(Request $request)
    {
        if (! $request->session()->pull('vote_completed', false)) {
            return redirect()->route('home');
        }
        return view('voting.success');
    }

    public function photo(Candidate $candidate)
    {
        $disk = Storage::disk('photos');
        abort_unless($disk->exists($candidate->photo_path), 404);
        return response()->file($disk->path($candidate->photo_path));
    }
}
