<?php

// Real HTTP cookies and CSRF checks against an isolated PHP server and MySQL _testing.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with((string) config('database.connections.mysql.database'), '_testing')) {
    throw new RuntimeException('HTTP smoke test refuses non-testing database.');
}
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
\App\Models\Student::query()->create(['nis' => '000007', 'name' => 'Siswa Simulasi', 'class_name' => 'X A']);
$base = 'http://127.0.0.1:8082';
$directory = storage_path('framework/testing/http-smoke-'.bin2hex(random_bytes(6)));
\Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
$imagePath = $directory.'/portrait.png';
$image = imagecreatetruecolor(400, 400);
$background = imagecolorallocate($image, 220, 236, 186);
$foreground = imagecolorallocate($image, 25, 59, 51);
imagefill($image, 0, 0, $background);
imagefilledellipse($image, 200, 140, 110, 110, $foreground);
imagefilledellipse($image, 200, 360, 250, 260, $foreground);
imagepng($image, $imagePath);
imagedestroy($image);
$serverEnvironment = array_merge(getenv(), [
    'APP_ENV' => 'testing', 'SESSION_COOKIE' => 'osis_http_smoke', 'SESSION_DRIVER' => 'file', 'SESSION_ENCRYPT' => 'true', 'CACHE_STORE' => 'file',
    'CANDIDATE_PHOTO_DIRECTORY' => $directory.'/photos',
    'PRIVATE_STORAGE_DIRECTORY' => $directory.'/private',
]);
// Start PHP directly so cleanup can terminate exactly this server process on
// Windows without depending on taskkill process-tree enumeration privileges.
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8082', '-t', public_path(), base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], [
    0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
    1 => ['file', $directory.'/server.log', 'a'],
    2 => ['file', $directory.'/server.log', 'a'],
], $serverPipes, public_path(), $serverEnvironment, ['bypass_shell' => true]);
if (! is_resource($server)) throw new RuntimeException('HTTP server process could not start.');
$assert = function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};
$request = function (string $path, string $jar, ?array $fields = null) use ($base): array {
    $curl = curl_init($base.$path);
    $headers = [];
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 8, CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$headers) {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
            return strlen($line);
        },
    ]);
    if ($fields !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException('HTTP request failed: '.curl_error($curl));
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$code, $body, $headers];
};
$token = function (string $body, string $name = '_token'): string {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$body);
    $xpath = new DOMXPath($document);
    $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
    if (! $input) throw new RuntimeException('Expected form input was missing: '.$name);
    return $input->getAttribute('value');
};
$teacher = '/ruang-guru/'.config('election.teacher_key');
$teacherJar = $directory.'/teacher-cookies.txt';
$studentJar = $directory.'/student-cookies.txt';
$replayJar = $directory.'/replay-cookies.txt';
try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @fsockopen('127.0.0.1', 8082, $errorCode, $errorText, .1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(100000);
    }
    $assert($ready, 'HTTP test server did not start.');
    [$code, $body] = $request($teacher, $teacherJar);
    $assert($code === 200, 'Teacher dashboard should render.');
    [$code] = $request($teacher.'/calon', $teacherJar, ['number' => 1]);
    $assert($code === 419, 'Real HTTP request without CSRF should fail.');
    [$code, $body] = $request($teacher.'/pengaturan', $teacherJar);
    $assert($code === 200, 'Settings should render.');
    [$code] = $request($teacher.'/pengaturan', $teacherJar, [
        '_token' => $token($body), '_method' => 'PATCH', 'school_name' => 'SMA Simulasi',
        'election_title' => 'Pemilihan Simulasi', 'osis_period' => '2026/2027',
        'report_place' => 'Jakarta', 'committee_chair' => 'Panitia Simulasi',
    ]);
    $assert($code === 302, 'Settings should save with real CSRF.');
    $csvPath = $directory.'/siswa.csv';
    file_put_contents($csvPath, "nis,nama,kelas\n000008,Siswa Impor,X B\n");
    [$code, $body] = $request($teacher.'/siswa', $teacherJar);
    [$code] = $request($teacher.'/siswa/impor', $teacherJar, [
        '_token' => $token($body), 'file' => new CURLFile($csvPath, 'text/csv', 'siswa.csv'),
    ]);
    $assert($code === 302 && \App\Models\Student::query()->count() === 1, 'Preview upload must not save students.');
    [$code, $body] = $request($teacher.'/siswa/impor/pratinjau', $teacherJar);
    $assert($code === 200 && str_contains($body, '000008'), 'Preview must survive real cookie round trip.');
    [$code] = $request($teacher.'/siswa/impor/konfirmasi', $teacherJar, ['_token' => $token($body), 'import_token' => $token($body, 'import_token')]);
    $assert($code === 302 && \App\Models\Student::query()->where('nis', '000008')->exists(), 'Preview should import only after confirmation.');
    [$code, $body] = $request($teacher.'/siswa?q=000008&class=X%20B&participation=pending', $teacherJar);
    $assert($code === 200 && str_contains($body, 'Siswa Impor') && ! str_contains($body, 'Siswa Simulasi'), 'Combined filters should render only matching students.');
    foreach ([1, 2] as $number) {
        [$code, $body] = $request($teacher.'/calon/tambah', $teacherJar);
        [$code] = $request($teacher.'/calon', $teacherJar, [
            '_token' => $token($body), 'number' => (string) $number, 'name' => 'Calon Simulasi '.$number,
            'vision' => 'Sekolah yang ramah.', 'mission' => 'Dengar aspirasi siswa.',
            'photo' => new CURLFile($imagePath, 'image/png', 'portrait.png'),
        ]);
        $assert($code === 302, 'Teacher upload should succeed.');
    }
    [$code] = $request($teacher.'/rekap', $teacherJar);
    $assert($code === 403, 'Results must be hidden before closing.');
    [$code] = $request($teacher.'/rekap.pdf', $teacherJar);
    $assert($code === 403, 'PDF results must be hidden before closing.');
    [$code, $body] = $request($teacher, $teacherJar);
    [$code] = $request($teacher.'/buka', $teacherJar, ['_token' => $token($body), 'confirmation' => 'BUKA']);
    $assert($code === 302, 'Election should open.');
    [$code, $body, $headers] = $request('/', $studentJar);
    $assert($code === 200 && ! str_contains($body, config('election.teacher_key')), 'Student HTML must not disclose teacher key.');
    $assert(str_contains($headers['cache-control'] ?? '', 'no-store'), 'Pages must be no-store.');
    [$code] = $request('/verifikasi-nis', $studentJar, ['_token' => $token($body), 'nis' => '000007']);
    $assert($code === 302, 'NIS should validate.');
    [$code, $body] = $request('/calon', $studentJar);
    $candidate = \App\Models\Candidate::query()->orderBy('number')->firstOrFail();
    [$code] = $request('/pilihan', $studentJar, ['_token' => $token($body), 'flow_token' => $token($body, 'flow_token'), 'candidate_id' => (string) $candidate->id]);
    $assert($code === 302, 'Choosing should redirect to confirmation.');
    [$code, $body] = $request('/konfirmasi', $studentJar);
    $fields = ['_token' => $token($body), 'flow_token' => $token($body, 'flow_token')];
    [$code, , $headers] = $request('/suara', $studentJar, $fields);
    $assert($code === 302 && str_ends_with($headers['location'] ?? '', '/berhasil'), 'Vote should commit and redirect.');
    [$code, $body] = $request('/berhasil', $studentJar);
    $assert($code === 200 && str_contains($body, 'SUARAMU SUDAH TERCATAT') && ! str_contains($body, '000007'), 'Success page must be anonymous.');
    [$code] = $request('/suara', $studentJar, $fields);
    $assert($code === 419, 'Old CSRF token must fail after completing.');
    [$code, $body] = $request('/', $replayJar);
    $request('/verifikasi-nis', $replayJar, ['_token' => $token($body), 'nis' => '000007']);
    [$code, $body] = $request('/', $replayJar);
    $assert(str_contains($body, 'NIS ini sudah digunakan untuk memilih.'), 'Another laptop must reject used NIS.');
    [$code, $body] = $request($teacher, $teacherJar);
    $request($teacher.'/tutup', $teacherJar, ['_token' => $token($body), 'confirmation' => 'TUTUP']);
    [$code, $body] = $request($teacher.'/rekap', $teacherJar);
    $assert($code === 200 && ! str_contains($body, '000007'), 'Final results must be anonymous.');
    [$code, $body] = $request($teacher.'/rekap.csv', $teacherJar);
    $assert($code === 200 && str_contains($body, '2,"Calon Simulasi 2",0'), 'CSV must contain zero-vote candidate.');
    [$code, $body] = $request($teacher.'/progres', $teacherJar);
    $progress = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $assert($code === 200 && $progress['registered'] === 2 && $progress['participated'] === 1 && ! str_contains($body, 'candidate_id'), 'Progress must expose only aggregate turnout.');
    [$code, $body] = $request($teacher.'/rekap.pdf', $teacherJar);
    $assert($code === 200 && str_starts_with($body, '%PDF-'), 'Closed election PDF should download offline.');
    $assert(\App\Models\Vote::query()->count() === 1 && \App\Models\Participation::query()->count() === 1, 'Exactly one vote and participation must remain.');
    echo "HTTP smoke PASS: cookies, CSRF, pengaturan, pratinjau/impor CSV, filter, unggah calon, buka/tutup, pemilihan, replay, progres dan PDF/CSV.\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    if (is_dir($directory.'/private')) {
        $resolvedPrivate = strtolower(str_replace('\\', '/', realpath($directory.'/private')));
        $resolvedTesting = strtolower(str_replace('\\', '/', realpath(storage_path('framework/testing'))));
        if (! str_starts_with($resolvedPrivate.'/', $resolvedTesting.'/')) {
            throw new RuntimeException('Refusing cleanup outside the testing workspace.');
        }
        \Illuminate\Support\Facades\File::deleteDirectory($directory.'/private');
    }
    if (is_dir($directory.'/photos')) {
        foreach (\Illuminate\Support\Facades\File::files($directory.'/photos') as $file) unlink($file->getPathname());
        @rmdir($directory.'/photos');
    }
    foreach (\Illuminate\Support\Facades\File::files($directory) as $file) unlink($file->getPathname());
    @rmdir($directory);
}
