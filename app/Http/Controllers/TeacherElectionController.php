<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Services\ElectionService;
use App\Services\ParticipationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TeacherElectionController extends Controller
{
    public function index(Request $request, ElectionService $service, ParticipationService $participation)
    {
        $setting = ElectionSetting::current();
        return view('teacher.dashboard', [
            'teacherKey' => $request->route('teacherKey'),
            'setting' => $setting,
            'registered' => Student::query()->count(),
            'participated' => Participation::query()->count(),
            'candidateCount' => Candidate::query()->count(),
            'readiness' => $setting->status === 'preparation' ? $service->readiness() : [],
            'turnout' => $participation->summary(),
            'scheduleNotice' => $service->scheduleNotice($setting),
        ]);
    }

    public function open(Request $request, ElectionService $service)
    {
        $request->validate(['confirmation' => ['required', 'in:BUKA']]);
        $service->open();
        return redirect()->route('teacher.dashboard', ['teacherKey' => $request->route('teacherKey')])->with('status', 'Pemilihan dibuka. Data calon dan siswa sekarang dikunci.');
    }

    public function close(Request $request, ElectionService $service)
    {
        $request->validate(['confirmation' => ['required', 'in:TUTUP']]);
        $service->close();
        return redirect()->route('teacher.results', ['teacherKey' => $request->route('teacherKey')])->with('status', 'Pemilihan ditutup. Seluruh suara yang selesai tersimpan telah dihitung.');
    }

    public function results(Request $request, ElectionService $service)
    {
        return view('teacher.results', $service->results() + ['teacherKey' => $request->route('teacherKey')]);
    }

    public function reopen(Request $request, ElectionService $service)
    {
        $data = $request->validate([
            'confirmation' => ['required', 'in:BUKA KEMBALI'],
            'reopen_closes_at' => ['nullable', 'string', 'date_format:Y-m-d\TH:i'],
        ], [
            'confirmation.in' => 'Ketik BUKA KEMBALI untuk membuka kembali pemilihan.',
            'reopen_closes_at.date_format' => 'Format batas tutup baru tidak valid.',
        ]);
        $closesAt = ! empty($data['reopen_closes_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['reopen_closes_at'], 'Asia/Jakarta')->utc()
            : null;
        $service->reopen($closesAt);

        return redirect()->route('teacher.dashboard', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', 'Pemilihan dibuka kembali. Suara lama tetap tersimpan, siswa yang sudah memilih tidak dapat memilih lagi, dan pengumuman hasil disembunyikan.');
    }

    public function export(ElectionService $service)
    {
        $results = $service->results();
        return response()->streamDownload(function () use ($results) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Rekap pemilihan ketua OSIS'], ',', '"', '');
            foreach (['registered' => 'Siswa terdaftar', 'participated' => 'Sudah memilih', 'remaining' => 'Belum memilih', 'votes' => 'Total suara'] as $key => $label) {
                fputcsv($output, [$label, $results[$key]], ',', '"', '');
            }
            fputcsv($output, ['Konsistensi data', $results['consistent'] ? 'Sesuai' : 'PERLU PEMERIKSAAN'], ',', '"', '');
            fputcsv($output, ['Nomor urut', 'Nama calon', 'Jumlah suara'], ',', '"', '');
            foreach ($results['candidates'] as $candidate) {
                // Avoid spreadsheet formula injection in teacher-provided names.
                $name = preg_match('/^[=+@\-\t\r\n]/u', $candidate->name) ? "'".$candidate->name : $candidate->name;
                fputcsv($output, [$candidate->number, $name, $candidate->votes_count], ',', '"', '');
            }
            fclose($output);
        }, 'rekap-osis-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
