<?php

return [
    'duration_minutes' => (int) env('AUTH_SESSION_DURATION_MINUTES', 120),
    'warning_minutes' => (int) env('AUTH_SESSION_WARNING_MINUTES', 5),
];
