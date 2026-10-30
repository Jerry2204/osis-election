<?php

namespace App\Http\Controllers;

use App\Services\ElectionReportService;

class TeacherReportController extends Controller
{
    public function download(ElectionReportService $report)
    {
        return $report->pdf()->download('rekap-osis-'.now()->format('Y-m-d-His').'.pdf');
    }
}
