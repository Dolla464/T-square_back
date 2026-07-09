<?php

return [
    // ── Chunking ──────────────────────────────────────────
    'chunk_size_bytes'       => 5 * 1024 * 1024,
    'chunk_max_upload_kb'    => 5120,
    'max_video_size_kb'      => 512000,

    // ── Session / meta ────────────────────────────────────
    'meta_version'               => 1,
    'session_ttl_hours'          => 24,
    'finalizing_timeout_minutes' => 30,

    // ── Storage ───────────────────────────────────────────
    'chunks_disk'   => 'local',
    'chunks_path'   => 'chunks',
    'final_disk'    => 'public',
    'previews_path' => 'courses/previews',

    // ── Validation ────────────────────────────────────────
    'allowed_extensions' => ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'],

    // ── Processing ────────────────────────────────────────
    'getid3_max_size_mb' => 20,
];
