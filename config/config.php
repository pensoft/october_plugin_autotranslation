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
    | DeepL Locale Code Mappings
    |--------------------------------------------------------------------------
    |
    | Maps locale codes to DeepL-compatible format.
    | DeepL requires uppercase codes (BG, ES, etc.) and specific regional
    | variants (EN-US, PT-PT, ZH-HANS).
    |
    | Format: 'your_locale_code' => 'DEEPL_CODE'
    |
    */
    'locale_mappings' => [
        // Standard 2-letter codes → Uppercase
        'ar' => 'AR',        // Arabic
        'bg' => 'BG',        // Bulgarian
        'cs' => 'CS',        // Czech
        'da' => 'DA',        // Danish
        'de' => 'DE',        // German
        'el' => 'EL',        // Greek
        'es' => 'ES',        // Spanish (Spain)
        'et' => 'ET',        // Estonian
        'fi' => 'FI',        // Finnish
        'fr' => 'FR',        // French
        'hu' => 'HU',        // Hungarian
        'id' => 'ID',        // Indonesian
        'it' => 'IT',        // Italian
        'ja' => 'JA',        // Japanese
        'ko' => 'KO',        // Korean
        'lt' => 'LT',        // Lithuanian
        'lv' => 'LV',        // Latvian
        'nb' => 'NB',        // Norwegian Bokmål
        'nl' => 'NL',        // Dutch
        'pl' => 'PL',        // Polish
        'ro' => 'RO',        // Romanian
        'ru' => 'RU',        // Russian
        'sk' => 'SK',        // Slovak
        'sl' => 'SL',        // Slovenian
        'sv' => 'SV',        // Swedish
        'tr' => 'TR',        // Turkish
        'uk' => 'UK',        // Ukrainian

        // Special cases - regional variants
        'en' => 'EN-US',     // Default English to US
        'pt' => 'PT-PT',     // Default Portuguese to Portugal
        'zh' => 'ZH-HANS',   // Default Chinese to Simplified

        // Common alternative codes
        'sp' => 'ES',        // Spanish alternative code
        'no' => 'NB',        // Norwegian alternative
    ],

];

