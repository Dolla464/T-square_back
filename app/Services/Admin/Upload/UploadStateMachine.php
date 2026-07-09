<?php

namespace App\Services\Admin\Upload;

use App\Exceptions\InvalidUploadStateException;

final class UploadStateMachine
{
    private const ALLOWED_TRANSITIONS = [
        'created'    => ['uploading'],
        'uploading'  => ['uploading', 'uploaded'],
        'uploaded'   => ['finalizing'],
        'finalizing' => ['complete', 'failed'],
        'complete'   => [],
        'failed'     => ['uploading', 'uploaded'],
    ];

    public function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidUploadStateException($from, $to);
        }
    }

    public function transition(array &$meta, string $to): void
    {
        $from = $meta['status'] ?? 'created';
        $this->assertTransitionAllowed($from, $to);
        $meta['status'] = $to;
    }

    public function isTerminal(string $status): bool
    {
        return $status === 'complete';
    }
}
