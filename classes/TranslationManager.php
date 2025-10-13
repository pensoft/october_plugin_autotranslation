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
     */
    public function __construct()
    {
        $this->translator = new DeepLTranslator();
        $this->filter = new FieldFilter();
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
        // Ensure model has TranslatableModel behavior
        if (!$model->isClassExtendedWith(TranslatableModel::class)) {
            throw new \Exception('Model must implement TranslatableModel behavior');
        }

        $translated = [];
        $translatableAttributes = $this->getTranslatableAttributes($model);

        // Filter by selected fields if specified
        $selectedFields = isset($options['fields']) ? $options['fields'] : [];
        if (!empty($selectedFields)) {
            $translatableAttributes = array_intersect($translatableAttributes, $selectedFields);
        }

        // Get overwrite setting
        $overwrite = isset($options['overwrite']) ? $options['overwrite'] : false;

        // Get source language values
        $model->translateContext($sourceLocale);

        foreach ($translatableAttributes as $attribute) {
            $sourceValue = $model->getAttribute($attribute);

            // If source is empty, try fallback to any available locale
            if (empty($sourceValue) || trim($sourceValue) === '') {
                \Log::debug("Model {$model->id} attribute '{$attribute}' empty in locale '{$sourceLocale}'");
                continue;
            }

            // Check if already translated (skip unless overwrite is true)
            $model->translateContext($targetLocale);
            $existingTranslation = $model->getAttribute($attribute);

            if (!empty($existingTranslation) && !$overwrite) {
                \Log::debug("Model {$model->id} attribute '{$attribute}' already translated, skipping");
                $model->translateContext($sourceLocale);
                continue;
            }

            try {
                // Translate the value
                $translatedValue = $this->translator->translateText(
                    $sourceValue,
                    $sourceLocale,
                    $targetLocale,
                    $options
                );

                $translated[$attribute] = $translatedValue;

                \Log::info("Translated model {$model->id} attribute '{$attribute}' from {$sourceLocale} to {$targetLocale}");

            } catch (\Exception $e) {
                \Log::error("Failed to translate attribute '{$attribute}' for model {$model->id}: " . $e->getMessage());
            }
        }

        // Save the model with translations in the target locale context
        if (!empty($translated)) {
            // Switch to target locale and set all translated values
            $model->translateContext($targetLocale);
            foreach ($translated as $attribute => $value) {
                $model->setAttribute($attribute, $value);
            }
            $model->save();

            // Reset to default locale after saving
            $model->noFallbackLocale();
        }

        return $translated;
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
        // Check if target language is supported by DeepL
        $availableLanguages = $this->translator->getTargetLanguages();

        if (!isset($availableLanguages[$targetLocale])) {
            \Log::warning("Target language '{$targetLocale}' is not supported by DeepL", [
                'available_languages' => array_keys($availableLanguages)
            ]);

            throw new \Exception("Language '{$targetLocale}' is not supported by your DeepL account. Available languages: " . implode(', ', array_keys($availableLanguages)) . ". Please ensure your locale code matches DeepL's format (e.g., EN-US, BG, PT-BR).");
        }

        $query = Message::query();

        if (!empty($messageIds)) {
            $query->whereIn('id', $messageIds);
        }

        $messages = $query->get();
        $count = 0;
        $skipped = ['empty' => 0, 'already_translated' => 0];

        \Log::info("Starting translation: {$messages->count()} messages from {$sourceLocale} to {$targetLocale}, overwrite={$overwrite}");

        foreach ($messages as $message) {
            $sourceText = $message->forLocale($sourceLocale);

            // If source locale is empty, try to get text from default locale or any available locale
            if (empty($sourceText) || trim($sourceText) === '') {
                // Get all available message data
                $messageData = $message->message_data ?? [];

                if (!empty($messageData) && is_array($messageData)) {
                    // Try to find text in any available locale
                    $sourceText = reset($messageData);
                    $actualSourceLocale = key($messageData);

                    \Log::debug("Message ID {$message->id}: No text in '{$sourceLocale}', using locale '{$actualSourceLocale}' instead");
                }
            }

            \Log::debug("Message ID {$message->id} - code: {$message->code}, source text: " . substr($sourceText ?? 'NULL', 0, 50));

            if (empty($sourceText) || trim($sourceText) === '') {
                $skipped['empty']++;
                \Log::debug("Message ID {$message->id} - SKIPPED: empty source");
                continue;
            }

            // Check if already translated (skip unless overwrite is true)
            $existingTranslation = $message->forLocale($targetLocale);
            if (!empty($existingTranslation) && !$overwrite) {
                $skipped['already_translated']++;
                \Log::debug("Message ID {$message->id} - SKIPPED: already translated (existing: " . substr($existingTranslation, 0, 50) . ")");
                continue;
            }

            try {
                \Log::info("Message ID {$message->id} - TRANSLATING from {$sourceLocale} to {$targetLocale}");

                $translatedText = $this->translator->translateText(
                    $sourceText,
                    $sourceLocale,
                    $targetLocale
                );

                \Log::info("Message ID {$message->id} - Translation result: " . substr($translatedText, 0, 50));

                $message->toLocale($targetLocale, $translatedText);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Failed to translate message ID {$message->id}: " . $e->getMessage());
            }
        }

        \Log::info("Translation complete: {$count} translated, {$skipped['empty']} empty, {$skipped['already_translated']} already translated");

        return $count;
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

