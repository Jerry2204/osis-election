<?php

namespace Tests\Feature;

use App\Models\ElectionSetting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function upload(string $contents, string $name = 'siswa.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
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
        $this->post($wrong.'/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n")])->assertNotFound();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_bulk_upload_preserves_zeroes_utf8_quotes_and_trims_fields(): void
    {
        $csv = "\xEF\xBB\xBFnis,nama,kelas\r\n00001,\"Alya, Putri\",X A\r\n\r\n00002, José , XI B \r\n";
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload($csv)])
            ->assertRedirect($this->teacher.'/siswa')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseHas('students', ['nis' => '00001', 'name' => 'Alya, Putri']);
        $this->assertDatabaseHas('students', ['nis' => '00002', 'name' => 'José', 'class_name' => 'XI B']);
        $this->get($this->teacher.'/siswa')->assertOk()->assertSee('00001')->assertSee('Alya, Putri');
    }

    public function test_bad_csvs_do_not_save_any_rows_and_report_the_problem(): void
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
            $response = $this->from($this->teacher.'/siswa')->post($this->teacher.'/siswa/impor', ['file' => $this->upload($contents)]);
            $response->assertRedirect($this->teacher.'/siswa')->assertSessionHasErrors('file');
            $this->assertStringContainsString($error, implode(' ', session('errors')->get('file')));
            $this->assertDatabaseCount('students', 0);
        }
    }

    public function test_existing_nis_in_a_later_batch_rolls_back_all_new_students(): void
    {
        Student::query()->create(['nis' => '999999', 'name' => 'Data lama', 'class_name' => 'X A']);
        $csv = "nis,nama,kelas\n";
        for ($i = 1; $i <= 500; $i++) {
            $csv .= sprintf("%06d,Siswa,X A\n", $i);
        }
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload($csv."999999,Bentrok,X B\n")])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', ['nis' => '999999', 'name' => 'Data lama']);
    }

    public function test_import_is_blocked_after_opening_and_after_closing(): void
    {
        foreach (['open', 'closed'] as $status) {
            ElectionSetting::current()->update(['status' => $status]);
            $this->get($this->teacher.'/siswa')->assertOk()->assertSee('Data siswa sudah dikunci')->assertDontSee('id="student-file"', false);
            $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n")])->assertForbidden();
            $this->assertDatabaseCount('students', 0);
        }
    }

    public function test_upload_requires_a_csv_file_within_the_size_limit(): void
    {
        $this->post($this->teacher.'/siswa/impor')->assertSessionHasErrors('file');
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload("nis,nama,kelas\n001,Alya,X A\n", 'siswa.txt')])->assertSessionHasErrors('file');
        $this->post($this->teacher.'/siswa/impor', ['file' => UploadedFile::fake()->create('siswa.csv', 10241, 'text/csv')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_record_limit_cancels_the_whole_upload(): void
    {
        $csv = "nis,nama,kelas\n";
        for ($i = 1; $i <= 20001; $i++) {
            $csv .= sprintf("%06d,Siswa,X A\n", $i);
        }
        $this->post($this->teacher.'/siswa/impor', ['file' => $this->upload($csv)])->assertSessionHasErrors('file');
        $this->assertStringContainsString('20.000', implode(' ', session('errors')->get('file')));
        $this->assertDatabaseCount('students', 0);
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
