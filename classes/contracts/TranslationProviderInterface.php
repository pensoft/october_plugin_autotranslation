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
     *
     * @param string $text The text to translate
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Additional translation options (formality, etc.)
     * @return string Translated text
     * @throws \Exception If translation fails
     */
    public function translateText($text, $sourceLang, $targetLang, array $options = []);

    /**
     * Translate multiple texts in a single batch operation
     *
     * @param array $texts Array of texts to translate
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @param array $options Additional translation options
     * @return array Array of translated texts
     * @throws \Exception If translation fails
     */
    public function translateBatch(array $texts, $sourceLang, $targetLang, array $options = []);

    /**
     * Get available source languages
     *
     * @return array Array of language codes and names [code => name]
     */
    public function getSourceLanguages();

    /**
     * Get available target languages
     *
     * @return array Array of language codes and names [code => name]
     */
    public function getTargetLanguages();

    /**
     * Get API usage statistics (if supported by provider)
     *
     * @return mixed Usage information or null if not supported
     */
    public function getUsage();

    /**
     * Test connection to the translation service
     *
     * @return bool True if connection successful
     */
    public function testConnection();
}
