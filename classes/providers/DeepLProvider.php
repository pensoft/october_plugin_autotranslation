<?php namespace Pensoft\AutoTranslation\Classes\Providers;

use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;

/**
 * DeepL Translation Provider
 *
 * Adapter that wraps DeepLTranslator to implement TranslationProviderInterface
 * Follows Adapter Pattern and Dependency Inversion Principle
 */
class DeepLProvider implements TranslationProviderInterface
{
    /**
     * @var DeepLTranslator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param DeepLTranslator|null $translator
     */
    public function __construct(?DeepLTranslator $translator = null)
    {
        $this->translator = $translator ?: new DeepLTranslator();
    }

    /**
     * Translate a single text
     *
     * @param string $text
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $options
     * @return string
     */
    public function translateText($text, $sourceLang, $targetLang, array $options = [])
    {
        return $this->translator->translateText($text, $sourceLang, $targetLang, $options);
    }

    /**
     * Translate multiple texts in batch
     *
     * @param array $texts
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $options
     * @return array
     */
    public function translateBatch(array $texts, $sourceLang, $targetLang, array $options = [])
    {
        return $this->translator->translateBatch($texts, $sourceLang, $targetLang, $options);
    }

    /**
     * Get available source languages
     *
     * @return array
     */
    public function getSourceLanguages()
    {
        return $this->translator->getSourceLanguages();
    }

    /**
     * Get available target languages
     *
     * @return array
     */
    public function getTargetLanguages()
    {
        return $this->translator->getTargetLanguages();
    }

    /**
     * Get API usage statistics
     *
     * @return \DeepL\Usage|null
     */
    public function getUsage()
    {
        return $this->translator->getUsage();
    }

    /**
     * Test API connection
     *
     * @return bool
     */
    public function testConnection()
    {
        return $this->translator->testConnection();
    }

    /**
     * Get the underlying DeepLTranslator instance
     * Useful for accessing DeepL-specific features
     *
     * @return DeepLTranslator
     */
    public function getTranslator()
    {
        return $this->translator;
    }
}
