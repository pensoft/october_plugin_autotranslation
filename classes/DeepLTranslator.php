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
     */
    protected function createClient(): Translator
    {
        $apiKey = $this->getApiKey();
        $this->validateApiKey($apiKey);

        $options = $this->buildClientOptions();

        return new Translator($apiKey, $options);
    }

    /**
     * Get API key from settings
     */
    protected function getApiKey(): ?string
    {
        return $this->settings ? $this->settings->get('deepl_api_key') : Settings::get('deepl_api_key');
    }

    /**
     * Validate API key
     */
    protected function validateApiKey(?string $apiKey): void
    {
        if (empty($apiKey)) {
            throw new \Exception('DeepL API key is not configured. Please set it in Settings.');
        }
    }

    /**
     * Build client options array
     */
    protected function buildClientOptions(): array
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
     */
    protected function getServerType(): string
    {
        return $this->settings
            ? $this->settings->get('deepl_server_type', 'free')
            : Settings::get('deepl_server_type', 'free');
    }

    /**
     * Translate text content
     */
    public function translateText(string $text, string $sourceLang, string $targetLang, array $options = []): string
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
     */
    protected function isEmptyText(string $text): bool
    {
        return empty($text) || trim($text) === '';
    }

    /**
     * Build translation options for DeepL API
     */
    protected function buildTranslationOptions(array $options = []): array
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
     */
    protected function shouldPreserveHtml(): bool
    {
        $preserveHtmlSetting = $this->settings
            ? $this->settings->get('preserve_html', true)
            : Settings::get('preserve_html', true);

        return $this->preserveHtml && $preserveHtmlSetting;
    }

    /**
     * Check if formality option is provided
     */
    protected function hasFormalityOption(array $options): bool
    {
        return isset($options['formality']) && !empty($options['formality']);
    }

    /**
     * Perform the actual translation
     */
    protected function performTranslation(string $text, string $targetLang, array $deeplOptions): string
    {
        // Use null for auto-detection to avoid issues with language variants
        $result = $this->client->translateText($text, null, $targetLang, $deeplOptions);
        return $result->text;
    }

    /**
     * Log translation error
     */
    protected function logTranslationError(\Exception $e): void
    {
        \Log::error('DeepL Translation Error: ' . $e->getMessage());
    }

    /**
     * Translate multiple texts in batch
     */
    public function translateBatch(array $texts, string $sourceLang, string $targetLang, array $options = []): array
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
     */
    protected function extractNonEmptyTexts(array $texts): array
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
     */
    protected function performBatchTranslation(array $texts, string $sourceLang, string $targetLang, array $deeplOptions): array
    {
        // Use null for auto-detection to avoid issues with language variants and fallback locales
        return $this->client->translateText($texts, null, $targetLang, $deeplOptions);
    }

    /**
     * Merge translated texts back into original array
     */
    protected function mergeTranslatedTexts(array $originalTexts, array $results, array $positions): array
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
     */
    protected function logBatchTranslationError(\Exception $e): void
    {
        \Log::error('DeepL Batch Translation Error: ' . $e->getMessage());
    }

    /**
     * Get available source languages
     */
    public function getSourceLanguages(): array
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
     */
    public function getTargetLanguages(): array
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
     */
    protected function formatLanguagesList(array $languages): array
    {
        $result = [];

        foreach ($languages as $lang) {
            $result[$lang->code] = $lang->name;
        }

        return $result;
    }

    /**
     * Log language retrieval error
     */
    protected function logLanguageRetrievalError(string $type, \Exception $e): void
    {
        \Log::error("Failed to get {$type} languages: " . $e->getMessage());
    }

    /**
     * Check API usage
     */
    public function getUsage(): ?\DeepL\Usage
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
     */
    public function testConnection(): bool
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