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
     * @var bool
     */
    protected $preserveHtml = true;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $apiKey = Settings::get('deepl_api_key');
        
        if (empty($apiKey)) {
            throw new \Exception('DeepL API key is not configured. Please set it in Settings.');
        }
        
        $options = [];
        
        // Set server URL based on plan type
        $serverType = Settings::get('deepl_server_type', 'free');
        if ($serverType === 'free') {
            $options['server_url'] = 'https://api-free.deepl.com';
        }
        
        $this->client = new Translator($apiKey, $options);
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
        if (empty($text) || trim($text) === '') {
            return $text;
        }

        $deeplOptions = [];

        // Preserve HTML formatting for rich content
        if ($this->preserveHtml && Settings::get('preserve_html', true)) {
            $deeplOptions['tag_handling'] = 'html';
        }

        // Add formality if supported and specified
        if (isset($options['formality']) && !empty($options['formality'])) {
            $deeplOptions['formality'] = $options['formality'];
        }

        // For source language, use null for auto-detection to avoid issues with variants
        // DeepL can auto-detect the source language reliably
        $finalSourceLang = null;

        try {
            $result = $this->client->translateText(
                $text,
                $finalSourceLang,
                $targetLang,
                $deeplOptions
            );

            return $result->text;
        } catch (\Exception $e) {
            \Log::error('DeepL Translation Error: ' . $e->getMessage());
            throw $e;
        }
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
        
        // Filter out empty texts but keep track of positions
        $nonEmptyTexts = [];
        $positions = [];
        
        foreach ($texts as $index => $text) {
            if (!empty($text) && trim($text) !== '') {
                $nonEmptyTexts[] = $text;
                $positions[] = $index;
            }
        }
        
        if (empty($nonEmptyTexts)) {
            return $texts;
        }
        
        $deeplOptions = [];
        
        if ($this->preserveHtml && Settings::get('preserve_html', true)) {
            $deeplOptions['tag_handling'] = 'html';
        }
        
        try {
            $results = $this->client->translateText(
                $nonEmptyTexts,
                $sourceLang,
                $targetLang,
                $deeplOptions
            );
            
            // Rebuild the array with translated texts in their original positions
            $translated = $texts;
            foreach ($results as $idx => $result) {
                $originalPosition = $positions[$idx];
                $translated[$originalPosition] = $result->text;
            }
            
            return $translated;
        } catch (\Exception $e) {
            \Log::error('DeepL Batch Translation Error: ' . $e->getMessage());
            throw $e;
        }
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
            $result = [];
            
            foreach ($languages as $lang) {
                $result[$lang->code] = $lang->name;
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('Failed to get source languages: ' . $e->getMessage());
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
            $result = [];
            
            foreach ($languages as $lang) {
                $result[$lang->code] = $lang->name;
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('Failed to get target languages: ' . $e->getMessage());
            return [];
        }
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

