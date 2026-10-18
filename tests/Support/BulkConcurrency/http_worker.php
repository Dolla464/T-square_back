<?php

declare(strict_types=1);

/**
 * Standalone HTTP worker for parallel bulk concurrency tests.
 * Usage: php http_worker.php /path/to/job.json
 *
 * Does not run migrations or RefreshDatabase.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$jobPath = $argv[1] ?? null;
if ($jobPath === null || ! is_readable($jobPath)) {
    fwrite(STDERR, "Missing or unreadable job file.\n");
    exit(2);
}

/** @var array<string, mixed> $job */
$job = json_decode((string) file_get_contents($jobPath), true, 512, JSON_THROW_ON_ERROR);

$basePath = (string) ($job['base_path'] ?? '');
if ($basePath === '' || ! is_dir($basePath)) {
    fwrite(STDERR, "Invalid base_path in job.\n");
    exit(2);
}

foreach ($job['env'] ?? [] as $key => $value) {
    if (! is_string($key)) {
        continue;
    }
    $stringValue = $value === null ? '' : (string) $value;
    putenv($key.'='.$stringValue);
    $_ENV[$key] = $stringValue;
    $_SERVER[$key] = $stringValue;
}

$workerId = (string) ($job['worker_id'] ?? 'worker');
$barrierDir = isset($job['barrier_dir']) ? (string) $job['barrier_dir'] : '';
$barrierParticipants = (int) ($job['barrier_participants'] ?? 2);
$barrierTimeoutSeconds = (int) ($job['barrier_timeout_seconds'] ?? 30);
$barrierLockName = (string) ($job['barrier_lock_name'] ?? 't_square_bulk_parallel_barrier');
$resultPath = (string) ($job['result_path'] ?? '');
$readyMarker = $barrierDir !== '' ? $barrierDir.DIRECTORY_SEPARATOR.'ready_'.$workerId : '';

$exitCode = 1;
$resultPayload = [
    'ok' => false,
    'worker_id' => $workerId,
    'pid' => getmypid(),
    'error' => null,
    'http_status' => null,
    'json' => null,
    'meta' => [],
];

try {
    if ($barrierDir !== '') {
        if (! is_dir($barrierDir) && ! mkdir($barrierDir, 0777, true) && ! is_dir($barrierDir)) {
            throw new RuntimeException('Could not create barrier directory.');
        }
        file_put_contents($readyMarker, (string) time());

        $deadline = time() + $barrierTimeoutSeconds;
        while (time() < $deadline) {
            $readyFiles = glob($barrierDir.DIRECTORY_SEPARATOR.'ready_*') ?: [];
            if (count($readyFiles) >= $barrierParticipants) {
                break;
            }
            usleep(20_000);
        }

        $readyFiles = glob($barrierDir.DIRECTORY_SEPARATOR.'ready_*') ?: [];
        if (count($readyFiles) < $barrierParticipants) {
            throw new RuntimeException('Barrier timeout: not all workers became ready.');
        }
    }

    require $basePath.'/vendor/autoload.php';
    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once $basePath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $cacheDefault = (string) config('cache.default');
    $cacheDriver = (string) config('cache.stores.'.$cacheDefault.'.driver', '');
    $resultPayload['meta'] = [
        'cache_default' => $cacheDefault,
        'cache_driver' => $cacheDriver,
        'php_sapi' => PHP_SAPI,
    ];

    if (isset($job['expected_cache_store']) && (string) $job['expected_cache_store'] !== $cacheDefault) {
        throw new RuntimeException(
            'Cache store mismatch: expected '.(string) $job['expected_cache_store'].', got '.$cacheDefault
        );
    }

    $requestSpec = $job['request'] ?? [];
    $method = strtoupper((string) ($requestSpec['method'] ?? 'GET'));
    $uri = (string) ($requestSpec['uri'] ?? '/');
    $headers = is_array($requestSpec['headers'] ?? null) ? $requestSpec['headers'] : [];
    $jsonBody = $requestSpec['json'] ?? null;

    $server = ['HTTP_ACCEPT' => 'application/json'];
    foreach ($headers as $name => $value) {
        $normalized = strtoupper(str_replace('-', '_', (string) $name));
        if (! str_starts_with($normalized, 'HTTP_')) {
            $normalized = 'HTTP_'.$normalized;
        }
        $server[$normalized] = (string) $value;
    }

    $content = $jsonBody !== null ? json_encode($jsonBody, JSON_THROW_ON_ERROR) : null;
    if ($content !== null) {
        $server['CONTENT_TYPE'] = 'application/json';
    }

    $request = Request::create($uri, $method, [], [], [], $server, $content);
    /** @var Kernel $kernel */
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    $resultPayload['http_status'] = $response->getStatusCode();
    $decoded = json_decode($response->getContent(), true);
    $resultPayload['json'] = is_array($decoded) ? $decoded : ['raw' => $response->getContent()];
    $resultPayload['ok'] = true;
    $exitCode = 0;
} catch (Throwable $e) {
    $resultPayload['error'] = $e->getMessage();
    $exitCode = 1;
} finally {
    if ($readyMarker !== '' && is_file($readyMarker)) {
        @unlink($readyMarker);
    }

    if ($resultPath !== '') {
        file_put_contents(
            $resultPath,
            json_encode($resultPayload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }
}

exit($exitCode);
