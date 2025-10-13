<?php namespace Pensoft\AutoTranslation\Classes;

use RainLab\Translate\Models\Locale;
use RainLab\Translate\Models\Message;
use RainLab\Translate\Behaviors\TranslatableModel;
use October\Rain\Database\Model;

/**
 * Translation Manager - Orchestrates translation operations
 */
class TranslationManager
{
    /**
     * @var DeepLTranslator
     */
    protected $translator;

    /**
     * @var FieldFilter
     */
    protected $filter;

    /**
     * Constructor
     *
     * @param DeepLTranslator|null $translator
     * @param FieldFilter|null $filter
     */
    public function __construct(?DeepLTranslator $translator = null, ?FieldFilter $filter = null)
    {
        $this->translator = $translator ?: new DeepLTranslator();
        $this->filter = $filter ?: new FieldFilter();
    }
    
    /**
     * Translate model attributes from source to target locale
     *
     * @param Model $model
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function translateModel(Model $model, $sourceLocale, $targetLocale, array $options = [])
    {
        $this->validateTranslatableModel($model);

        // Normalize locale codes for DeepL API
        $normalizedSource = $this->normalizeLocaleCode($sourceLocale);
        $normalizedTarget = $this->normalizeLocaleCode($targetLocale);

        $attributes = $this->prepareAttributesForTranslation($model, $options);
        $overwrite = $options['overwrite'] ?? false;

        $translated = $this->translateAttributes($model, $attributes, $normalizedSource, $normalizedTarget, $overwrite, $options);

        if (!empty($translated)) {
            $this->saveTranslatedModel($model, $translated, $targetLocale);
        }

        return $translated;
    }

    /**
     * Validate that model implements TranslatableModel behavior
     *
     * @param Model $model
     * @return void
     * @throws \Exception
     */
    protected function validateTranslatableModel(Model $model)
    {
        if (!$model->isClassExtendedWith(TranslatableModel::class)) {
            throw new \Exception('Model must implement TranslatableModel behavior');
        }
    }

    /**
     * Prepare attributes list for translation
     *
     * @param Model $model
     * @param array $options
     * @return array
     */
    protected function prepareAttributesForTranslation(Model $model, array $options)
    {
        $translatableAttributes = $this->getTranslatableAttributes($model);

        $selectedFields = $options['fields'] ?? [];
        if (!empty($selectedFields)) {
            return array_intersect($translatableAttributes, $selectedFields);
        }

        return $translatableAttributes;
    }

    /**
     * Translate all attributes
     *
     * @param Model $model
     * @param array $attributes
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param bool $overwrite
     * @param array $options
     * @return array
     */
    protected function translateAttributes(Model $model, array $attributes, $sourceLocale, $targetLocale, $overwrite, array $options)
    {
        $translated = [];
        $model->translateContext($sourceLocale);

        foreach ($attributes as $attribute) {
            $result = $this->translateAttribute($model, $attribute, $sourceLocale, $targetLocale, $overwrite, $options);

            if ($result !== null) {
                $translated[$attribute] = $result;
            }
        }

        return $translated;
    }

