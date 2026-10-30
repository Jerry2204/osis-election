<?php

namespace Tests\Feature;

use App\Models\ElectionSetting;
use App\Models\Student;
use App\Services\StudentImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(string $contents, string $name = 'siswa.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function stage(string $contents): string
    {
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload($contents)])
            ->assertRedirect($this->teacher.'/siswa/impor/pratinjau')->assertSessionHasNoErrors();
        // Match a real browser: the framework's array test session otherwise keeps data while rotating its ID.
        $this->withCookie(config('session.cookie'), session()->getId());
        return session('student_import.token');
    }

    private function confirm(string $token)
    {
        return $this->post($this->teacher.'/siswa/impor/konfirmasi', ['import_token' => $token]);
    }

    public function test_teacher_can_open_import_page_and_download_empty_template(): void
    {
        $this->get($this->teacher)->assertOk()->assertSee('Data siswa');
        $this->get($this->teacher.'/siswa')->assertOk()->assertSee('Impor siswa dari CSV')->assertSee('Belum ada data siswa.');
        $response = $this->get($this->teacher.'/siswa/template.csv');
        $response->assertOk()->assertDownload('template-siswa.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertSame("nis,nama,kelas\n", file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->assertDatabaseCount('students', 0);
    }

    public function test_student_routes_require_the_teacher_key(): void
    {
        $wrong = '/ruang-guru/'.str_repeat('b', 64).'/siswa';
        $this->get($wrong)->assertNotFound();
        $this->get($wrong.'/template.csv')->assertNotFound();
        $this->get($wrong.'/impor/pratinjau')->assertNotFound();
        $this->post($wrong.'/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n")])->assertNotFound();
        $this->post($wrong.'/impor/konfirmasi', ['import_token' => str_repeat('a', 64)])->assertNotFound();
        $this->post($wrong.'/impor/batal')->assertNotFound();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_bulk_upload_previews_then_preserves_zeroes_utf8_quotes_and_trims_fields(): void
    {
        $csv = "\xEF\xBB\xBFnis,nama,kelas\r\n00001,\"Alya, Putri\",X A\r\n\r\n00002, Jos\u{00E9} , XI B \r\n";
        $token = $this->stage($csv);
        $this->assertDatabaseCount('students', 0);
        $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()->assertSee('00001')
            ->assertViewHas('inspection', fn ($value) => $value['records'] === 2 && $value['valid_count'] === 2 && $value['can_import']);
        $this->confirm($token)->assertRedirect($this->teacher.'/siswa')->assertSessionHasNoErrors()->assertSessionHas('status')->assertSessionMissing('student_import');
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseHas('students', ['nis' => '00001', 'name' => 'Alya, Putri']);
        $this->assertDatabaseHas('students', ['nis' => '00002', 'name' => "Jos\u{00E9}", 'class_name' => 'XI B']);
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
        $this->get($this->teacher.'/siswa')->assertOk()->assertSee('00001')->assertSee('Alya, Putri');
    }

    public function test_bad_csvs_are_previewed_with_errors_and_cannot_be_saved(): void
    {
        $cases = [
            ["nis;nama;kelas\n001;Alya;X A\n", 'Header CSV'],
            ["nis,nama,kelas\n", 'Tidak ada siswa'],
            ["nis,nama,kelas\n001,Alya,X A\nABC,Bima,X B\n", 'Baris 3'],
            ["nis,nama,kelas\n001,Alya,X A\n001,Bima,X B\n", 'NIS ganda'],
            ["nis,nama,kelas\n001,Alya,X A\n002,,X B\n", 'Baris 3'],
            ["nis,nama,kelas\n001,Alya,X A\n002,Bima,X B,tambahan\n", 'tiga kolom'],
            ["nis,nama,kelas\n001,Alya,X A\n002,Jos\xE9,X B\n", 'UTF-8'],
            ["nis,nama,kelas\n".str_repeat('1', 33).",Alya,X A\n", 'Baris 2'],
        ];
        foreach ($cases as [$contents, $error]) {
            $token = $this->stage($contents);
            $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()->assertSee($error)
                ->assertViewHas('inspection', fn ($value) => ! $value['can_import']);
            $this->confirm($token)->assertRedirect($this->teacher.'/siswa')->assertSessionHasErrors('file');
            $this->assertStringContainsString($error, implode(' ', session('errors')->get('file')));
            $this->assertDatabaseCount('students', 0);
            session()->forget('errors');
        }
    }

    public function test_database_collision_created_after_preview_invalidates_entire_import(): void
    {
        $csv = "nis,nama,kelas\n";
        for ($i = 1; $i <= 500; $i++) {
            $csv .= sprintf("%06d,Siswa,X A\n", $i);
        }
        $token = $this->stage($csv."999999,Bentrok,X B\n");
        Student::query()->create(['nis' => '999999', 'name' => 'Data lama', 'class_name' => 'X A']);
        $this->confirm($token)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', ['nis' => '999999', 'name' => 'Data lama']);
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
    }

    public function test_existing_nis_is_reported_during_preview(): void
    {
        Student::query()->create(['nis' => '001', 'name' => 'Data lama', 'class_name' => 'X A']);
        $this->stage("nis,nama,kelas\n001,Bentrok,X B\n002,Baru,X B\n");
        $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()->assertSee('NIS sudah terdaftar')
            ->assertViewHas('inspection', fn ($value) => $value['valid_count'] === 1 && $value['invalid_count'] === 1 && ! $value['can_import']);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_import_is_blocked_after_opening_and_after_closing(): void
    {
        foreach (['open', 'closed'] as $status) {
            ElectionSetting::current()->update(['status' => 'preparation']);
            $token = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
            ElectionSetting::current()->update(['status' => $status]);
            $this->get($this->teacher.'/siswa')->assertOk()->assertSee('Data siswa sudah dikunci')->assertDontSee('id="student-file"', false);
            $this->confirm($token)->assertForbidden()->assertSessionMissing('student_import');
            $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n")])->assertForbidden();
            $this->assertDatabaseCount('students', 0);
            $this->assertCount(0, Storage::disk('local')->files('student-imports'));
        }
    }

    public function test_upload_requires_a_csv_file_within_the_size_limit(): void
    {
        $this->post($this->teacher.'/siswa/impor')->assertSessionHasErrors('file');
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n", 'siswa.txt')])->assertSessionHasErrors('file');
        $this->post($this->teacher.'/siswa/impor', ['file' => UploadedFile::fake()->create('siswa.csv', 10241, 'text/csv')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 0);
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
    }

    public function test_record_limit_is_reported_without_truncating_the_record_count(): void
    {
        $csv = "nis,nama,kelas\n";
        for ($i = 1; $i <= 20001; $i++) {
            $csv .= sprintf("%06d,Siswa,X A\n", $i);
        }
        $token = $this->stage($csv);
        $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()->assertSee('20.000')
            ->assertViewHas('inspection', fn ($value) => $value['records'] === 20001 && ! $value['can_import']);
        $this->confirm($token)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_parser_finishes_after_thirty_errors_and_limits_displayed_rows(): void
    {
        $csv = "nis,nama,kelas\n";
        for ($i = 1; $i <= 40; $i++) {
            $csv .= "invalid{$i},Siswa,X A\n";
        }
        $this->stage($csv."001,Alya,X A\n002,Bima,X B\n003,Citra,X B\n");
        $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()
            ->assertViewHas('inspection', fn ($value) => $value['records'] === 43 && $value['valid_count'] === 3 &&
                $value['invalid_count'] === 40 && $value['error_count'] === 40 && count($value['errors']) === 30 && count($value['sample']) === 20);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_cancel_and_replacement_remove_private_staging_files(): void
    {
        $oldToken = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
        $newToken = $this->stage("nis,nama,kelas\n002,Bima,X B\n");
        $this->assertNotSame($oldToken, $newToken);
        Storage::disk('local')->assertMissing('student-imports/'.$oldToken.'.csv');
        $this->assertCount(2, Storage::disk('local')->files('student-imports'));
        $this->post($this->teacher.'/siswa/impor/batal')->assertRedirect($this->teacher.'/siswa')->assertSessionMissing('student_import');
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
        $this->assertDatabaseCount('students', 0);
    }

    public function test_expired_and_replayed_previews_cannot_commit(): void
    {
        $token = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
        $this->travel(31)->minutes();
        $this->confirm($token)->assertSessionHasErrors('file');
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
        $this->assertDatabaseCount('students', 0);
        $this->travelBack();
        session()->forget('errors');
        $token = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
        $this->confirm($token)->assertSessionHasNoErrors();
        $this->confirm($token)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_wrong_token_does_not_replace_the_current_preview(): void
    {
        $token = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
        $this->confirm(str_repeat('f', 64))->assertSessionHasErrors('file');
        $this->assertSame($token, session('student_import.token'));
        $this->assertDatabaseCount('students', 0);
    }

    public function test_preview_is_bound_to_its_session_without_deleting_the_owner_file(): void
    {
        $owner = new Store('owner', new ArraySessionHandler(120));
        $other = new Store('other', new ArraySessionHandler(120));
        $owner->start();
        $other->start();
        $service = app(StudentImportPreviewService::class);
        $service->stage($this->upload("nis,nama,kelas\n001,Alya,X A\n"), $owner);
        $token = $owner->get('student_import.token');
        $other->put('student_import.token', $token);
        try {
            $service->confirm($other, $token);
            $this->fail('Another session committed the preview.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('sesi ini', $exception->errors()['file'][0]);
        }
        $other->put('student_import.token', $token);
        $service->discard($other);
        Storage::disk('local')->assertExists('student-imports/'.$token.'.csv');
        $this->assertSame(1, $service->confirm($owner, $token));
        $this->assertDatabaseCount('students', 1);
    }

    public function test_cleanup_command_preserves_active_previews_and_removes_expired_ones(): void
    {
        $token = $this->stage("nis,nama,kelas\n001,Alya,X A\n");
        $this->artisan('students:purge-imports')->assertSuccessful();
        Storage::disk('local')->assertExists('student-imports/'.$token.'.csv');
        $this->travel(31)->minutes();
        $this->artisan('students:purge-imports')->assertSuccessful();
        $this->assertCount(0, Storage::disk('local')->files('student-imports'));
        $this->assertDatabaseCount('students', 0);
    }

    public function test_preview_escapes_student_text(): void
    {
        $this->stage("nis,nama,kelas\n001,<script>alert(1)</script>,X A\n");
        $this->get($this->teacher.'/siswa/impor/pratinjau')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_student_list_is_paginated_and_escapes_names(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            Student::query()->create(['nis' => sprintf('%05d', $i), 'name' => sprintf('Siswa %02d', $i), 'class_name' => 'X A']);
        }
        Student::query()->where('nis', '00051')->update(['name' => '<script>alert(1)</script>']);
        $this->get($this->teacher.'/siswa')->assertOk()->assertSee('51 siswa terdaftar')->assertSee('Berikutnya')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get($this->teacher.'/siswa?page=2')->assertOk()->assertSee('Sebelumnya')->assertSee('Halaman 2 dari 2');
    }
}
