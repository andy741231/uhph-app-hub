<?php

return [
    'authorization_code_ttl' => (int) env('HUB_AUTHORIZATION_CODE_TTL', 60),
    'local_client' => [
        'application_keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('HUB_LOCAL_APPLICATION_KEYS', '')),
        ))),
        'secret' => env('HUB_LOCAL_CLIENT_SECRET'),
    ],
];
