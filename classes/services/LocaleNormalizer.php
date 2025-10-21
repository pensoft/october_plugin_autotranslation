<?php namespace Pensoft\AutoTranslation\Classes\Services;

/**
 * Locale Normalizer Service
 *
 * Handles locale code normalization and mapping between October CMS locale codes
 * and translation provider-specific formats (e.g., DeepL requires uppercase codes)
 */
class LocaleNormalizer
{
    /**
     * Normalize locale code for translation provider
     * DeepL requires uppercase codes (BG, CS, etc.) and specific formats (EN-US, PT-BR)
     *
     * @param string $localeCode October CMS locale code
     * @return string Normalized locale code for translation provider
     */
    public function normalize($localeCode)
    {
        // Convert to lowercase first to handle the mapping
        $lower = strtolower($localeCode);

        // Load mappings from config file
        $mapping = $this->getMappings();

        // Return mapped value if exists, otherwise uppercase the original
        return $mapping[$lower] ?? strtoupper($localeCode);
    }

    /**
     * Get locale mappings from configuration
     *
     * Allows customization of locale code mappings via config file
     * Example: ['en' => 'EN-US', 'pt' => 'PT-BR']
     *
     * @return array Mapping of lowercase locale codes to provider-specific codes
     */
    public function getMappings()
    {
        return \Config::get('pensoft.autotranslation::locale_mappings', []);
    }

    /**
     * Normalize multiple locale codes at once
     *
     * @param array $localeCodes Array of locale codes to normalize
     * @return array Array of normalized locale codes
     */
    public function normalizeMultiple(array $localeCodes)
    {
        return array_map([$this, 'normalize'], $localeCodes);
    }

    /**
     * Check if a locale code needs normalization
     *
     * @param string $localeCode
     * @return bool True if the code will be transformed
     */
    public function needsNormalization($localeCode)
    {
        return $this->normalize($localeCode) !== $localeCode;
    }
}
