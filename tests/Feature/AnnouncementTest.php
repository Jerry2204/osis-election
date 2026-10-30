<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function fixture(array $counts = [2, 1]): void
    {
        Storage::fake('photos');
        ElectionSetting::current()->update(['status' => 'closed', 'school_name' => 'Sekolah Simulasi', 'osis_period' => '2026/2027']);
        foreach ($counts as $index => $count) {
            $candidate = Candidate::query()->create([
                'number' => $index + 1, 'name' => 'Calon Rahasia '.($index + 1),
                'photo_path' => 'test.png', 'vision' => 'Visi', 'mission' => 'Misi',
            ]);
            for ($i = 0; $i < $count; $i++) {
                Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
            }
        }
        for ($i = 0; $i <= array_sum($counts); $i++) {
            $student = Student::query()->create(['nis' => sprintf('00000%d', $i), 'name' => 'IDENTITAS-SISWA-RAHASIA', 'class_name' => 'X A']);
            if ($i < array_sum($counts)) Participation::query()->create(['student_id' => $student->id]);
        }
    }

    public function test_publication_defaults_to_disabled_and_no_results_are_exposed(): void
    {
        $this->assertFalse(ElectionSetting::current()->results_published);
        $this->fixture();
        $response = $this->get('/pengumuman')->assertNotFound()->assertSee('Pengumuman')->assertSee('belum tersedia.')
            ->assertDontSee('Calon Rahasia')->assertDontSee('IDENTITAS-SISWA-RAHASIA')->assertDontSee('/ruang-guru/');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/pengumuman/status')->assertOk()->assertExactJson(['available' => false]);
    }

    public function test_preparation_and_open_elections_cannot_publish_even_with_a_stale_flag(): void
    {
        foreach (['preparation', 'open'] as $status) {
            ElectionSetting::current()->update(['status' => $status, 'results_published' => true]);
            $this->get('/pengumuman')->assertNotFound()->assertDontSee('Total suara');
            $this->get('/pengumuman/status')->assertExactJson(['available' => false]);
            $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasErrors('election');
            $this->get($this->teacher.'/pengumuman/pratinjau')->assertForbidden();
        }
    }

    public function test_only_teacher_can_control_or_preview_announcement_and_payload_is_validated(): void
    {
        $this->fixture();
        $wrong = '/ruang-guru/'.str_repeat('b', 64).'/pengumuman';
        $this->post($wrong, ['enabled' => '1'])->assertNotFound();
        $this->get($wrong.'/pratinjau')->assertNotFound();
        $this->post($this->teacher.'/pengumuman', [])->assertSessionHasErrors('enabled');
        $this->post($this->teacher.'/pengumuman', ['enabled' => 'invalid'])->assertSessionHasErrors('enabled');
        $this->assertFalse(ElectionSetting::current()->results_published);
    }

    public function test_teacher_can_preview_without_activating_the_public_screen(): void
    {
        $this->fixture();
        $this->get($this->teacher.'/pengumuman/pratinjau')->assertOk()->assertSee('Pratinjau panitia')
            ->assertSee('Calon Rahasia 1')->assertSee('data-preview="true"', false);
        $this->assertFalse(ElectionSetting::current()->results_published);
        $this->get('/pengumuman')->assertNotFound()->assertDontSee('Calon Rahasia 1');
        $this->get($this->teacher.'/rekap')->assertOk()->assertSee('Aktifkan pengumuman')->assertSee('Pratinjau layar');
    }

    public function test_activated_screen_contains_graph_percentages_branding_and_no_student_or_teacher_data(): void
    {
        $this->fixture([1, 2]);
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertRedirect($this->teacher.'/rekap')->assertSessionHasNoErrors();
        $this->assertTrue(ElectionSetting::current()->results_published);
        $response = $this->get('/pengumuman')->assertOk()->assertSee('Sekolah Simulasi')->assertSee('2026/2027')
            ->assertSee('66,7')->assertSee('33,3')->assertSee('75,0')->assertSee('Perolehan tertinggi')
            ->assertSee('class="vote-bar"', false)->assertSee('data-fullscreen', false)
            ->assertDontSee('IDENTITAS-SISWA-RAHASIA')->assertDontSee('000000')->assertDontSee('/ruang-guru/')
            ->assertDontSee(config('election.teacher_key'))->assertDontSee('Aktifkan pengumuman');
        $response->assertViewHas('rankedCandidates', fn ($candidates) => $candidates->pluck('number')->all() === [2, 1]);
        $this->get('/pengumuman/status')->assertExactJson(['available' => true]);
        $this->get('/')->assertOk()->assertSee('Lihat pengumuman hasil')->assertDontSee('/ruang-guru/');
        $this->get($this->teacher.'/rekap')->assertOk()->assertSee('Sembunyikan pengumuman')->assertSee('Buka layar pengumuman');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_tied_leaders_are_all_marked_without_declaring_a_single_winner(): void
    {
        $this->fixture([1, 1]);
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasNoErrors();
        $response = $this->get('/pengumuman')->assertOk()->assertSee('Perolehan suara tertinggi sama.')
            ->assertSee('50,0')->assertDontSee('Pemenang');
        $this->assertSame(2, substr_count($response->getContent(), '>Tertinggi bersama</span>'));
        $response->assertViewHas('rankedCandidates', fn ($candidates) => $candidates->pluck('number')->all() === [1, 2]);
    }

    public function test_zero_votes_and_no_candidates_have_explicit_empty_states(): void
    {
        $this->fixture([0, 0]);
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasNoErrors();
        $this->get('/pengumuman')->assertOk()->assertSee('Belum ada suara tercatat.')
            ->assertDontSee('class="leader-label"', false)->assertSee('0,0');
        Candidate::query()->delete();
        $this->get('/pengumuman')->assertOk()->assertSee('Tidak ada calon terdaftar');
    }

    public function test_inconsistent_results_block_publication_and_hide_an_already_published_screen(): void
    {
        $this->fixture();
        Participation::query()->delete();
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasErrors('election');
        $this->assertFalse(ElectionSetting::current()->results_published);
        ElectionSetting::current()->update(['results_published' => true]);
        $this->get('/pengumuman')->assertNotFound()->assertDontSee('Calon Rahasia');
        $this->get('/pengumuman/status')->assertExactJson(['available' => false]);
        $this->get($this->teacher.'/pengumuman/pratinjau')->assertForbidden();
    }

    public function test_withdrawal_hides_results_and_keeps_all_votes_intact(): void
    {
        $this->fixture();
        $this->post($this->teacher.'/pengumuman', ['enabled' => '1'])->assertSessionHasNoErrors();
        $this->get('/pengumuman')->assertOk();
        $this->post($this->teacher.'/pengumuman', ['enabled' => '0'])->assertRedirect($this->teacher.'/rekap')->assertSessionHasNoErrors();
        $this->assertFalse(ElectionSetting::current()->results_published);
        $this->get('/pengumuman/status')->assertExactJson(['available' => false]);
        $this->get('/pengumuman')->assertNotFound()->assertDontSee('Calon Rahasia');
        $this->assertDatabaseCount('votes', 3);
        $this->assertDatabaseCount('participations', 3);
        $this->assertSame('closed', ElectionSetting::current()->status);
    }

    public function test_candidate_and_school_text_are_escaped(): void
    {
        $this->fixture();
        ElectionSetting::current()->update(['school_name' => '<script>sekolah()</script>', 'results_published' => true]);
        Candidate::query()->firstOrFail()->update(['name' => '<script>calon()</script>']);
        $this->get('/pengumuman')->assertOk()->assertSee('&lt;script&gt;calon()', false)
            ->assertSee('&lt;script&gt;sekolah()', false)->assertDontSee('<script>calon()', false)
            ->assertDontSee('<script>sekolah()', false);
    }
}
