<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfigureSessionLifetime;
use Illuminate\Http\Request;
use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    public function test_teacher_duration_does_not_leak_into_later_voter_requests(): void
    {
        config(['election.base_session_lifetime_minutes' => 5, 'election.teacher_session_lifetime_minutes' => 30]);
        $middleware = new ConfigureSessionLifetime;
        $observed = [];
        foreach (['/ruang-guru/'.str_repeat('a', 64).'/siswa/impor/pratinjau', '/', '/ruang-guru/'.str_repeat('a', 64), '/verifikasi-nis'] as $path) {
            $middleware->handle(Request::create($path), function () use (&$observed) {
                $observed[] = config('session.lifetime');
                return response('ok');
            });
        }
        $this->assertSame([30, 5, 30, 5], $observed);
        $this->assertSame(300, config('election.voting_lifetime'));
    }

    public function test_teacher_duration_is_at_least_the_configured_base_duration(): void
    {
        config(['election.base_session_lifetime_minutes' => 60, 'election.teacher_session_lifetime_minutes' => 30]);
        $middleware = new ConfigureSessionLifetime;
        $middleware->handle(Request::create('/ruang-guru/'.str_repeat('a', 64).'/siswa'), function () {
            $this->assertSame(60, config('session.lifetime'));
            return response('ok');
        });
        $middleware->handle(Request::create('/'), function () {
            $this->assertSame(60, config('session.lifetime'));
            return response('ok');
        });
    }

    public function test_public_file_session_requests_do_not_run_short_lifetime_garbage_collection(): void
    {
        config(['session.driver' => 'file', 'session.lottery' => [100, 100], 'election.base_session_lifetime_minutes' => 5]);
        (new ConfigureSessionLifetime)->handle(Request::create('/'), function () {
            $this->assertSame(5, config('session.lifetime'));
            $this->assertSame([0, 100], config('session.lottery'));
            return response('ok');
        });
    }

    public function test_purge_keeps_six_minute_teacher_sessions_but_removes_expired_sessions(): void
    {
        $directory = $this->temporarySessionDirectory();
        $files = [str_repeat('a', 40) => 31 * 60, str_repeat('b', 40) => 6 * 60, str_repeat('c', 40) => 60, 'notes.txt' => 60 * 60];
        try {
            foreach ($files as $name => $age) {
                file_put_contents($directory.DIRECTORY_SEPARATOR.$name, 'isolated test session');
                touch($directory.DIRECTORY_SEPARATOR.$name, time() - $age);
            }
            config(['session.driver' => 'file', 'session.files' => $directory, 'election.base_session_lifetime_minutes' => 5, 'election.teacher_session_lifetime_minutes' => 30]);
            $this->artisan('election:purge-sessions')->assertSuccessful();
            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.str_repeat('a', 40));
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.str_repeat('b', 40));
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.str_repeat('c', 40));
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'notes.txt');
        } finally {
            $this->removeTemporarySessions($directory, array_keys($files));
        }
    }

    public function test_purge_respects_a_longer_base_duration(): void
    {
        $directory = $this->temporarySessionDirectory();
        $files = [str_repeat('d', 40) => 61 * 60, str_repeat('e', 40) => 31 * 60];
        try {
            foreach ($files as $name => $age) {
                file_put_contents($directory.DIRECTORY_SEPARATOR.$name, 'isolated test session');
                touch($directory.DIRECTORY_SEPARATOR.$name, time() - $age);
            }
            config(['session.driver' => 'file', 'session.files' => $directory, 'election.base_session_lifetime_minutes' => 60, 'election.teacher_session_lifetime_minutes' => 30]);
            $this->artisan('election:purge-sessions')->assertSuccessful();
            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.str_repeat('d', 40));
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.str_repeat('e', 40));
        } finally {
            $this->removeTemporarySessions($directory, array_keys($files));
        }
    }

    private function temporarySessionDirectory(): string
    {
        $directory = tempnam(sys_get_temp_dir(), 'osis-session-test-');
        unlink($directory);
        mkdir($directory, 0700);
        return $directory;
    }

    private function removeTemporarySessions(string $directory, array $names): void
    {
        // Remove only exact files this test created, without recursive deletion.
        foreach ($names as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
