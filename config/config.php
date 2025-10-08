<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Field Exclusions
    |--------------------------------------------------------------------------
    |
    | Field names that should never be translated by default.
    | These can be overridden in the plugin settings.
    |
    */
    'default_excluded_fields' => [
        'slug',
        'url',
        'uri',
        'code',
        'key',
        'api_key',
        'secret',
        'token',
        'password',
        'hash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of items to translate in a single batch.
    | Larger batches are more efficient but may hit API rate limits.
    |
    */
    'batch_size' => 50,

    /*
    |--------------------------------------------------------------------------
    | Locale Mapping
    |--------------------------------------------------------------------------
    |
    | Map October CMS locale codes to DeepL locale codes.
    | DeepL requires specific locale codes (e.g., 'en-US' instead of 'en').
    |
    */
    'locale_mapping' => [
            'en' => 'EN-US',
            'en-gb' => 'EN-GB',
            'en-us' => 'EN-US',
            'pt' => 'PT-PT',
            'pt-pt' => 'PT-PT',
            'pt-br' => 'PT-BR',
            'bg' => 'BG',      // Bulgarian
            'cs' => 'CS',      // Czech
            'da' => 'DA',      // Danish
            'de' => 'DE',      // German
            'el' => 'EL',      // Greek
            'es' => 'ES',      // Spanish
            'et' => 'ET',      // Estonian
            'fi' => 'FI',      // Finnish
            'fr' => 'FR',      // French
            'hu' => 'HU',      // Hungarian
            'id' => 'ID',      // Indonesian
            'it' => 'IT',      // Italian
            'ja' => 'JA',      // Japanese
            'ko' => 'KO',      // Korean
            'lt' => 'LT',      // Lithuanian
            'lv' => 'LV',      // Latvian
            'nb' => 'NB',      // Norwegian (Bokmål)
            'nl' => 'NL',      // Dutch
            'pl' => 'PL',      // Polish
            'ro' => 'RO',      // Romanian
            'ru' => 'RU',      // Russian
            'sk' => 'SK',      // Slovak
            'sl' => 'SL',      // Slovenian
            'sv' => 'SV',      // Swedish
            'tr' => 'TR',      // Turkish
            'uk' => 'UK',      // Ukrainian
            'zh' => 'ZH',      // Chinese (simplified)
            'hr' => 'HR',      // Croatian
            'ga' => 'GA',      // Irish (not in DeepL - will fallback)
            'mt' => 'MT',      // Maltese (not in DeepL - will fallback)
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds for DeepL API calls.
    |
    */
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Number of times to retry failed API requests.
    |
    */
    'max_retries' => 3,
];

