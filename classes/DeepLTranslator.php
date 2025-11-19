<?php namespace Pensoft\AutoTranslation\Classes;

use DeepL\Translator;
use Pensoft\AutoTranslation\Models\Settings;

/**
 * DeepL Translation Service
 */
class DeepLTranslator
{
    /**
     * @var Translator
     */
    protected $client;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var bool
     */
    protected $preserveHtml = true;

    /**
     * Constructor
     *
     * @param Settings|null $settings
     * @param Translator|null $client
     */
    public function __construct(?Settings $settings = null, ?Translator $client = null)
    {
        $this->settings = $settings;

        if ($client) {
            $this->client = $client;
        } else {
            $this->client = $this->createClient();
        }
    }

    /**
     * Create DeepL client instance
     *
     * @return Translator
     * @throws \Exception
     */
    protected function createClient()
    {
        $apiKey = $this->getApiKey();
        $this->validateApiKey($apiKey);

        $options = $this->buildClientOptions();

        return new Translator($apiKey, $options);
    }

    /**
     * Get API key from settings
     *
     * @return string|null
     */
    protected function getApiKey()
    {
        return $this->settings ? $this->settings->get('deepl_api_key') : Settings::get('deepl_api_key');
    }

    /**
     * Validate API key
     *
     * @param string|null $apiKey
     * @return void
     * @throws \Exception
     */
    protected function validateApiKey($apiKey)
    {
        if (empty($apiKey)) {
            throw new \Exception('DeepL API key is not configured. Please set it in Settings.');
        }
    }

    /**
     * Build client options array
     *
     * @return array
     */
    protected function buildClientOptions()
    {
        $options = [];
        $serverType = $this->getServerType();

        if ($serverType === 'free') {
            $options['server_url'] = 'https://api-free.deepl.com';
        }

        return $options;
    }

    /**
     * Get server type from settings
     *
     * @return string
     */
    protected function getServerType()
    {
        return $this->settings
            ? $this->settings->get('deepl_server_type', 'free')
            : Settings::get('deepl_server_type', 'free');
    }
    
    /**
     * Translate text content
     *
     * @param string $text
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $options
     * @return string
     */
    public function translateText($text, $sourceLang, $targetLang, array $options = [])
    {
        if ($this->isEmptyText($text)) {
            return $text;
        }

        $deeplOptions = $this->buildTranslationOptions($options);

        try {
            return $this->performTranslation($text, $targetLang, $deeplOptions);
        } catch (\Exception $e) {
            $this->logTranslationError($e);
            throw $e;
        }
    }

    /**
     * Check if text is empty
     *
     * @param string $text
     * @return bool
     */
    protected function isEmptyText($text)
    {
        return empty($text) || trim($text) === '';
    }

    /**
     * Build translation options for DeepL API
     *
     * @param array $options
     * @return array
     */
    protected function buildTranslationOptions(array $options = [])
    {
        $deeplOptions = [];

        if ($this->shouldPreserveHtml()) {
            $deeplOptions['tag_handling'] = 'html';
        }

        if ($this->hasFormalityOption($options)) {
            $deeplOptions['formality'] = $options['formality'];
        }

        return $deeplOptions;
    }

    /**
     * Check if HTML should be preserved
     *
     * @return bool
     */
    protected function shouldPreserveHtml()
    {
        $preserveHtmlSetting = $this->settings
            ? $this->settings->get('preserve_html', true)
            : Settings::get('preserve_html', true);

        return $this->preserveHtml && $preserveHtmlSetting;
    }

    /**
     * Check if formality option is provided
     *
     * @param array $options
     * @return bool
     */
    protected function hasFormalityOption(array $options)
    {
        return isset($options['formality']) && !empty($options['formality']);
    }

    /**
     * Perform the actual translation
     *
     * @param string $text
     * @param string $targetLang
     * @param array $deeplOptions
     * @return string
     */
    protected function performTranslation($text, $targetLang, array $deeplOptions)
    {
        // Use null for auto-detection to avoid issues with language variants
        $result = $this->client->translateText($text, null, $targetLang, $deeplOptions);
        return $result->text;
    }

