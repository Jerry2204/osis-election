<?php

namespace Tests\Feature;

use App\Exceptions\VotingRejected;
use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Student;
use App\Services\CastVoteService;
use App\Services\ElectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettingsAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function readyElection(): array
    {
        $student = Student::query()->create(['nis' => '000123', 'name' => 'Siswa Uji', 'class_name' => 'X A']);
        $candidates = [];
        foreach ([1, 2] as $number) {
            Storage::disk('photos')->put($number.'.png', 'photo');
            $candidates[] = Candidate::query()->create([
                'number' => $number, 'name' => 'Calon '.$number, 'photo_path' => $number.'.png', 'vision' => 'Visi', 'mission' => 'Misi',
            ]);
        }
        return [$student, $candidates[0]];
    }

    private function schedule(string $start = '2026-11-02 00:30:00', string $end = '2026-11-02 02:00:00'): void
    {
        ElectionSetting::current()->update([
            'opens_at' => CarbonImmutable::parse($start, 'UTC'),
            'closes_at' => CarbonImmutable::parse($end, 'UTC'),
        ]);
    }

    public function test_settings_defaults_and_page_preserve_existing_installations(): void
    {
        $setting = ElectionSetting::current();
        $this->assertSame('Suara OSIS', $setting->schoolLabel());
        $this->assertSame('Pemilihan Ketua OSIS', $setting->electionLabel());
        $this->assertNull($setting->opens_at);
        $this->get($this->teacher.'/pengaturan')->assertOk()->assertSee('Identitas sekolah')->assertSee('Tanggal dan jam buka (WIB)');
        $this->get('/ruang-guru/'.str_repeat('b', 64).'/pengaturan')->assertNotFound();
    }

    public function test_settings_save_wib_inputs_as_utc_and_render_back_in_wib(): void
    {
        $this->patch($this->teacher.'/pengaturan', [
            'school_name' => 'SMA Nusantara', 'election_title' => 'Pemilihan OSIS Nusantara', 'osis_period' => '2026/2027',
            'opens_at' => '2026-11-02T08:30', 'closes_at' => '2026-11-02T10:00',
            'report_place' => 'Jakarta', 'committee_chair' => 'Ibu Alya', 'osis_advisor' => 'Pak Bima', 'principal_name' => 'Ibu Citra',
        ])->assertRedirect($this->teacher.'/pengaturan')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('election_settings', ['school_name' => 'SMA Nusantara', 'opens_at' => '2026-11-02 01:30:00', 'closes_at' => '2026-11-02 03:00:00']);
        $setting = ElectionSetting::current();
        $this->assertSame('UTC', $setting->opens_at->timezoneName);
        $this->assertSame('2026-11-02T01:30:00+00:00', $setting->opens_at->toIso8601String());
        $this->get($this->teacher.'/pengaturan')->assertOk()->assertSee('2026-11-02T08:30')->assertSee('SMA Nusantara');
    }

    public function test_schedule_requires_both_dates_and_a_later_closing_time(): void
    {
        $this->patch($this->teacher.'/pengaturan', ['opens_at' => '2026-11-02T08:00'])->assertSessionHasErrors('closes_at');
        $this->patch($this->teacher.'/pengaturan', ['closes_at' => '2026-11-02T10:00'])->assertSessionHasErrors('opens_at');
        $this->patch($this->teacher.'/pengaturan', ['opens_at' => '2026-11-02T08:00', 'closes_at' => '2026-11-02T08:00'])->assertSessionHasErrors('closes_at');
        $this->assertNull(ElectionSetting::current()->opens_at);
        $this->schedule();
        $this->patch($this->teacher.'/pengaturan', ['opens_at' => '', 'closes_at' => ''])->assertSessionHasNoErrors();
        $this->assertNull(ElectionSetting::current()->opens_at);
        $this->assertNull(ElectionSetting::current()->closes_at);
    }

    public function test_logo_is_private_validated_and_can_be_replaced_or_removed(): void
    {
        $this->post($this->teacher.'/pengaturan', ['_method' => 'PATCH', 'logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
        $first = ElectionSetting::current()->logo_path;
        $this->assertStringStartsWith('school-logos/', $first);
        Storage::disk('local')->assertExists($first);
        $this->get('/logo-sekolah')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->post($this->teacher.'/pengaturan', ['_method' => 'PATCH', 'logo' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        $second = ElectionSetting::current()->logo_path;
        $this->patch($this->teacher.'/pengaturan', ['remove_logo' => '1'])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($second);
        $this->get('/logo-sekolah')->assertNotFound();
        $this->post($this->teacher.'/pengaturan', ['_method' => 'PATCH', 'logo' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->post($this->teacher.'/pengaturan', ['_method' => 'PATCH', 'logo' => UploadedFile::fake()->image('large.png')->size(2049)])->assertSessionHasErrors('logo');
        $this->assertNull(ElectionSetting::current()->logo_path);
    }

    public function test_new_schedule_must_start_in_the_future_but_unchanged_due_schedule_can_keep_branding_edits(): void
    {
        $this->patch($this->teacher.'/pengaturan', ['opens_at' => '2026-11-02T06:59', 'closes_at' => '2026-11-02T09:00'])->assertSessionHasErrors('opens_at');
        $this->assertNull(ElectionSetting::current()->opens_at);
        $this->schedule();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:35:00', 'Asia/Jakarta'));
        // There are no students/candidates, so readiness keeps this in preparation.
        $this->patch($this->teacher.'/pengaturan', [
            'school_name' => 'Sekolah Baru', 'opens_at' => '2026-11-02T07:30', 'closes_at' => '2026-11-02T09:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Sekolah Baru', ElectionSetting::current()->school_name);
        $this->assertSame('preparation', ElectionSetting::current()->status);
        $this->patch($this->teacher.'/pengaturan', ['opens_at' => '2026-11-02T07:30', 'closes_at' => '2026-11-02T09:30'])->assertSessionHasErrors('opens_at');
    }

    public function test_identity_freezes_but_signers_remain_editable_after_open_and_close(): void
    {
        ElectionSetting::current()->update(['school_name' => 'Sekolah Lama']);
        foreach (['open', 'closed'] as $status) {
            ElectionSetting::current()->update(['status' => $status]);
            $this->patch($this->teacher.'/pengaturan', ['school_name' => 'Sekolah Baru'])->assertSessionHasErrors('election');
            $this->patch($this->teacher.'/pengaturan', ['opens_at' => '2026-11-03T08:00', 'closes_at' => '2026-11-03T10:00'])->assertSessionHasErrors('election');
            $this->patch($this->teacher.'/pengaturan', ['report_place' => 'Jakarta', 'committee_chair' => 'Panitia Baru'])->assertSessionHasNoErrors();
            $this->assertSame('Sekolah Lama', ElectionSetting::current()->school_name);
            $this->assertSame('Panitia Baru', ElectionSetting::current()->committee_chair);
        }
    }

    public function test_schedule_opens_at_the_exact_boundary_and_closes_at_the_exact_boundary(): void
    {
        $this->readyElection();
        $this->schedule();
        $service = app(ElectionService::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:29:59', 'Asia/Jakarta'));
        $this->assertSame('preparation', $service->reconcile()->status);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:30:00', 'Asia/Jakarta'));
        $this->assertSame('open', $service->reconcile()->status);
        $this->assertSame('2026-11-02 00:30:00', ElectionSetting::current()->opened_at->format('Y-m-d H:i:s'));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:59:59', 'Asia/Jakarta'));
        $this->assertSame('open', $service->reconcile()->status);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Jakarta'));
        $this->assertSame('closed', $service->reconcile()->status);
        $this->assertSame('2026-11-02 02:00:00', ElectionSetting::current()->closed_at->format('Y-m-d H:i:s'));
        $this->assertSame('closed', $service->reconcile()->status);
    }

    public function test_opening_waits_for_readiness_and_never_opens_after_the_window(): void
    {
        $this->schedule();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:30:00', 'Asia/Jakarta'));
        $service = app(ElectionService::class);
        $this->assertSame('preparation', $service->reconcile()->status);
        $this->assertStringContainsString('kesiapan belum lengkap', $service->scheduleNotice());
        $this->readyElection();
        $this->assertSame('open', $service->reconcile()->status);
        ElectionSetting::current()->update(['status' => 'preparation', 'opened_at' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Jakarta'));
        $this->assertSame('closed', $service->reconcile()->status);
        $this->assertNull(ElectionSetting::current()->opened_at);
        $this->assertStringContainsString('sebelum pemilihan sempat dibuka', $service->scheduleNotice());
    }

    public function test_manual_opening_can_start_early_and_manual_close_is_final(): void
    {
        $this->readyElection();
        $this->schedule();
        $service = app(ElectionService::class);
        $service->open();
        $this->assertSame('open', ElectionSetting::current()->status);
        $service->close();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Jakarta'));
        $this->assertSame('closed', $service->reconcile()->status);
        $this->expectException(ValidationException::class);
        $service->open();
    }

    public function test_vote_rechecks_schedule_and_commits_automatic_closure_when_rejected(): void
    {
        [$student, $candidate] = $this->readyElection();
        $this->schedule();
        ElectionSetting::current()->update(['status' => 'open', 'opened_at' => now()->utc()]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Jakarta'));
        $context = ['student_id' => $student->id, 'candidate_id' => $candidate->id, 'token' => str_repeat('t', 64), 'expires_at' => now()->timestamp + 300];
        try {
            app(CastVoteService::class)->cast($context, $context['token']);
            $this->fail('Vote was accepted at the closing time.');
        } catch (VotingRejected $exception) {
            $this->assertStringContainsString('sudah ditutup', $exception->getMessage());
        }
        $this->assertSame('closed', ElectionSetting::current()->status);
        $this->assertNotNull(ElectionSetting::current()->closed_at);
        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('participations', 0);
    }

    public function test_preparation_guard_blocks_a_time_boundary_without_a_rolled_back_status_change(): void
    {
        $this->readyElection();
        $this->schedule();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:30:00', 'Asia/Jakarta'));
        try {
            DB::transaction(fn () => app(ElectionService::class)->requirePreparation());
            $this->fail('Preparation mutation was allowed at opening time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('election', $exception->errors());
        }
        $this->assertSame('preparation', ElectionSetting::current()->status);
        $this->assertSame('open', app(ElectionService::class)->reconcile()->status);
    }

    public function test_requests_and_reconcile_command_apply_the_schedule_without_a_running_worker(): void
    {
        $this->readyElection();
        $this->schedule();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:30:00', 'Asia/Jakarta'));
        $this->get('/')->assertOk()->assertSee('Sedang dibuka');
        $this->assertSame('open', ElectionSetting::current()->status);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Jakarta'));
        $this->artisan('election:reconcile')->assertSuccessful();
        $this->assertSame('closed', ElectionSetting::current()->status);
    }
}
