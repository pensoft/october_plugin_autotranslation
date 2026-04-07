<?php

return [
    'plugin' => [
        'name' => 'Auto Translation',
        'description' => 'AI-powered translation using DeepL API',
    ],
    'settings' => [
        'label' => 'Auto Translation Settings',
        'description' => 'Configure DeepL API and translation options',
    ],
    'permissions' => [
        'access' => 'Access auto translation features',
        'manage_settings' => 'Manage translation settings',
    ],
    'navigation' => [
        'main' => 'Auto Translation',
        'messages' => 'Translate Messages',
        'models' => 'Translate Models',
    ],
    'messages' => [
        'translate_success' => 'Successfully translated :count items',
        'translate_error' => 'Translation failed: :error',
        'no_api_key' => 'DeepL API key is not configured',
        'connection_success' => 'Connection successful!',
        'connection_failed' => 'Connection failed',
    ],
];

