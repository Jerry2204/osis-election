<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Student;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ScheduledConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_vote_waiting_on_election_lock_cannot_commit_after_scheduled_close(): void
    {
        ElectionSetting::current()->update([
            'status' => 'open', 'opens_at' => now()->utc()->subMinute(),
            'opened_at' => now()->utc()->subMinute(), 'closes_at' => now()->utc()->addMinute(),
        ]);
        $candidate = Candidate::query()->create(['number' => 1, 'name' => 'Calon', 'photo_path' => 'test.png', 'vision' => 'Visi', 'mission' => 'Misi']);
        $student = Student::query()->create(['nis' => '0001', 'name' => 'Siswa', 'class_name' => 'X A']);
        $context = ['student_id' => $student->id, 'candidate_id' => $candidate->id, 'token' => str_repeat('t', 64), 'expires_at' => now()->timestamp + 300];
        $barrier = storage_path('framework/testing/scheduled-barrier-'.bin2hex(random_bytes(6)));
        $process = new Process([PHP_BINARY, base_path('tests/support/cast-vote-worker.php'), $barrier], base_path(), ['APP_ENV' => 'testing'], json_encode($context), 20);
        $process->start();
        try {
            $deadline = microtime(true) + 12;
            while (! str_contains($process->getOutput(), 'READY') && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $process->getOutput(), 'The worker must connect to the separate testing database.');
            DB::beginTransaction();
            ElectionSetting::locked()->update(['closes_at' => now()->utc()->addSecond()]);
            file_put_contents($barrier, 'go');
            // The worker passed middleware earlier; its database lock is the
            // final boundary check, after the deadline has elapsed here.
            usleep(1600000);
            $this->assertTrue($process->isRunning(), 'Vote should be waiting on the election lock.');
            DB::commit();
            $this->assertSame(2, $process->wait(), $process->getErrorOutput());
            $this->assertSame('closed', ElectionSetting::current()->status);
            $this->assertNotNull(ElectionSetting::current()->closed_at);
            $this->assertDatabaseCount('votes', 0);
            $this->assertDatabaseCount('participations', 0);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            $process->stop();
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }
    }
}
