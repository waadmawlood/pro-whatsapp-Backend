<?php

return [
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),

    'bridge' => [
        'url' => env('WHATSAPP_BRIDGE_URL', 'http://127.0.0.1:3001'),
        'secret' => env('WHATSAPP_BRIDGE_SECRET', 'change-me-bridge-secret'),
        'timeout' => (int) env('WHATSAPP_BRIDGE_TIMEOUT', 10),
    ],
];
