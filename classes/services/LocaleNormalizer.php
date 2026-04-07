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
     */
    public function normalize(string $localeCode): string
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
     */
    public function getMappings(): array
    {
        return \Config::get('pensoft.autotranslation::locale_mappings', []);
    }

    /**
     * Normalize multiple locale codes at once
     */
    public function normalizeMultiple(array $localeCodes): array
    {
        return array_map([$this, 'normalize'], $localeCodes);
    }

    /**
     * Check if a locale code needs normalization
     */
    public function needsNormalization(string $localeCode): bool
    {
        return $this->normalize($localeCode) !== $localeCode;
    }
}