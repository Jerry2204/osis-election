<?php

use App\Exceptions\VotingRejected;
use App\Services\CastVoteService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with((string) config('database.connections.mysql.database'), '_testing')) {
    fwrite(STDERR, 'Worker refuses non-testing database.');
    exit(1);
}
$context = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo "READY\n";
flush();
$deadline = microtime(true) + 15;
while (! is_file($argv[1])) {
    if (microtime(true) > $deadline) {
        exit(1);
    }
    usleep(10000);
}
try {
    $app->make(CastVoteService::class)->cast($context, $context['token']);
    echo "ACCEPTED\n";
    exit(0);
} catch (VotingRejected) {
    echo "REJECTED\n";
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception));
    exit(1);
}
