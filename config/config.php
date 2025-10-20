<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field Type Filtering
    |--------------------------------------------------------------------------
    |
    | Defines which field types should or should not be translated.
    | Customize these arrays to match your project's field type conventions.
    |
    */

    'field_types' => [
        // Form field types that should never be translated
        'excluded_types' => [
            'dropdown',
            'radio',
            'checkbox',
            'checkboxlist',
            'switch',
            'number',
            'datepicker',
            'timepicker',
            'colorpicker',
            'mediafinder',
            'fileupload',
            'relation',
            'repeater',
            'partial',
        ],

        // Form field types that contain translatable content
        'translatable_types' => [
            'text',
            'textarea',
            'richeditor',
            'markdown',
            'mltext',
            'mltextarea',
            'mlricheditor',
            'mlmarkdowneditor',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Name Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for field names that should be excluded from translation.
    | Useful for automatically detecting system fields, IDs, timestamps, etc.
    |
    */

    'field_patterns' => [
        // Regex patterns for fields to exclude from translation
        'excluded_patterns' => [
            '/slug$/i',
            '/url$/i',
            '/uri$/i',
            '/code$/i',
            '/key$/i',
            '/_key$/i',
            '/^id$/i',
            '/_id$/i',
            '/_at$/i',
            '/^created_at$/i',
            '/^updated_at$/i',
            '/^deleted_at$/i',
            '/sort_order$/i',
            '/^sort$/i',
            '/^order$/i',
            '/^position$/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Type Heuristics
    |--------------------------------------------------------------------------
    |
    | Used by ModelDiscoveryService to automatically guess field types based
    | on field names. Customize these to match your project's naming conventions.
    |
    */

    'field_type_heuristics' => [
        // Field names that typically contain rich text/HTML content
        'rich_text_fields' => [
            'content',
            'description',
            'body',
            'text',
            'excerpt',
            'summary',
            'bio',
            'about',
        ],

        // Field names that are typically slugs or URL-safe identifiers
        'slug_fields' => [
            'slug',
            'code',
            'url',
            'uri',
            'handle',
        ],

        // Field names for SEO/meta content
        'meta_fields' => [
            'keywords',
            'meta_title',
            'meta_description',
            'seo_title',
            'seo_description',
            'og_title',
            'og_description',
            'twitter_title',
            'twitter_description',
        ],

        // Field names that should not be translated by default
        'skip_translation_fields' => [
            'slug',
            'code',
            'url',
            'published',
            'external',
            'type',
            'status',
            'is_published',
            'is_active',
            'is_featured',
        ],
    ],

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

