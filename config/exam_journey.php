<?php

return [
    'enabled' => filter_var(env('EXAM_STUDENT_EXAM_LOG', false), FILTER_VALIDATE_BOOL),

    'client_events_enabled' => filter_var(
        env('EXAM_STUDENT_EXAM_LOG_CLIENT', true),
        FILTER_VALIDATE_BOOL,
    ),

    'max_events_per_request' => (int) env('EXAM_JOURNEY_MAX_EVENTS', 20),

    'max_payload_bytes' => (int) env('EXAM_JOURNEY_MAX_PAYLOAD_BYTES', 16384),

    'max_meta_keys' => 10,

    'max_meta_string_length' => 256,

    'client_event_types' => [
        'PAGE_MOUNT',
        'START_API_CALL',
        'START_API_FAIL',
        'NETWORK_ERROR',
        'SUBMIT_CLICK',
        'VISIBILITY_HIDDEN',
        'ERROR_BOUNDARY',
    ],
];
