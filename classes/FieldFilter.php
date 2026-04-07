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
     * Field types to exclude from translation (loaded from config)
     *
     * @var array
     */
    protected $excludedTypes;

    /**
     * Field name patterns to exclude (regex patterns, loaded from config)
     *
     * @var array
     */
    protected $excludedPatterns;

    /**
     * Translatable field types (loaded from config)
     *
     * @var array
     */
    protected $translatableTypes;

    /**
     * Constructor
     */
    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings;

        // Load field type configuration from config file
        $this->excludedTypes = \Config::get('pensoft.autotranslation::field_types.excluded_types', []);
        $this->translatableTypes = \Config::get('pensoft.autotranslation::field_types.translatable_types', []);
        $this->excludedPatterns = \Config::get('pensoft.autotranslation::field_patterns.excluded_patterns', []);
    }

    /**
     * Check if field should be translated
     */
    public function shouldTranslate(string $fieldName, array $fieldConfig = []): bool
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
     */
    protected function hasExplicitTranslatableFlag(array $fieldConfig): bool
    {
        return isset($fieldConfig['translatable']) && is_bool($fieldConfig['translatable']);
    }

    /**
     * Check if field name matches excluded patterns
     */
    protected function matchesExcludedPattern(string $fieldName): bool
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
     */
    protected function isCustomExcluded(string $fieldName): bool
    {
        $customExclusions = $this->getCustomExclusions();
        return in_array($fieldName, $customExclusions);
    }

    /**
     * Get field type from config
     */
    protected function getFieldType(array $fieldConfig): string
    {
        return $fieldConfig['type'] ?? 'text';
    }

    /**
     * Check if field type is translatable
     */
    protected function isTranslatableType(string $fieldType): bool
    {
        if (in_array($fieldType, $this->excludedTypes)) {
            return false;
        }

        return in_array($fieldType, $this->translatableTypes);
    }

    /**
     * Check if field contains HTML/rich content
     */
    public function isRichContent(array $fieldConfig = []): bool
    {
        $fieldType = $this->getFieldType($fieldConfig);
        return $this->isRichContentType($fieldType);
    }

    /**
     * Check if field type is rich content type
     */
    protected function isRichContentType(string $fieldType): bool
    {
        $richTypes = ['richeditor', 'mlricheditor', 'markdown', 'mlmarkdowneditor'];
        return in_array($fieldType, $richTypes);
    }

    /**
     * Get custom excluded fields from settings
     */
    public function getCustomExclusions(): array
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
     */
    protected function parseExclusionsList(string $excluded): array
    {
        $fields = preg_split('/[\r\n,]+/', $excluded);
        $fields = array_map('trim', $fields);
        return array_filter($fields);
    }

    /**
     * Add custom exclusion pattern
     */
    public function addExclusionPattern(string $pattern): void
    {
        if (!in_array($pattern, $this->excludedPatterns)) {
            $this->excludedPatterns[] = $pattern;
        }
    }

    /**
     * Add custom translatable type
     */
    public function addTranslatableType(string $type): void
    {
        if (!in_array($type, $this->translatableTypes)) {
            $this->translatableTypes[] = $type;
        }
    }
}