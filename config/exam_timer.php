<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exam Timer Diagnostic Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, emits passive [EXAM_TIMER_DIAGNOSTIC] log entries for
    | exam start/resume and time-status responses. Default: disabled.
    |
    */
    'diagnostic' => filter_var(env('EXAM_TIMER_DIAGNOSTIC', false), FILTER_VALIDATE_BOOL),
];
