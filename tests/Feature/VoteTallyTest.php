<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use App\Services\CastVoteService;
use App\Services\ElectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoteTallyTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function fixture(array $counts = [2, 1, 0]): array
    {
        Storage::fake('photos');
        $candidates = [];
        foreach ($counts as $index => $count) {
            Storage::disk('photos')->put($index.'.png', 'photo');
            $candidate = Candidate::query()->create(['number' => $index + 1, 'name' => 'Calon '.($index + 1), 'photo_path' => $index.'.png', 'vision' => 'Visi', 'mission' => 'Misi']);
            $candidates[] = $candidate;
            for ($i = 0; $i < $count; $i++) Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
        }
        for ($i = 0; $i <= array_sum($counts); $i++) {
            $student = Student::query()->create(['nis' => sprintf('00000%d', $i), 'name' => 'IDENTITAS RAHASIA SISWA', 'class_name' => 'X A']);
            if ($i < array_sum($counts)) Participation::query()->create(['student_id' => $student->id]);
        }
        ElectionSetting::current()->update(['status' => 'open']);
        return [$candidates, $student];
    }

    public function test_teacher_sees_live_vote_counts_percentages_and_zero_vote_candidates(): void
    {
        $this->fixture();
        $response = $this->get($this->teacher.'/perolehan-suara')->assertOk()
            ->assertJsonPath('status', 'open')->assertJsonPath('total_votes', 3)
            ->assertJsonPath('candidates.0.number', 1)->assertJsonPath('candidates.0.votes', 2)->assertJsonPath('candidates.0.percentage', 66.7)
            ->assertJsonPath('candidates.1.votes', 1)->assertJsonPath('candidates.1.percentage', 33.3)
            ->assertJsonPath('candidates.2.votes', 0)->assertJsonPath('candidates.2.percentage', 0);
        $this->assertEqualsCanonicalizing(['status', 'total_votes', 'candidates', 'updated_at'], array_keys($response->json()));
        $this->assertEqualsCanonicalizing(['id', 'number', 'name', 'votes', 'percentage'], array_keys($response->json('candidates.0')));
        $response->assertDontSee('IDENTITAS RAHASIA SISWA')->assertDontSee('student_id')->assertDontSee('photo_path');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get($this->teacher)->assertOk()->assertSee('Perolehan suara sementara')->assertSee('data-live-tally', false)->assertSee('66,7%');
    }

    public function test_next_snapshot_includes_committed_votes_without_changing_existing_data(): void
    {
        [$candidates, $pending] = $this->fixture([1, 1]);
        $this->get($this->teacher.'/perolehan-suara')->assertJsonPath('total_votes', 2)->assertJsonPath('candidates.1.votes', 1);
        $context = ['student_id' => $pending->id, 'candidate_id' => $candidates[1]->id, 'token' => str_repeat('t', 64), 'expires_at' => now()->timestamp + 300];
        app(CastVoteService::class)->cast($context, $context['token']);
        $this->get($this->teacher.'/perolehan-suara')->assertJsonPath('total_votes', 3)
            ->assertJsonPath('candidates.1.votes', 2)->assertJsonPath('candidates.1.percentage', 66.7);
        $this->assertDatabaseCount('votes', 3);
        $this->assertDatabaseCount('participations', 3);
    }

    public function test_public_and_wrong_teacher_cannot_access_temporary_tallies(): void
    {
        $this->fixture();
        $this->get('/perolehan-suara')->assertNotFound();
        $this->get('/ruang-guru/'.str_repeat('b', 64).'/perolehan-suara')->assertNotFound();
        $this->get('/status-pemilihan')->assertOk()->assertDontSee('total_votes')->assertDontSee('candidates');
        $this->get('/pengumuman')->assertNotFound()->assertDontSee('Calon 1');
        $this->get('/pengumuman/status')->assertExactJson(['available' => false]);
        $this->get('/')->assertOk()->assertDontSee('data-live-tally', false)->assertDontSee('/perolehan-suara');
        foreach (['/rekap', '/rekap.csv', '/rekap.pdf'] as $path) $this->get($this->teacher.$path)->assertForbidden();
    }

    public function test_participation_progress_stays_independent_of_vote_tallies(): void
    {
        $this->fixture();
        $this->get($this->teacher.'/progres')->assertOk()->assertJsonPath('participated', 3)
            ->assertDontSee('votes_count')->assertDontSee('candidates')->assertDontSee('total_votes');
    }

    public function test_preparation_and_zero_vote_states_are_supported(): void
    {
        $this->get($this->teacher.'/perolehan-suara')->assertOk()->assertJsonPath('status', 'preparation')
            ->assertJsonPath('total_votes', 0)->assertJsonPath('candidates', []);
        $this->get($this->teacher)->assertOk()->assertSee('Belum ada calon terdaftar.');
        $this->fixture([0, 0]);
        $this->get($this->teacher.'/perolehan-suara')->assertOk()->assertJsonPath('total_votes', 0)
            ->assertJsonPath('candidates.0.percentage', 0)->assertJsonPath('candidates.1.percentage', 0);
    }

    public function test_closing_and_reopening_change_labels_but_preserve_running_totals(): void
    {
        $this->fixture();
        app(ElectionService::class)->close();
        $this->get($this->teacher.'/perolehan-suara')->assertJsonPath('status', 'closed')->assertJsonPath('total_votes', 3);
        $this->get($this->teacher)->assertOk()->assertSee('Perolehan suara akhir');
        app(ElectionService::class)->reopen();
        $this->get($this->teacher.'/perolehan-suara')->assertJsonPath('status', 'open')->assertJsonPath('total_votes', 3);
        $this->get($this->teacher)->assertOk()->assertSee('Perolehan suara sementara');
        $this->assertFalse(ElectionSetting::current()->results_published);
    }

    public function test_candidate_names_are_escaped_in_server_rendered_tally(): void
    {
        [$candidates] = $this->fixture();
        $candidates[0]->update(['name' => '<script>calon()</script>']);
        $this->get($this->teacher)->assertOk()->assertSee('&lt;script&gt;calon()', false)->assertDontSee('<script>calon()', false);
    }
}
