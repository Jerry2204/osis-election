<?php

namespace App\Services;

use App\Models\ElectionSetting;
use Barryvdh\DomPDF\Facade\Pdf as DompdfFacade;
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ElectionReportService
{
    public function __construct(
        private readonly ElectionService $election,
        private readonly ParticipationService $participation,
    ) {
    }

    public function data(): array
    {
        // This is the same guarded source used by the HTML and CSV result pages.
        $results = $this->election->results();
        $setting = ElectionSetting::current();
        $highest = $results['candidates']->max('votes_count');
        $leaders = $results['votes'] > 0
            ? $results['candidates']->where('votes_count', $highest)->pluck('name')->values()->all()
            : [];

        return [
            'setting' => $setting,
            'results' => $results,
            'turnout' => $this->participation->summary(),
            'generatedAt' => CarbonImmutable::now('Asia/Jakarta'),
            'logoDataUri' => $this->logoDataUri($setting),
            'tiedLeaders' => count($leaders) > 1 ? $leaders : [],
        ];
    }

    public function pdf(?array $data = null): PDF
    {
        File::ensureDirectoryExists(storage_path('fonts'), 0700);
        File::ensureDirectoryExists(storage_path('app/private/pdf-temp'), 0700);
        $pdf = DompdfFacade::loadView('teacher.report-pdf', $data ?? $this->data())
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ], true);

        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(
            40,
            811,
            'Suara OSIS | Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal'),
            8,
            [0.36, 0.42, 0.38],
        );

        return $pdf;
    }

    private function logoDataUri(ElectionSetting $setting): ?string
    {
        if (! $setting->logo_path || ! Storage::disk('local')->exists($setting->logo_path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($setting->logo_path);
        if (! is_string($contents) || strlen($contents) > 2 * 1024 * 1024) {
            return null;
        }
        $image = @getimagesizefromstring($contents);
        if (! is_array($image) || ! in_array($image['mime'] ?? null, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        // Embed only verified local bitmap bytes; the renderer never fetches a URL.
        return 'data:'.$image['mime'].';base64,'.base64_encode($contents);
    }
}
