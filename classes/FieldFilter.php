<?php namespace Pensoft\AutoTranslation\Classes;

use Pensoft\AutoTranslation\Models\Settings;

/**
 * Field Filter - Determines which fields should be translated
 */
class FieldFilter
{
    /**
     * Default field types to exclude from translation
     *
     * @var array
     */
    protected static $excludedTypes = [
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
    ];
    
    /**
     * Field name patterns to exclude (regex patterns)
     *
     * @var array
     */
    protected static $excludedPatterns = [
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
    ];
    
    /**
     * Translatable field types
     *
     * @var array
     */
    protected static $translatableTypes = [
        'text',
        'textarea',
        'richeditor',
        'markdown',
        'mltext',
        'mltextarea',
        'mlricheditor',
        'mlmarkdowneditor',
    ];
    
    /**
     * Check if field should be translated
     *
     * @param string $fieldName
     * @param array $fieldConfig
     * @return bool
     */
    public static function shouldTranslate($fieldName, array $fieldConfig = [])
    {
        // Check if explicitly marked as non-translatable
        if (isset($fieldConfig['translatable']) && $fieldConfig['translatable'] === false) {
            return false;
        }
        
        // Check if explicitly marked as translatable
        if (isset($fieldConfig['translatable']) && $fieldConfig['translatable'] === true) {
            return true;
        }
        
        // Check against excluded patterns
        foreach (self::$excludedPatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return false;
            }
        }
        
        // Check custom excluded fields from settings
        $customExclusions = self::getCustomExclusions();
        if (in_array($fieldName, $customExclusions)) {
            return false;
        }
        
        // Get field type
        $fieldType = isset($fieldConfig['type']) ? $fieldConfig['type'] : 'text';
        
        // Check if type is in excluded list
        if (in_array($fieldType, self::$excludedTypes)) {
            return false;
        }
        
        // Only translate text-based fields
        return in_array($fieldType, self::$translatableTypes);
    }
    
    /**
     * Check if field contains HTML/rich content
     *
     * @param array $fieldConfig
     * @return bool
     */
    public static function isRichContent(array $fieldConfig = [])
    {
        $fieldType = isset($fieldConfig['type']) ? $fieldConfig['type'] : 'text';
        
        $richTypes = [
            'richeditor',
            'mlricheditor',
            'markdown',
            'mlmarkdowneditor',
        ];
        
        return in_array($fieldType, $richTypes);
    }
    
    /**
     * Get custom excluded fields from settings
     *
     * @return array
     */
    public static function getCustomExclusions()
    {
        $excluded = Settings::get('excluded_fields', '');
        
        if (empty($excluded)) {
            return [];
        }
        
        // Support comma-separated or line-separated values
        $fields = preg_split('/[\r\n,]+/', $excluded);
        
        // Trim and filter
        $fields = array_map('trim', $fields);
        $fields = array_filter($fields);
        
        return $fields;
    }
    
    /**
     * Add custom exclusion pattern
     *
     * @param string $pattern
     * @return void
     */
    public static function addExclusionPattern($pattern)
    {
        if (!in_array($pattern, self::$excludedPatterns)) {
            self::$excludedPatterns[] = $pattern;
        }
    }
    
    /**
     * Add custom translatable type
     *
     * @param string $type
     * @return void
     */
    public static function addTranslatableType($type)
    {
        if (!in_array($type, self::$translatableTypes)) {
            self::$translatableTypes[] = $type;
        }
    }
}

