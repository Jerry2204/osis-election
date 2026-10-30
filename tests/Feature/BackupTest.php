<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_dump_restores_counts_and_status_without_partial_file(): void
    {
        $directory = storage_path('framework/testing/backup-'.Str::random(12));
        config(['election.backup_directory' => $directory]);
        $student = Student::query()->create(['nis' => '000123', 'name' => 'Siswa', 'class_name' => 'X A']);
        $candidate = Candidate::query()->create(['number' => 1, 'name' => 'Calon', 'photo_path' => 'test.png', 'vision' => 'Visi', 'mission' => 'Misi']);
        Participation::query()->create(['student_id' => $student->id]);
        Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
        ElectionSetting::current()->update(['status' => 'closed']);
        try {
            $this->artisan('election:backup')->assertSuccessful();
            $files = File::files($directory);
            $this->assertCount(1, $files);
            $this->assertStringEndsWith('.sql', $files[0]->getFilename());
            DB::table('votes')->delete();
            DB::table('participations')->delete();
            DB::table('candidates')->delete();
            DB::table('students')->delete();
            ElectionSetting::current()->update(['status' => 'preparation']);
            $connection = config('database.connections.mysql');
            $handle = fopen($files[0]->getPathname(), 'r');
            try {
                $process = new Process([
                    'mysql', '--protocol=TCP', '--host='.$connection['host'], '--port='.$connection['port'],
                    '--user='.$connection['username'], '--database='.$connection['database'], '--default-character-set=utf8mb4',
                ], base_path(), ['MYSQL_PWD' => $connection['password']], $handle, 30);
                $process->run();
                $this->assertTrue($process->isSuccessful(), 'Restore MySQL harus berhasil. Periksa mysql pada PATH.');
            } finally {
                fclose($handle);
            }
            $this->assertDatabaseCount('students', 1);
            $this->assertDatabaseHas('students', ['nis' => '000123']);
            $this->assertDatabaseCount('votes', 1);
            $this->assertDatabaseCount('participations', 1);
            $this->assertSame('closed', ElectionSetting::current()->status);
        } finally {
            // Directory is created by this test in storage/framework/testing only.
            foreach (File::files($directory) as $file) File::delete($file->getPathname());
            @rmdir($directory);
        }
    }

    public function test_backup_refuses_public_directory(): void
    {
        config(['election.backup_directory' => public_path()]);
        $this->artisan('election:backup')->assertFailed();
    }
}
