<?php

/*
| Only the landing page origins may call this API. Set LEAD_ALLOWED_ORIGINS to
| a comma separated list per environment; localhost belongs in local/staging
| only. No credentials are used, so no cookies and no wildcard-with-credentials.
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('LEAD_ALLOWED_ORIGINS', ''))
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-Submission-Id', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
