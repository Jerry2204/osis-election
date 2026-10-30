<?php

namespace App\Http\Controllers;

use App\Services\ElectionService;
use App\Services\ParticipationService;
use App\Services\VoteTallyService;

class ElectionStatusController extends Controller
{
    public function publicStatus(ElectionService $service)
    {
        $setting = $service->reconcile();
        return response()->json([
            'status' => $setting->status,
            'server_time' => now()->toIso8601String(),
            'opens_at' => $setting->opens_at?->toIso8601String(),
            'closes_at' => $setting->closes_at?->toIso8601String(),
        ]);
    }

    public function progress(ElectionService $election, ParticipationService $participation)
    {
        $setting = $election->reconcile();
        return response()->json($participation->summary() + [
            'status' => $setting->status,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function tally(ElectionService $election, VoteTallyService $tally)
    {
        $election->reconcile();
        return response()->json($tally->snapshot());
    }
}
