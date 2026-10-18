<?php

declare(strict_types=1);

namespace Tests\Support\BulkConcurrency;

use RuntimeException;
use Symfony\Component\Process\Process;

final class ParallelHttpRunner
{
    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    public static function run(array $jobs, int $timeoutSeconds = 90): array
    {
        if (count($jobs) < 2) {
            throw new RuntimeException('ParallelHttpRunner requires at least two jobs.');
        }

        $barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'t_square_bulk_'.bin2hex(random_bytes(8));
        if (! mkdir($barrierDir, 0777, true) && ! is_dir($barrierDir)) {
            throw new RuntimeException('Could not create barrier directory.');
        }

        $phpBinary = (string) (getenv('PHP_BINARY') ?: PHP_BINARY);
        $workerScript = base_path('tests/Support/BulkConcurrency/http_worker.php');
        $jobFiles = [];
        $resultPaths = [];
        $processes = [];
        $sharedBarrierLockName = 't_square_bulk_'.bin2hex(random_bytes(8));

        try {
            foreach ($jobs as $index => $job) {
                $resultPath = tempnam(sys_get_temp_dir(), 't_square_bulk_result_');
                if ($resultPath === false) {
                    throw new RuntimeException('Could not create result temp file.');
                }

                $jobPayload = array_merge($job, [
                    'base_path' => base_path(),
                    'barrier_dir' => $barrierDir,
                    'barrier_participants' => count($jobs),
                    'barrier_lock_name' => $sharedBarrierLockName,
                    'result_path' => $resultPath,
                ]);

                $jobFile = tempnam(sys_get_temp_dir(), 't_square_bulk_job_');
                if ($jobFile === false) {
                    throw new RuntimeException('Could not create job temp file.');
                }

                file_put_contents($jobFile, json_encode($jobPayload, JSON_THROW_ON_ERROR));
                $jobFiles[] = $jobFile;
                $resultPaths[] = $resultPath;

                $process = new Process(
                    [$phpBinary, $workerScript, $jobFile],
                    base_path(),
                    null,
                    null,
                    $timeoutSeconds,
                );
                $process->start(function ($type, $buffer): void {
                    // Drain stdout/stderr so parallel workers cannot block on full pipes (Windows).
                });
                $processes[] = $process;
            }

            $errors = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                if (! $process->isSuccessful()) {
                    $errors[] = 'Worker '.$index.' exited '.$process->getExitCode().': '.trim($process->getErrorOutput().$process->getOutput());
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode("\n", $errors));
            }

            $results = [];
            foreach ($resultPaths as $index => $resultPath) {
                if (! is_readable($resultPath)) {
                    throw new RuntimeException('Missing result file for worker '.$index.'.');
                }
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
                $results[] = $decoded;
            }

            $pids = array_map(static fn (array $r): int => (int) ($r['pid'] ?? 0), $results);
            if (count(array_unique(array_filter($pids))) < count($jobs)) {
                throw new RuntimeException('Workers did not run in separate PHP processes.');
            }

            return $results;
        } finally {
            foreach ($jobFiles as $jobFile) {
                if (is_file($jobFile)) {
                    @unlink($jobFile);
                }
            }
            foreach ($resultPaths as $resultPath) {
                if (is_file($resultPath)) {
                    @unlink($resultPath);
                }
            }
            if (is_dir($barrierDir)) {
                foreach (glob($barrierDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                @rmdir($barrierDir);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public static function testingEnvFromApp(?string $cacheStoreOverride = null): array
    {
        $env = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => config('app.debug') ? 'true' : 'false',
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => $cacheStoreOverride ?? (string) config('cache.default'),
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        return $env;
    }
}
