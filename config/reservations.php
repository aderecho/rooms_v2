<?php

return [
    'organization' => env('RESERVATION_ORGANIZATION', 'University of the Philippines Cebu'),
    'portal_url' => env('RESERVATION_PORTAL_URL', env('APP_URL', 'http://localhost')),
    'logo_url' => env('RESERVATION_LOGO_URL'),
    'contact_email' => env('RESERVATION_CONTACT_EMAIL'),
    'instructions' => env('RESERVATION_APPROVAL_INSTRUCTIONS'),
    'additional_recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('RESERVATION_NOTIFICATION_RECIPIENTS', ''))))),
    'mail_tries' => max(1, (int) env('RESERVATION_MAIL_TRIES', 3)),
    'mail_backoff' => array_map('intval', explode(',', (string) env('RESERVATION_MAIL_BACKOFF', '60,300,900'))),
];
