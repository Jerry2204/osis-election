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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReopenElectionTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->travelTo(CarbonImmutable::parse('2030-01-01 08:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $voted = Student::query()->create(['nis' => '0001', 'name' => 'Siswa Lama', 'class_name' => 'X A']);
        $pending = Student::query()->create(['nis' => '0002', 'name' => 'Siswa Belum Memilih', 'class_name' => 'X A']);
        $candidates = [];
        foreach ([1, 2] as $number) {
            Storage::disk('photos')->put($number.'.png', 'photo');
            $candidates[] = Candidate::query()->create(['number' => $number, 'name' => 'Calon '.$number, 'photo_path' => $number.'.png', 'vision' => 'Visi', 'mission' => 'Misi']);
        }
        Participation::query()->create(['student_id' => $voted->id]);
        $vote = Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidates[0]->id]);
        ElectionSetting::current()->update([
            'status' => 'closed', 'opened_at' => now()->utc()->subHour(), 'closed_at' => now()->utc()->subMinute(),
            'opens_at' => now()->utc()->subHour(), 'closes_at' => now()->utc()->subMinute(),
            'results_published' => true,
        ]);
        return [$voted, $pending, $candidates[0], $vote];
    }

    private function reopen(array $extra = [])
    {
        return $this->post($this->teacher.'/buka-kembali', ['confirmation' => 'BUKA KEMBALI'] + $extra);
    }

    public function test_reopening_preserves_votes_students_candidates_and_first_opening_time(): void
    {
        [, , $candidate, $vote] = $this->fixture();
        $firstOpened = ElectionSetting::current()->opened_at;
        $this->reopen()->assertRedirect($this->teacher)->assertSessionHasNoErrors()->assertSessionHas('status');
        $setting = ElectionSetting::current();
        $this->assertSame('open', $setting->status);
        $this->assertTrue($setting->opened_at->equalTo($firstOpened));
        $this->assertNull($setting->closed_at);
        $this->assertNull($setting->opens_at);
        $this->assertNull($setting->closes_at);
        $this->assertFalse($setting->results_published);
        $this->assertSame('open', app(ElectionService::class)->reconcile()->status);
        $this->assertDatabaseHas('votes', ['id' => $vote->id, 'candidate_id' => $candidate->id]);
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseCount('participations', 1);
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseCount('candidates', 2);
    }

    public function test_only_students_who_have_not_voted_can_add_votes_after_reopening(): void
    {
        [$voted, $pending, $candidate] = $this->fixture();
        $this->reopen()->assertSessionHasNoErrors();
        $this->post('/verifikasi-nis', ['nis' => $voted->nis])->assertSessionHas('notice', 'NIS ini sudah digunakan untuk memilih.');
        $context = ['student_id' => $voted->id, 'candidate_id' => $candidate->id, 'token' => str_repeat('t', 64), 'expires_at' => now()->timestamp + 300];
        try {
            app(CastVoteService::class)->cast($context, $context['token']);
            $this->fail('A previous voter was allowed to vote again.');
        } catch (VotingRejected $exception) {
            $this->assertStringContainsString('sudah digunakan', $exception->getMessage());
        }
        $context['student_id'] = $pending->id;
        app(CastVoteService::class)->cast($context, $context['token']);
        $this->assertDatabaseCount('votes', 2);
        $this->assertDatabaseCount('participations', 2);
    }

    public function test_results_and_publication_stay_hidden_until_closed_and_explicitly_published_again(): void
    {
        $this->fixture();
        $this->get('/pengumuman')->assertOk();
        $this->reopen()->assertSessionHasNoErrors();
        foreach (['/rekap', '/rekap.csv', '/rekap.pdf', '/pengumuman/pratinjau'] as $path) {
            $this->get($this->teacher.$path)->assertForbidden();
        }
        $this->get('/pengumuman')->assertNotFound()->assertDontSee('Calon 1');
        $this->get('/pengumuman/status')->assertExactJson(['available' => false]);
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasErrors('election');
        $this->post($this->teacher.'/tutup', ['confirmation' => 'TUTUP'])->assertRedirect($this->teacher.'/rekap');
        $this->get($this->teacher.'/rekap')->assertOk();
        $this->get('/pengumuman')->assertNotFound();
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasNoErrors();
        $this->get('/pengumuman')->assertOk();
    }

    public function test_reopening_uses_a_new_wib_closing_time_and_closes_at_the_new_deadline(): void
    {
        $this->fixture();
        $this->reopen(['reopen_closes_at' => '2030-01-01T09:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('election_settings', ['status' => 'open', 'opens_at' => '2030-01-01 01:00:00', 'closes_at' => '2030-01-01 02:00:00']);
        $this->assertSame('open', app(ElectionService::class)->reconcile()->status);
        $this->travelTo(CarbonImmutable::parse('2030-01-01 09:00:00', 'Asia/Jakarta'));
        $setting = app(ElectionService::class)->reconcile();
        $this->assertSame('closed', $setting->status);
        $this->assertFalse($setting->results_published);
        $this->assertSame('2030-01-01 02:00:00', $setting->closed_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_reopening_requires_teacher_access_confirmation_and_a_future_deadline(): void
    {
        $this->fixture();
        $this->post('/ruang-guru/'.str_repeat('b', 64).'/buka-kembali', ['confirmation' => 'BUKA KEMBALI'])->assertNotFound();
        $this->post($this->teacher.'/buka-kembali')->assertSessionHasErrors('confirmation');
        $this->post($this->teacher.'/buka-kembali', ['confirmation' => 'BUKA'])->assertSessionHasErrors('confirmation');
        foreach (['invalid', '2030-01-01T07:00', '2030-01-01T08:00'] as $deadline) {
            $this->reopen(['reopen_closes_at' => $deadline])->assertSessionHasErrors('reopen_closes_at');
            $this->assertSame('closed', ElectionSetting::current()->status);
            $this->assertTrue(ElectionSetting::current()->results_published);
        }
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_reopening_rejects_preparation_and_already_open_elections(): void
    {
        $this->fixture();
        foreach (['preparation', 'open'] as $status) {
            ElectionSetting::current()->update(['status' => $status, 'opens_at' => null, 'closes_at' => null]);
            $this->reopen()->assertSessionHasErrors('election');
            $this->assertSame($status, ElectionSetting::current()->status);
        }
    }

    public function test_missing_photos_or_inconsistent_counts_block_reopening_without_modifying_data(): void
    {
        [, $pending] = $this->fixture();
        Storage::disk('photos')->delete('2.png');
        $this->reopen()->assertSessionHasErrors('election');
        $this->assertSame('closed', ElectionSetting::current()->status);
        Storage::disk('photos')->put('2.png', 'photo');
        Participation::query()->create(['student_id' => $pending->id]);
        $this->reopen()->assertSessionHasErrors('election');
        $this->assertSame('closed', ElectionSetting::current()->status);
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseCount('participations', 2);
    }

    public function test_student_candidate_and_identity_data_remain_locked_and_forms_render_on_closed_pages(): void
    {
        [, , $candidate] = $this->fixture();
        $this->get($this->teacher)->assertOk()->assertSee('BUKA KEMBALI')->assertSee('name="reopen_closes_at"', false);
        $this->get($this->teacher.'/rekap')->assertOk()->assertSee('id="reopen-election"', false);
        $this->reopen()->assertSessionHasNoErrors();
        $this->get($this->teacher)->assertOk()->assertDontSee('id="reopen-election"', false);
        $this->patch($this->teacher.'/calon/'.$candidate->id, [])->assertForbidden();
        $this->post($this->teacher.'/siswa/impor', ['file' => UploadedFile::fake()->createWithContent('siswa.csv', "nis,nama,kelas\n003,Siswa,X A\n")])->assertForbidden();
        $this->patch($this->teacher.'/pengaturan', ['school_name' => 'Diubah'])->assertSessionHasErrors('election');
    }

    public function test_a_never_opened_but_ready_election_can_be_started_again(): void
    {
        $this->fixture();
        Vote::query()->delete();
        Participation::query()->delete();
        ElectionSetting::current()->update(['opened_at' => null]);
        $this->reopen()->assertSessionHasNoErrors();
        $this->assertSame('open', ElectionSetting::current()->status);
        $this->assertSame(now()->utc()->timestamp, ElectionSetting::current()->opened_at->timestamp);
    }
}
