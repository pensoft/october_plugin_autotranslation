<?php namespace Pensoft\AutoTranslation\Classes;

use Pensoft\AutoTranslation\Models\Settings;

/**
 * Field Filter - Determines which fields should be translated
 */
class FieldFilter
{
    /**
     * @var Settings
     */
    protected $settings;

    /**
     * Default field types to exclude from translation
     *
     * @var array
     */
    protected $excludedTypes = [
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
    protected $excludedPatterns = [
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
    protected $translatableTypes = [
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
     * Constructor
     *
     * @param Settings|null $settings
     */
    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings;
    }
    
    /**
     * Check if field should be translated
     *
     * @param string $fieldName
     * @param array $fieldConfig
     * @return bool
     */
    public function shouldTranslate($fieldName, array $fieldConfig = [])
    {
        if ($this->hasExplicitTranslatableFlag($fieldConfig)) {
            return $fieldConfig['translatable'];
        }

        if ($this->matchesExcludedPattern($fieldName)) {
            return false;
        }

        if ($this->isCustomExcluded($fieldName)) {
            return false;
        }

        $fieldType = $this->getFieldType($fieldConfig);

        return $this->isTranslatableType($fieldType);
    }

    /**
     * Check if field config has explicit translatable flag
     *
     * @param array $fieldConfig
     * @return bool
     */
    protected function hasExplicitTranslatableFlag(array $fieldConfig)
    {
        return isset($fieldConfig['translatable']) && is_bool($fieldConfig['translatable']);
    }

    /**
     * Check if field name matches excluded patterns
     *
     * @param string $fieldName
     * @return bool
     */
    protected function matchesExcludedPattern($fieldName)
    {
        foreach ($this->excludedPatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if field is in custom exclusions list
     *
     * @param string $fieldName
     * @return bool
     */
    protected function isCustomExcluded($fieldName)
    {
        $customExclusions = $this->getCustomExclusions();
        return in_array($fieldName, $customExclusions);
    }

    /**
     * Get field type from config
     *
     * @param array $fieldConfig
     * @return string
     */
    protected function getFieldType(array $fieldConfig)
    {
        return $fieldConfig['type'] ?? 'text';
    }

    /**
     * Check if field type is translatable
     *
     * @param string $fieldType
     * @return bool
     */
    protected function isTranslatableType($fieldType)
    {
        if (in_array($fieldType, $this->excludedTypes)) {
            return false;
        }

        return in_array($fieldType, $this->translatableTypes);
    }
    
    /**
     * Check if field contains HTML/rich content
     *
     * @param array $fieldConfig
     * @return bool
     */
    public function isRichContent(array $fieldConfig = [])
    {
        $fieldType = $this->getFieldType($fieldConfig);
        return $this->isRichContentType($fieldType);
    }

    /**
     * Check if field type is rich content type
     *
     * @param string $fieldType
     * @return bool
     */
    protected function isRichContentType($fieldType)
    {
        $richTypes = ['richeditor', 'mlricheditor', 'markdown', 'mlmarkdowneditor'];
        return in_array($fieldType, $richTypes);
    }

    /**
     * Get custom excluded fields from settings
     *
     * @return array
     */
    public function getCustomExclusions()
    {
        if (!$this->settings) {
            return [];
        }

        $excluded = $this->settings->get('excluded_fields', '');

        if (empty($excluded)) {
            return [];
        }

        return $this->parseExclusionsList($excluded);
    }

    /**
     * Parse exclusions list from string
     *
     * @param string $excluded
     * @return array
     */
    protected function parseExclusionsList($excluded)
    {
        $fields = preg_split('/[\r\n,]+/', $excluded);
        $fields = array_map('trim', $fields);
        return array_filter($fields);
    }

    /**
     * Add custom exclusion pattern
     *
     * @param string $pattern
     * @return void
     */
    public function addExclusionPattern($pattern)
    {
        if (!in_array($pattern, $this->excludedPatterns)) {
            $this->excludedPatterns[] = $pattern;
        }
    }

    /**
     * Add custom translatable type
     *
     * @param string $type
     * @return void
     */
    public function addTranslatableType($type)
    {
        if (!in_array($type, $this->translatableTypes)) {
            $this->translatableTypes[] = $type;
        }
    }
}

