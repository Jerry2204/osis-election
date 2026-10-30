<?php

namespace Tests\Feature;

use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_progress_snapshot_includes_only_turnout_totals_and_classes(): void
    {
        $one = Student::query()->create(['nis' => '001', 'name' => 'Nama Rahasia', 'class_name' => 'X A']);
        Student::query()->create(['nis' => '002', 'name' => 'Siswa Dua', 'class_name' => 'X A']);
        Student::query()->create(['nis' => '003', 'name' => 'Siswa Tiga', 'class_name' => 'X B']);
        Participation::query()->create(['student_id' => $one->id]);
        ElectionSetting::current()->update(['status' => 'open']);
        $response = $this->get($this->teacher.'/progres')->assertOk()
            ->assertJsonPath('registered', 3)->assertJsonPath('participated', 1)->assertJsonPath('remaining', 2)
            ->assertJsonPath('percentage', 33.3)->assertJsonPath('classes.0.class_name', 'X A')
            ->assertJsonPath('classes.0.percentage', 50)->assertJsonPath('classes.1.participated', 0);
        $this->assertEqualsCanonicalizing(['registered', 'participated', 'remaining', 'percentage', 'classes', 'status', 'updated_at'], array_keys($response->json()));
        $response->assertDontSee('Nama Rahasia')->assertDontSee('candidate_id')->assertDontSee('votes_count');
        $this->get($this->teacher)->assertOk()->assertSee('Partisipasi per kelas')->assertSee('Belum memilih');
    }

    public function test_progress_requires_teacher_key_and_handles_no_students(): void
    {
        $this->get('/ruang-guru/'.str_repeat('b', 64).'/progres')->assertNotFound();
        $this->get($this->teacher.'/progres')->assertOk()->assertJsonPath('registered', 0)->assertJsonPath('classes', [])
            ->assertJsonPath('remaining', 0)->assertJsonPath('percentage', 0);
    }

    public function test_public_status_has_no_participation_personal_or_teacher_data(): void
    {
        $response = $this->get('/status-pemilihan')->assertOk()->assertJsonPath('status', 'preparation');
        $this->assertEqualsCanonicalizing(['status', 'server_time', 'opens_at', 'closes_at'], array_keys($response->json()));
        $response->assertDontSee('teacher')->assertDontSee('registered')->assertDontSee('students');
        $this->get('/')->assertOk()->assertSee('data-election-watch', false)->assertDontSee('/ruang-guru/');
    }

    public function test_forms_render_field_errors_linked_to_inputs(): void
    {
        $errors = (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['name' => 'Nama wajib diisi.']));
        $this->withSession(['errors' => $errors])->get($this->teacher.'/calon/tambah')
            ->assertOk()->assertSee('id="name-error"', false)->assertSee('aria-describedby="name-error"', false)->assertSee('href="#name"', false);
    }

    public function test_voter_session_does_not_outlast_scheduled_closure(): void
    {
        Student::query()->create(['nis' => '0001', 'name' => 'Siswa', 'class_name' => 'X A']);
        $closing = now()->utc()->addMinute()->startOfSecond();
        ElectionSetting::current()->update(['status' => 'open', 'closes_at' => $closing]);
        $this->post('/verifikasi-nis', ['nis' => '0001'])->assertRedirect('/calon');
        $this->assertSame($closing->timestamp, session('voting.expires_at'));
    }
}
