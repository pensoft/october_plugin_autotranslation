<?php namespace Pensoft\AutoTranslation\Classes\Contracts;

/**
 * Translation Provider Interface
 *
 * Contract for translation service providers (DeepL, Google Translate, AWS Translate, etc.)
 */
interface TranslationProviderInterface
{
    /**
     * Translate a single text from source to target language
     */
    public function translateText(string $text, string $sourceLang, string $targetLang, array $options = []): string;

    /**
     * Translate multiple texts in a single batch operation
     */
    public function translateBatch(array $texts, string $sourceLang, string $targetLang, array $options = []): array;

    /**
     * Get available source languages
     */
    public function getSourceLanguages(): array;

    /**
     * Get available target languages
     */
    public function getTargetLanguages(): array;

    /**
     * Get API usage statistics (if supported by provider)
     */
    public function getUsage(): mixed;

    /**
     * Test connection to the translation service
     */
    public function testConnection(): bool;
}