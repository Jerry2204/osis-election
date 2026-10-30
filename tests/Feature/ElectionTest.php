<?php

namespace Tests\Feature;

use App\Exceptions\VotingRejected;
use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use App\Services\CastVoteService;
use App\Services\ElectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ElectionTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
    }

    private function student(string $nis = '001001'): Student
    {
        return Student::query()->create(['nis' => $nis, 'name' => 'Siswa Uji', 'class_name' => 'X A']);
    }

    private function candidate(int $number = 1): Candidate
    {
        $photo = UploadedFile::fake()->image('photo.png');
        Storage::disk('photos')->put("{$number}.png", file_get_contents($photo->path()));
        return Candidate::query()->create(['number' => $number, 'name' => 'Calon '.$number, 'photo_path' => "{$number}.png", 'vision' => 'Sekolah yang ramah.', 'mission' => "Dengar siswa.\nKerja bersama."]);
    }

    private function openElection(): void
    {
        ElectionSetting::current()->update(['status' => 'open']);
    }

    private function context(Student $student, Candidate $candidate): array
    {
        return ['student_id' => $student->id, 'candidate_id' => $candidate->id, 'token' => Str::random(64), 'expires_at' => now()->timestamp + 300];
    }

    public function test_public_home_is_local_private_and_does_not_disclose_teacher_url(): void
    {
        $this->get('/')->assertOk()->assertSee('Suaramu,')->assertSee('Belum dibuka')
            ->assertDontSee('/ruang-guru/')->assertDontSee(config('election.teacher_key'))
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $this->get('/')->headers->get('Cache-Control'));
    }

    public function test_full_student_flow_preserves_leading_zero_and_erases_context(): void
    {
        $this->student();
        $candidate = $this->candidate();
        $this->openElection();
        $this->post('/verifikasi-nis', ['nis' => '001001'])->assertRedirect('/calon');
        $this->get('/calon')->assertOk()->assertSee('Calon 1');
        $context = session('voting');
        $this->post('/pilihan', ['candidate_id' => $candidate->id, 'flow_token' => $context['token']])->assertRedirect('/konfirmasi');
        $this->get('/konfirmasi')->assertOk();
        $this->post('/suara', ['flow_token' => session('voting.token')])->assertRedirect('/berhasil')->assertSessionMissing('voting');
        $this->get('/berhasil')->assertOk()->assertDontSee('001001')->assertDontSee('Calon 1');
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseCount('participations', 1);
        $this->post('/verifikasi-nis', ['nis' => '001001'])->assertSessionHas('notice', 'NIS ini sudah digunakan untuk memilih.');
    }

    public function test_unknown_nis_and_nis_without_leading_zeros_are_rejected(): void
    {
        $this->student();
        $this->openElection();
        $this->post('/verifikasi-nis', ['nis' => '1001'])->assertRedirect('/')->assertSessionHas('notice', 'NIS tidak terdaftar. Silakan hubungi petugas.')->assertSessionMissing('voting');
        $this->post('/verifikasi-nis', ['nis' => ['001001']])->assertRedirect('/')->assertSessionMissing('voting');
        $this->post('/verifikasi-nis', ['nis' => '01 001'])->assertRedirect('/')->assertSessionMissing('voting');
    }

    public function test_old_tab_and_expired_context_cannot_submit(): void
    {
        $student = $this->student();
        $candidate = $this->candidate();
        $this->openElection();
        $context = $this->context($student, $candidate);
        $this->withSession(['voting' => $context])->post('/suara', ['flow_token' => 'old-tab'])->assertRedirect('/')->assertSessionMissing('voting');
        $context['expires_at'] = now()->timestamp - 1;
        $this->withSession(['voting' => $context])->post('/suara', ['flow_token' => $context['token']])->assertRedirect('/')->assertSessionMissing('voting');
        $this->assertDatabaseCount('votes', 0);
    }

    public function test_duplicate_vote_is_rejected_even_when_original_context_is_replayed(): void
    {
        $student = $this->student();
        $candidate = $this->candidate();
        $this->openElection();
        $context = $this->context($student, $candidate);
        $service = app(CastVoteService::class);
        $service->cast($context, $context['token']);
        try {
            $service->cast($context, $context['token']);
            $this->fail('Duplicate vote was accepted.');
        } catch (VotingRejected $exception) {
            $this->assertStringContainsString('sudah digunakan', $exception->getMessage());
        }
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseCount('participations', 1);
    }

    public function test_vote_insert_failure_rolls_back_participation(): void
    {
        $student = $this->student();
        $candidate = $this->candidate();
        $this->openElection();
        $context = $this->context($student, $candidate);
        Vote::creating(fn () => throw new RuntimeException('Simulated disk/write failure'));
        try {
            app(CastVoteService::class)->cast($context, $context['token']);
            $this->fail('Failure was not triggered.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated disk/write failure', $exception->getMessage());
        } finally {
            Vote::flushEventListeners();
        }
        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('participations', 0);
    }

    public function test_closed_election_rejects_inflight_vote(): void
    {
        $context = $this->context($this->student(), $this->candidate());
        ElectionSetting::current()->update(['status' => 'closed']);
        $this->expectException(VotingRejected::class);
        app(CastVoteService::class)->cast($context, $context['token']);
    }

    public function test_confirmation_cannot_silently_change_after_another_tab_changes_choice(): void
    {
        $context = $this->context($this->student(), $this->candidate());
        $other = $this->candidate(2);
        $this->openElection();
        $this->withSession(['voting' => $context])->post('/pilihan', ['candidate_id' => $other->id, 'flow_token' => $context['token']])->assertRedirect('/konfirmasi');
        $this->post('/suara', ['flow_token' => $context['token']])->assertRedirect('/');
        $this->assertDatabaseCount('votes', 0);
    }

    public function test_home_and_cancel_erase_previous_voter_and_invalid_candidate_is_rejected(): void
    {
        $context = $this->context($this->student(), $this->candidate());
        $this->openElection();
        $this->withSession(['voting' => $context])->post('/pilihan', ['candidate_id' => 999999, 'flow_token' => $context['token']])->assertRedirect('/calon');
        $this->post('/batalkan')->assertRedirect('/')->assertSessionMissing('voting');
        $this->withSession(['voting' => $context])->get('/')->assertSessionMissing('voting');
        $this->get('/konfirmasi')->assertRedirect('/');
        $this->assertDatabaseCount('votes', 0);
    }

    public function test_teacher_key_is_required_but_not_login_or_ip(): void
    {
        $this->get('/ruang-guru/'.str_repeat('b', 64))->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.12'])->get($this->teacher)->assertOk();
        $this->get($this->teacher.'/calon')->assertOk();
    }

    public function test_teacher_can_create_update_preview_and_delete_candidate(): void
    {
        $this->post($this->teacher.'/calon', ['number' => 1, 'name' => 'Alya', 'vision' => 'Sekolah ramah', 'mission' => 'Dengar siswa', 'photo' => UploadedFile::fake()->image('portrait.png')])->assertRedirect($this->teacher.'/calon');
        $candidate = Candidate::query()->sole();
        Storage::disk('photos')->assertExists($candidate->photo_path);
        $this->get($this->teacher.'/calon/'.$candidate->id.'/edit')->assertOk();
        $this->get('/foto-calon/'.$candidate->id)->assertOk();
        $this->patch($this->teacher.'/calon/'.$candidate->id, ['number' => 2, 'name' => 'Alya Putri', 'vision' => 'Visi baru', 'mission' => 'Misi baru'])->assertRedirect($this->teacher.'/calon');
        $this->assertDatabaseHas('candidates', ['name' => 'Alya Putri', 'number' => 2]);
        $this->delete($this->teacher.'/calon/'.$candidate->id)->assertRedirect($this->teacher.'/calon');
        $this->assertDatabaseCount('candidates', 0);
        Storage::disk('photos')->assertMissing($candidate->photo_path);
    }

    public function test_duplicate_number_bad_photo_and_oversized_photo_are_rejected(): void
    {
        $this->candidate();
        $data = ['number' => 1, 'name' => 'Calon', 'vision' => 'Visi', 'mission' => 'Misi', 'photo' => UploadedFile::fake()->create('bad.png', 10, 'text/plain')];
        $this->post($this->teacher.'/calon', $data)->assertSessionHasErrors(['number', 'photo']);
        $data['number'] = 2;
        $data['photo'] = UploadedFile::fake()->image('large.png')->size(2049);
        $this->post($this->teacher.'/calon', $data)->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_readiness_opening_locking_and_closing(): void
    {
        $this->post($this->teacher.'/buka', ['confirmation' => 'BUKA'])->assertSessionHasErrors('election');
        $this->student();
        $candidate = $this->candidate();
        $this->candidate(2);
        $this->post($this->teacher.'/buka', ['confirmation' => 'BUKA'])->assertRedirect($this->teacher);
        $this->assertSame('open', ElectionSetting::current()->status);
        $this->delete($this->teacher.'/calon/'.$candidate->id)->assertSessionHasErrors('election');
        $this->patch($this->teacher.'/calon/'.$candidate->id, [])->assertForbidden();
        $this->post($this->teacher.'/tutup', ['confirmation' => 'TUTUP'])->assertRedirect($this->teacher.'/rekap');
        $this->assertSame('closed', ElectionSetting::current()->status);
        $this->post($this->teacher.'/buka', ['confirmation' => 'BUKA'])->assertSessionHasErrors('election');
    }

    public function test_missing_photo_prevents_opening(): void
    {
        $this->student();
        $candidate = $this->candidate();
        $this->candidate(2);
        Storage::disk('photos')->delete($candidate->photo_path);
        $this->post($this->teacher.'/buka', ['confirmation' => 'BUKA'])->assertSessionHasErrors('election');
        $this->assertSame('preparation', ElectionSetting::current()->status);
    }

    public function test_results_hidden_until_closed_and_export_includes_zero_vote_candidate(): void
    {
        $student = $this->student();
        $candidate = $this->candidate();
        $this->candidate(2);
        $this->openElection();
        $this->get($this->teacher.'/rekap')->assertForbidden();
        $this->get($this->teacher.'/rekap.csv')->assertForbidden();
        $context = $this->context($student, $candidate);
        app(CastVoteService::class)->cast($context, $context['token']);
        app(ElectionService::class)->close();
        $this->get($this->teacher.'/rekap')->assertOk()->assertSee('Calon 2')->assertDontSee('001001')->assertDontSee('Siswa Uji');
        $export = $this->get($this->teacher.'/rekap.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('2,"Calon 2",0', $export);
        $this->assertStringNotContainsString('001001', $export);
    }

    public function test_schema_has_no_student_to_vote_link_and_has_database_constraints(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'candidate_id'], Schema::getColumnListing('votes'));
        $this->assertSame(['student_id'], Schema::getColumnListing('participations'));
        $student = $this->student();
        Participation::query()->create(['student_id' => $student->id]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Participation::query()->create(['student_id' => $student->id]);
    }

    public function test_csv_import_is_atomic_and_preserves_nis(): void
    {
        $this->artisan('students:import', ['file' => base_path('docs/contoh-siswa.csv')])->assertSuccessful();
        $this->assertDatabaseHas('students', ['nis' => '001001']);
        $this->artisan('students:import', ['file' => base_path('docs/contoh-siswa.csv')])->assertFailed();
        $this->assertDatabaseCount('students', 3);
        $this->openElection();
        $this->artisan('students:import', ['file' => base_path('docs/contoh-siswa.csv')])->assertFailed();
        $this->assertDatabaseCount('students', 3);
    }

    public function test_invalid_or_duplicate_csv_rows_save_nothing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'osis-csv');
        try {
            file_put_contents($path, "nis,nama,kelas\n009,Alya,X A\n009,Bima,X B\n");
            $this->artisan('students:import', ['file' => $path])->assertFailed();
            $this->assertDatabaseCount('students', 0);
            file_put_contents($path, "nis,nama,kelas\n010,Alya,X A\nABC,Bima,X B\n");
            $this->artisan('students:import', ['file' => $path])->assertFailed();
            $this->assertDatabaseCount('students', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_csv_collision_in_later_chunk_rolls_back_earlier_chunk(): void
    {
        $this->student('999999');
        $path = tempnam(sys_get_temp_dir(), 'osis-csv');
        $contents = "nis,nama,kelas\n";
        for ($i = 1; $i <= 500; $i++) {
            $contents .= sprintf("%06d,Siswa,X A\n", $i);
        }
        file_put_contents($path, $contents."999999,Bentrok,X A\n");
        try {
            $this->artisan('students:import', ['file' => $path])->assertFailed();
            $this->assertDatabaseCount('students', 1);
        } finally {
            unlink($path);
        }
    }
}
