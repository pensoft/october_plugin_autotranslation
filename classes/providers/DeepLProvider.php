<?php namespace Pensoft\AutoTranslation\Classes\Providers;

use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;

/**
 * DeepL Translation Provider
 *
 * Adapter that wraps DeepLTranslator to implement TranslationProviderInterface
 */
class DeepLProvider implements TranslationProviderInterface
{
    /**
     * @var DeepLTranslator
     */
    protected $translator;

    /**
     * Constructor
     */
    public function __construct(?DeepLTranslator $translator = null)
    {
        $this->translator = $translator ?: new DeepLTranslator();
    }

    /**
     * Translate a single text
     */
    public function translateText(string $text, string $sourceLang, string $targetLang, array $options = []): string
    {
        return $this->translator->translateText($text, $sourceLang, $targetLang, $options);
    }

    /**
     * Translate multiple texts in batch
     */
    public function translateBatch(array $texts, string $sourceLang, string $targetLang, array $options = []): array
    {
        return $this->translator->translateBatch($texts, $sourceLang, $targetLang, $options);
    }

    /**
     * Get available source languages
     */
    public function getSourceLanguages(): array
    {
        return $this->translator->getSourceLanguages();
    }

    /**
     * Get available target languages
     */
    public function getTargetLanguages(): array
    {
        return $this->translator->getTargetLanguages();
    }

    /**
     * Get API usage statistics
     */
    public function getUsage(): mixed
    {
        return $this->translator->getUsage();
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        return $this->translator->testConnection();
    }

    /**
     * Get the underlying DeepLTranslator instance
     * Useful for accessing DeepL-specific features
     */
    public function getTranslator(): DeepLTranslator
    {
        return $this->translator;
    }
}