    /**
     * Log translation error
     *
     * @param \Exception $e
     * @return void
     */
    protected function logTranslationError(\Exception $e)
    {
        \Log::error('DeepL Translation Error: ' . $e->getMessage());
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
        if (empty($texts)) {
            return [];
        }

        [$nonEmptyTexts, $positions] = $this->extractNonEmptyTexts($texts);

        if (empty($nonEmptyTexts)) {
            return $texts;
        }

        $deeplOptions = $this->buildTranslationOptions($options);

        try {
            $results = $this->performBatchTranslation($nonEmptyTexts, $sourceLang, $targetLang, $deeplOptions);
            return $this->mergeTranslatedTexts($texts, $results, $positions);
        } catch (\Exception $e) {
            $this->logBatchTranslationError($e);
            throw $e;
        }
    }

    /**
     * Extract non-empty texts and track their positions
     *
     * @param array $texts
     * @return array [nonEmptyTexts, positions]
     */
    protected function extractNonEmptyTexts(array $texts)
    {
        $nonEmptyTexts = [];
        $positions = [];

        foreach ($texts as $index => $text) {
            if (!$this->isEmptyText($text)) {
                $nonEmptyTexts[] = $text;
                $positions[] = $index;
            }
        }

        return [$nonEmptyTexts, $positions];
    }

    /**
     * Perform batch translation
     *
     * @param array $texts
     * @param string $sourceLang
     * @param string $targetLang
     * @param array $deeplOptions
     * @return array
     */
    protected function performBatchTranslation(array $texts, $sourceLang, $targetLang, array $deeplOptions)
    {
        // Use null for auto-detection to avoid issues with language variants and fallback locales
        return $this->client->translateText($texts, null, $targetLang, $deeplOptions);
    }

    /**
     * Merge translated texts back into original array
     *
     * @param array $originalTexts
     * @param array $results
     * @param array $positions
     * @return array
     */
    protected function mergeTranslatedTexts(array $originalTexts, array $results, array $positions)
    {
        $translated = $originalTexts;

        foreach ($results as $idx => $result) {
            $originalPosition = $positions[$idx];
            $translated[$originalPosition] = $result->text;
        }

        return $translated;
    }

    /**
     * Log batch translation error
     *
     * @param \Exception $e
     * @return void
     */
    protected function logBatchTranslationError(\Exception $e)
    {
        \Log::error('DeepL Batch Translation Error: ' . $e->getMessage());
    }
    
    /**
     * Get available source languages
     *
     * @return array
     */
    public function getSourceLanguages()
    {
        try {
            $languages = $this->client->getSourceLanguages();
            return $this->formatLanguagesList($languages);
        } catch (\Exception $e) {
            $this->logLanguageRetrievalError('source', $e);
            return [];
        }
    }

    /**
     * Get available target languages
     *
     * @return array
     */
    public function getTargetLanguages()
    {
        try {
            $languages = $this->client->getTargetLanguages();
            return $this->formatLanguagesList($languages);
        } catch (\Exception $e) {
            $this->logLanguageRetrievalError('target', $e);
            return [];
        }
    }

    /**
     * Format languages list to code => name array
     *
     * @param array $languages
     * @return array
     */
    protected function formatLanguagesList(array $languages)
    {
        $result = [];

        foreach ($languages as $lang) {
            $result[$lang->code] = $lang->name;
        }

        return $result;
    }

    /**
     * Log language retrieval error
     *
     * @param string $type
     * @param \Exception $e
     * @return void
     */
    protected function logLanguageRetrievalError($type, \Exception $e)
    {
        \Log::error("Failed to get {$type} languages: " . $e->getMessage());
    }

    /**
     * Check API usage
     *
     * @return \DeepL\Usage|null
     */
    public function getUsage()
    {
        try {
            return $this->client->getUsage();
        } catch (\Exception $e) {
            \Log::error('Failed to get usage: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test API connection
     *
     * @return bool
     */
    public function testConnection()
    {
        try {
            $this->client->getUsage();
            return true;
        } catch (\Exception $e) {
            \Log::error('DeepL connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}

