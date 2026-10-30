<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Student;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    // Real committed MySQL data: subprocesses must not share a test transaction.
    use DatabaseMigrations;

    private function fixture(): Candidate
    {
        ElectionSetting::current()->update(['status' => 'open']);
        return Candidate::query()->create(['number' => 1, 'name' => 'Calon Uji', 'photo_path' => 'test.png', 'vision' => 'Visi', 'mission' => 'Misi']);
    }

    private function startWorkers(array $studentIds, int $candidateId, string $barrier): array
    {
        $processes = [];
        foreach ($studentIds as $studentId) {
            $context = ['student_id' => $studentId, 'candidate_id' => $candidateId, 'token' => str_repeat('t', 64), 'expires_at' => now()->timestamp + 300];
            $process = new Process([PHP_BINARY, base_path('tests/support/cast-vote-worker.php'), $barrier], base_path(), ['APP_ENV' => 'testing'], json_encode($context), 20);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 12;
        do {
            $ready = array_reduce($processes, fn ($all, $process) => $all && str_contains($process->getOutput(), 'READY'), true);
            if ($ready) {
                return $processes;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        foreach ($processes as $process) {
            $process->stop();
        }
        $this->fail('Workers did not become ready. Check .env.testing and MySQL access.');
    }

    public function test_three_laptops_using_same_nis_produce_exactly_one_vote(): void
    {
        $candidate = $this->fixture();
        $student = Student::query()->create(['nis' => '0001', 'name' => 'Siswa', 'class_name' => 'X']);
        $barrier = storage_path('framework/testing/barrier-'.bin2hex(random_bytes(6)));
        $processes = $this->startWorkers(array_fill(0, 3, $student->id), $candidate->id, $barrier);
        try {
            file_put_contents($barrier, 'go');
            $codes = array_map(fn (Process $process) => $process->wait(), $processes);
            sort($codes);
            $this->assertSame([0, 2, 2], $codes);
            $this->assertDatabaseCount('votes', 1);
            $this->assertDatabaseCount('participations', 1);
        } finally {
            foreach ($processes as $process) $process->stop();
            @unlink($barrier);
        }
    }

    public function test_three_different_students_can_vote_simultaneously(): void
    {
        $candidate = $this->fixture();
        $ids = [];
        foreach (['0001', '0002', '0003'] as $nis) {
            $ids[] = Student::query()->create(['nis' => $nis, 'name' => 'Siswa', 'class_name' => 'X'])->id;
        }
        $barrier = storage_path('framework/testing/barrier-'.bin2hex(random_bytes(6)));
        $processes = $this->startWorkers($ids, $candidate->id, $barrier);
        try {
            file_put_contents($barrier, 'go');
            $this->assertSame([0, 0, 0], array_map(fn (Process $process) => $process->wait(), $processes));
            $this->assertDatabaseCount('votes', 3);
            $this->assertDatabaseCount('participations', 3);
        } finally {
            foreach ($processes as $process) $process->stop();
            @unlink($barrier);
        }
    }

    public function test_closing_transaction_wins_before_waiting_vote_can_commit(): void
    {
        $candidate = $this->fixture();
        $student = Student::query()->create(['nis' => '0001', 'name' => 'Siswa', 'class_name' => 'X']);
        $barrier = storage_path('framework/testing/barrier-'.bin2hex(random_bytes(6)));
        $processes = $this->startWorkers([$student->id], $candidate->id, $barrier);
        try {
            DB::beginTransaction();
            ElectionSetting::locked()->update(['status' => 'closed']);
            file_put_contents($barrier, 'go');
            usleep(150000);
            $this->assertTrue($processes[0]->isRunning(), 'Vote should wait for the election row lock.');
            DB::commit();
            $this->assertSame(2, $processes[0]->wait());
            $this->assertDatabaseCount('votes', 0);
            $this->assertDatabaseCount('participations', 0);
        } finally {
            if (DB::transactionLevel()) DB::rollBack();
            foreach ($processes as $process) $process->stop();
            @unlink($barrier);
        }
    }
}