    /**
     * Translate a single attribute
     *
     * @param Model $model
     * @param string $attribute
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param bool $overwrite
     * @param array $options
     * @return string|null
     */
    protected function translateAttribute(Model $model, $attribute, $sourceLocale, $targetLocale, $overwrite, array $options)
    {
        $sourceValue = $model->getAttribute($attribute);

        if ($this->isEmptyValue($sourceValue)) {
            $this->logEmptyAttribute($model, $attribute, $sourceLocale);
            return null;
        }

        if ($this->shouldSkipTranslation($model, $attribute, $targetLocale, $overwrite)) {
            $model->translateContext($sourceLocale);
            return null;
        }

        try {
            $translatedValue = $this->translator->translateText($sourceValue, $sourceLocale, $targetLocale, $options);
            $this->logSuccessfulTranslation($model, $attribute, $sourceLocale, $targetLocale);
            return $translatedValue;
        } catch (\Exception $e) {
            $this->logTranslationFailure($model, $attribute, $e);
            return null;
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmptyValue($value)
    {
        return empty($value) || trim($value) === '';
    }

    /**
     * Check if translation should be skipped
     *
     * @param Model $model
     * @param string $attribute
     * @param string $targetLocale
     * @param bool $overwrite
     * @return bool
     */
    protected function shouldSkipTranslation(Model $model, $attribute, $targetLocale, $overwrite)
    {
        $model->translateContext($targetLocale);
        $existingTranslation = $model->getAttribute($attribute);

        if (!empty($existingTranslation) && !$overwrite) {
            $this->logSkippedTranslation($model, $attribute);
            return true;
        }

        return false;
    }

    /**
     * Save translated model
     *
     * @param Model $model
     * @param array $translated
     * @param string $targetLocale
     * @return void
     */
    protected function saveTranslatedModel(Model $model, array $translated, $targetLocale)
    {
        $model->translateContext($targetLocale);

        foreach ($translated as $attribute => $value) {
            $model->setAttribute($attribute, $value);
        }

        $model->save();
        $model->noFallbackLocale();
    }

    /**
     * Log empty attribute
     *
     * @param Model $model
     * @param string $attribute
     * @param string $sourceLocale
     * @return void
     */
    protected function logEmptyAttribute(Model $model, $attribute, $sourceLocale)
    {
        \Log::debug("Model {$model->id} attribute '{$attribute}' empty in locale '{$sourceLocale}'");
    }

    /**
     * Log skipped translation
     *
     * @param Model $model
     * @param string $attribute
     * @return void
     */
    protected function logSkippedTranslation(Model $model, $attribute)
    {
        \Log::debug("Model {$model->id} attribute '{$attribute}' already translated, skipping");
    }

    /**
     * Log successful translation
     *
     * @param Model $model
     * @param string $attribute
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return void
     */
    protected function logSuccessfulTranslation(Model $model, $attribute, $sourceLocale, $targetLocale)
    {
        \Log::info("Translated model {$model->id} attribute '{$attribute}' from {$sourceLocale} to {$targetLocale}");
    }

    /**
     * Log translation failure
     *
     * @param Model $model
     * @param string $attribute
     * @param \Exception $e
     * @return void
     */
    protected function logTranslationFailure(Model $model, $attribute, \Exception $e)
    {
        \Log::error("Failed to translate attribute '{$attribute}' for model {$model->id}: " . $e->getMessage());
    }
    
    /**
     * Translate messages from source to target locale
     *
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array $messageIds
     * @param bool $overwrite
     * @return int Number of translated messages
     */
    public function translateMessages($sourceLocale, $targetLocale, array $messageIds = [], $overwrite = false)
    {
        $normalizedTarget = $this->normalizeLocaleCode($targetLocale);
        $normalizedSource = $this->normalizeLocaleCode($sourceLocale);

        $this->validateTargetLanguage($normalizedTarget);

        $messages = $this->fetchMessages($messageIds);
        $stats = ['count' => 0, 'skipped' => ['empty' => 0, 'already_translated' => 0]];

        $this->logTranslationStart($messages, $sourceLocale, $targetLocale, $overwrite);

        foreach ($messages as $message) {
            $this->processMessage($message, $normalizedSource, $normalizedTarget, $overwrite, $stats);
        }

        $this->logTranslationComplete($stats);

        return $stats['count'];
    }

    /**
     * Normalize locale code for DeepL API
     * DeepL requires uppercase codes (BG, CS, etc.) and specific formats (EN-US, PT-BR)
     *
     * @param string $localeCode
     * @return string
     */
    protected function normalizeLocaleCode($localeCode)
    {
        // Convert to uppercase
        $normalized = strtoupper($localeCode);

        // Handle common variations
        $mapping = [
            'EN' => 'EN-US',  // Default English to US
            'PT' => 'PT-PT',  // Default Portuguese to Portugal
            'ZH' => 'ZH-HANS' // Default Chinese to Simplified
        ];

        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * Validate target language is supported
     *
     * @param string $targetLocale
     * @return void
     * @throws \Exception
     */
    protected function validateTargetLanguage($targetLocale)
    {
        $availableLanguages = $this->translator->getTargetLanguages();

        if (!isset($availableLanguages[$targetLocale])) {
            $this->logUnsupportedLanguage($targetLocale, $availableLanguages);
            $this->throwUnsupportedLanguageException($targetLocale, $availableLanguages);
        }
    }

    /**
     * Fetch messages to translate
     *
     * @param array $messageIds
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function fetchMessages(array $messageIds)
    {
        $query = Message::query();

        if (!empty($messageIds)) {
            $query->whereIn('id', $messageIds);
        }

        return $query->get();
    }

    /**
     * Process a single message
     *
     * @param Message $message
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param bool $overwrite
     * @param array &$stats
     * @return void
     */
    protected function processMessage(Message $message, $sourceLocale, $targetLocale, $overwrite, array &$stats)
    {
        $sourceText = $this->getMessageSourceText($message, $sourceLocale);

        if ($this->isEmptyValue($sourceText)) {
            $stats['skipped']['empty']++;
            $this->logEmptyMessage($message);
            return;
        }

        if ($this->shouldSkipMessage($message, $targetLocale, $overwrite)) {
            $stats['skipped']['already_translated']++;
            return;
        }

        $this->translateAndSaveMessage($message, $sourceText, $sourceLocale, $targetLocale, $stats);
    }

    /**
     * Get source text for message
     *
     * @param Message $message
     * @param string $sourceLocale
     * @return string|null
     */
    protected function getMessageSourceText(Message $message, $sourceLocale)
    {
        $sourceText = $message->forLocale($sourceLocale);

        if ($this->isEmptyValue($sourceText)) {
            return $this->getFallbackSourceText($message, $sourceLocale);
        }

        return $sourceText;
    }

    /**
     * Get fallback source text from any available locale
     *
     * @param Message $message
     * @param string $sourceLocale
     * @return string|null
     */
    protected function getFallbackSourceText(Message $message, $sourceLocale)
    {
        $messageData = $message->message_data ?? [];

        if (empty($messageData) || !is_array($messageData)) {
            return null;
        }

        $sourceText = reset($messageData);
        $actualSourceLocale = key($messageData);

        \Log::debug("Message ID {$message->id}: No text in '{$sourceLocale}', using locale '{$actualSourceLocale}' instead");

        return $sourceText;
    }

    /**
     * Check if message should be skipped
     *
     * @param Message $message
     * @param string $targetLocale
     * @param bool $overwrite
     * @return bool
     */
    protected function shouldSkipMessage(Message $message, $targetLocale, $overwrite)
    {
        $existingTranslation = $message->forLocale($targetLocale);

        if (!empty($existingTranslation) && !$overwrite) {
            $this->logSkippedMessage($message, $existingTranslation);
            return true;
        }

        return false;
    }

    /**
     * Translate and save message
     *
     * @param Message $message
     * @param string $sourceText
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array &$stats
     * @return void
     */
    protected function translateAndSaveMessage(Message $message, $sourceText, $sourceLocale, $targetLocale, array &$stats)
    {
        try {
            $this->logMessageTranslationStart($message, $sourceLocale, $targetLocale);

            $translatedText = $this->translator->translateText($sourceText, $sourceLocale, $targetLocale);

            $this->logMessageTranslationResult($message, $translatedText);

            $message->toLocale($targetLocale, $translatedText);
            $stats['count']++;
        } catch (\Exception $e) {
            \Log::error("Failed to translate message ID {$message->id}: " . $e->getMessage());
        }
    }

    /**
     * Log unsupported language warning
     *
     * @param string $targetLocale
     * @param array $availableLanguages
     * @return void
     */
    protected function logUnsupportedLanguage($targetLocale, array $availableLanguages)
    {
        \Log::warning("Target language '{$targetLocale}' is not supported by DeepL", [
            'available_languages' => array_keys($availableLanguages)
        ]);
    }

    /**
     * Throw unsupported language exception
     *
     * @param string $targetLocale
     * @param array $availableLanguages
     * @return void
     * @throws \Exception
     */
    protected function throwUnsupportedLanguageException($targetLocale, array $availableLanguages)
    {
        $languagesList = implode(', ', array_keys($availableLanguages));
        throw new \Exception("Language '{$targetLocale}' is not supported by your DeepL account. Available languages: {$languagesList}. Please ensure your locale code matches DeepL's format (e.g., EN-US, BG, PT-BR).");
    }

    /**
     * Log translation start
     *
     * @param \Illuminate\Database\Eloquent\Collection $messages
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param bool $overwrite
     * @return void
     */
    protected function logTranslationStart($messages, $sourceLocale, $targetLocale, $overwrite)
    {
        \Log::info("Starting translation: {$messages->count()} messages from {$sourceLocale} to {$targetLocale}, overwrite={$overwrite}");
    }

    /**
     * Log empty message
     *
     * @param Message $message
     * @return void
     */
    protected function logEmptyMessage(Message $message)
    {
        \Log::debug("Message ID {$message->id} - SKIPPED: empty source");
    }

    /**
     * Log skipped message
     *
     * @param Message $message
     * @param string $existingTranslation
     * @return void
     */
    protected function logSkippedMessage(Message $message, $existingTranslation)
    {
        $preview = substr($existingTranslation, 0, 50);
        \Log::debug("Message ID {$message->id} - SKIPPED: already translated (existing: {$preview})");
    }

    /**
     * Log message translation start
     *
     * @param Message $message
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return void
     */
    protected function logMessageTranslationStart(Message $message, $sourceLocale, $targetLocale)
    {
        \Log::info("Message ID {$message->id} - TRANSLATING from {$sourceLocale} to {$targetLocale}");
    }

    /**
     * Log message translation result
     *
     * @param Message $message
     * @param string $translatedText
     * @return void
     */
    protected function logMessageTranslationResult(Message $message, $translatedText)
    {
        $preview = substr($translatedText, 0, 50);
        \Log::info("Message ID {$message->id} - Translation result: {$preview}");
    }

    /**
     * Log translation complete
     *
     * @param array $stats
     * @return void
     */
    protected function logTranslationComplete(array $stats)
    {
        \Log::info("Translation complete: {$stats['count']} translated, {$stats['skipped']['empty']} empty, {$stats['skipped']['already_translated']} already translated");
    }
    
    /**
     * Translate multiple models in batch
     *
     * @param string $modelClass
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array $modelIds
     * @param array $options
     * @return int Number of translated models
     */
    public function translateModels($modelClass, $sourceLocale, $targetLocale, array $modelIds = [], array $options = [])
    {
        if (!class_exists($modelClass)) {
            throw new \Exception("Model class {$modelClass} not found");
        }
        
        $query = $modelClass::query();
        
        if (!empty($modelIds)) {
            $query->whereIn('id', $modelIds);
        }
        
        $models = $query->get();
        $count = 0;
        
        foreach ($models as $model) {
            try {
                $this->translateModel($model, $sourceLocale, $targetLocale, $options);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Failed to translate model {$modelClass} ID {$model->id}: " . $e->getMessage());
            }
        }
        
        return $count;
    }
    
    /**
     * Get translatable attributes from model
     *
     * @param Model $model
     * @return array
     */
    protected function getTranslatableAttributes(Model $model)
    {
        if (isset($model->translatable)) {
            $attributes = $model->translatable;

            // Handle associative array format (with options)
            $result = [];
            foreach ($attributes as $key => $value) {
                if (is_numeric($key)) {
                    // Simple string format
                    $fieldName = is_array($value) ? $value[0] : $value;
                } else {
                    // Key-value format
                    $fieldName = $key;
                }

                // Filter out excluded fields using FieldFilter
                if ($this->filter->shouldTranslate($fieldName)) {
                    $result[] = $fieldName;
                }
            }

            return $result;
        }

        return [];
    }
    
    /**
     * Get enabled locales from Rainlab.Translate
     *
     * @return array
     */
    public function getEnabledLocales()
    {
        return Locale::listEnabled();
    }
    
    /**
     * Get default locale
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        $locale = Locale::getDefault();
        return $locale ? $locale->code : 'en';
    }
    
    /**
     * Check if a locale is enabled
     *
     * @param string $localeCode
     * @return bool
     */
    public function isLocaleEnabled($localeCode)
    {
        return Locale::isValid($localeCode);
    }

    /**
     * Get translation statistics
     *
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return array
     */
    public function getTranslationStats($sourceLocale, $targetLocale)
    {
        $stats = [
            'messages_total' => 0,
            'messages_translated' => 0,
            'messages_missing' => 0,
        ];
        
        $messages = Message::all();
        
        foreach ($messages as $message) {
            $stats['messages_total']++;
            
            $sourceText = $message->forLocale($sourceLocale);
            $targetText = $message->forLocale($targetLocale);
            
            if (!empty($targetText)) {
                $stats['messages_translated']++;
            } elseif (!empty($sourceText)) {
                $stats['messages_missing']++;
            }
        }
        
        return $stats;
    }
}

