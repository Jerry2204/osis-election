<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use App\Services\ElectionReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function fixture(): void
    {
        foreach ([1, 2] as $number) {
            $candidate = Candidate::query()->create(['number' => $number, 'name' => 'Calon '.$number, 'photo_path' => 'unused.png', 'vision' => 'Visi', 'mission' => 'Misi']);
            $student = Student::query()->create(['nis' => '0000'.$number, 'name' => 'Siswa '.$number, 'class_name' => 'X A']);
            Participation::query()->create(['student_id' => $student->id]);
            Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
        }
        Student::query()->create(['nis' => '00003', 'name' => 'Belum Memilih', 'class_name' => 'XI B']);
        ElectionSetting::current()->update(['status' => 'closed']);
    }

    public function test_pdf_is_available_only_to_teacher_after_closure(): void
    {
        $this->get($this->teacher.'/rekap.pdf')->assertForbidden();
        ElectionSetting::current()->update(['status' => 'open']);
        $this->get($this->teacher.'/rekap.pdf')->assertForbidden();
        $this->fixture();
        $this->get('/ruang-guru/'.str_repeat('b', 64).'/rekap.pdf')->assertNotFound();
        $response = $this->get($this->teacher.'/rekap.pdf');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_pdf_data_matches_csv_totals_preserves_ties_and_omits_student_identity(): void
    {
        $this->fixture();
        $data = app(ElectionReportService::class)->data();
        $this->assertSame(3, $data['results']['registered']);
        $this->assertSame(2, $data['results']['participated']);
        $this->assertSame(1, $data['results']['remaining']);
        $this->assertSame(2, $data['results']['votes']);
        $this->assertTrue($data['results']['consistent']);
        $this->assertEqualsWithDelta(66.6667, $data['turnout']['percentage'], 0.1);
        $this->assertSame(['Calon 1', 'Calon 2'], $data['tiedLeaders']);
        $html = view('teacher.report-pdf', $data)->render();
        $this->assertStringContainsString('Perolehan suara tertinggi sama', $html);
        $this->assertStringContainsString('Tidak tersedia', $html);
        $this->assertStringNotContainsString('00001', $html);
        $this->assertStringNotContainsString('Belum Memilih', $html);
        $csv = $this->get($this->teacher.'/rekap.csv')->streamedContent();
        $this->assertStringContainsString('"Siswa terdaftar",3', $csv);
        $this->assertStringContainsString('"Sudah memilih",2', $csv);
        $this->assertStringContainsString('"Total suara",2', $csv);
    }

    public function test_report_escapes_branding_embeds_only_local_logo_and_formats_actual_wib_times(): void
    {
        Storage::fake('local');
        $logo = UploadedFile::fake()->image('logo.png');
        Storage::disk('local')->put('school-logos/logo.png', file_get_contents($logo->path()));
        $this->fixture();
        ElectionSetting::current()->update([
            'school_name' => '<script>Sekolah</script>', 'election_title' => 'Pemilihan & Persahabatan',
            'osis_period' => '2026/2027', 'logo_path' => 'school-logos/logo.png',
            'opened_at' => CarbonImmutable::parse('2026-10-30 01:00:00', 'UTC'),
            'closed_at' => CarbonImmutable::parse('2026-10-30 03:00:00', 'UTC'),
            'report_place' => 'Jakarta', 'committee_chair' => 'Nama Ketua', 'osis_advisor' => 'Nama Pembina', 'principal_name' => 'Nama Kepala',
        ]);
        $data = app(ElectionReportService::class)->data();
        $this->assertStringStartsWith('data:image/png;base64,', $data['logoDataUri']);
        $html = view('teacher.report-pdf', $data)->render();
        $this->assertStringContainsString('&lt;script&gt;Sekolah&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>Sekolah</script>', $html);
        $this->assertStringContainsString('30/10/2026 08:00:00 WIB', $html);
        $this->assertStringContainsString('Nama Ketua', $html);
        $this->assertStringContainsString('Nama Pembina', $html);
        $this->assertStringContainsString('Nama Kepala', $html);
        $pdf = app(ElectionReportService::class)->pdf($data);
        $options = $pdf->getDomPDF()->getOptions();
        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
        $this->assertFalse($options->getIsJavascriptEnabled());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_large_class_report_is_multipage_and_warns_when_totals_differ(): void
    {
        $this->fixture();
        for ($i = 1; $i <= 70; $i++) {
            Student::query()->create(['nis' => '9'.str_pad($i, 5, '0', STR_PAD_LEFT), 'name' => 'Siswa Uji', 'class_name' => sprintf('Kelas %02d', $i)]);
        }
        $student = Student::query()->where('nis', '00003')->firstOrFail();
        Participation::query()->create(['student_id' => $student->id]);
        $data = app(ElectionReportService::class)->data();
        $this->assertFalse($data['results']['consistent']);
        $this->assertStringContainsString('Perlu pemeriksaan.', view('teacher.report-pdf', $data)->render());
        $pdf = app(ElectionReportService::class)->pdf($data);
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_closed_empty_election_has_zero_percentages_and_no_tie(): void
    {
        ElectionSetting::current()->update(['status' => 'closed']);
        $data = app(ElectionReportService::class)->data();
        $this->assertSame([], $data['tiedLeaders']);
        $this->assertEquals(0, $data['turnout']['percentage']);
        $html = view('teacher.report-pdf', $data)->render();
        $this->assertStringContainsString('0,0%', $html);
        $this->assertStringContainsString('Tidak ada calon terdaftar.', $html);
        $this->assertStringNotContainsString('Perolehan suara tertinggi sama', $html);
    }
}
