<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\PrivacyHeaders::class);
        $middleware->web(prepend: [\App\Http\Middleware\ConfigureSessionLifetime::class]);
        $middleware->web(append: [\App\Http\Middleware\ReconcileElectionSchedule::class]);
        $middleware->alias([
            'teacher' => \App\Http\Middleware\EnsureTeacherKey::class,
            'election.open' => \App\Http\Middleware\EnsureElectionOpen::class,
            'voting.session' => \App\Http\Middleware\EnsureVotingSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['nis', 'candidate_id', 'flow_token', 'password', 'password_confirmation']);
        $exceptions->dontReport([\Illuminate\Contracts\Cache\LockTimeoutException::class]);
        $exceptions->render(function (\Illuminate\Contracts\Cache\LockTimeoutException $exception) {
            return response()->view('errors.problem', ['code' => 409, 'message' => 'Permintaan sebelumnya masih diproses. Jika sudah mengirim suara, minta petugas memeriksa status NIS sebelum mencoba kembali.'], 409);
        });
        $exceptions->report(function (\Illuminate\Database\QueryException $exception) {
            \Illuminate\Support\Facades\Log::error('Operasi database gagal. Periksa layanan MySQL.', ['code' => $exception->getCode()]);
            return false;
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $exception, \Illuminate\Http\Request $request) {
            return response()->view('errors.problem', ['code' => 503, 'message' => 'Layanan database belum tersedia. Silakan hubungi petugas.'], 503);
        });
        $exceptions->respond(fn ($response) => \App\Http\Middleware\PrivacyHeaders::apply($response));
    })->create();
