<?php

namespace App\Http\Controllers;

use App\Models\ElectionSetting;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function show(AnnouncementService $service)
    {
        $setting = ElectionSetting::current();
        if (! $service->available($setting)) {
            return response()->view('announcements.waiting', compact('setting'), 404);
        }
        return view('announcements.results', $service->data($setting) + ['preview' => false]);
    }

    public function status(AnnouncementService $service)
    {
        return response()->json(['available' => $service->available(ElectionSetting::current())]);
    }

    public function preview(AnnouncementService $service)
    {
        $setting = ElectionSetting::current();
        abort_unless($setting->status === 'closed', 403, 'Pratinjau hasil tersedia setelah pemilihan ditutup.');
        $data = $service->data($setting);
        abort_unless($data['consistent'], 403, 'Jumlah partisipasi dan suara belum sesuai. Periksa data terlebih dahulu.');
        return view('announcements.results', $data + ['preview' => true]);
    }

    public function update(Request $request, AnnouncementService $service)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $service->publish((bool) $data['enabled']);
        return redirect()->route('teacher.results', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', $data['enabled'] ? 'Pengumuman hasil diaktifkan. Buka layar pengumuman untuk ditampilkan pada proyektor.' : 'Pengumuman hasil disembunyikan. Layar yang sedang terbuka akan memperbarui status otomatis.');
    }
}
