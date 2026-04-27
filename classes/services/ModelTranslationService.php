<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Classes\Locale;
use RainLab\Translate\Behaviors\TranslatableModel;
use October\Rain\Database\Model;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\FieldFilter;
use Pensoft\AutoTranslation\Classes\Strategies\DeepLBatchStrategy;
use Pensoft\AutoTranslation\Classes\Services\TranslationBatchCollector;

/**
 * Model Translation Service
 *
 * Handles translation of October CMS models that implement TranslatableModel behavior
 */
class ModelTranslationService
{
    /**
     * @var TranslationProviderInterface
     */
    protected $provider;

    /**
     * @var FieldFilter
     */
    protected $filter;

    /**
     * @var LocaleNormalizer
     */
    protected $normalizer;

    /**
     * @var TranslationBatchCollector
     */
    protected $batchCollector;

    /**
     * Constructor
     */
    public function __construct(
        TranslationProviderInterface $provider,
        ?FieldFilter $filter = null,
        ?LocaleNormalizer $normalizer = null,
        ?TranslationBatchCollector $batchCollector = null
    )
    {
        $this->provider = $provider;
        $this->filter = $filter ?: new FieldFilter();
        $this->normalizer = $normalizer ?: new LocaleNormalizer();
        $this->batchCollector = $batchCollector ?: new TranslationBatchCollector();
    }

    /**
     * Translate model attributes from source to target locale
     */
    public function translateModel(Model $model, string $sourceLocale, string $targetLocale, array $options = []): array
    {
        $this->validateTranslatableModel($model);

        // Normalize locale codes for translation provider
        $normalizedSource = $this->normalizer->normalize($sourceLocale);
        $normalizedTarget = $this->normalizer->normalize($targetLocale);

        $attributes = $this->prepareAttributesForTranslation($model, $options);
        $overwrite = $options['overwrite'] ?? false;

        // Translate attributes
        $translated = $this->translateAttributes(
            $model,
            $attributes,
            $sourceLocale,      // Original for storage
            $targetLocale,      // Original for storage
            $normalizedSource,  // Normalized for API
            $normalizedTarget,  // Normalized for API
            $overwrite,
            $options
        );

        if (!empty($translated)) {
            // Save translated attributes
            $this->saveTranslatedModel($model, $translated, $targetLocale);
        }

        return $translated;
    }

    /**
     * Translate multiple models using batch processing (recommended for performance)
     */
    public function translateModelsInBatch(
        string $modelClass,
        string $sourceLocale,
        string $targetLocale,
        array $modelIds = [],
        array $options = []
    ): int
    {
        if (!class_exists($modelClass)) {
            throw new \Exception("Model class {$modelClass} not found");
        }

        $query = $modelClass::query();

        if (!empty($modelIds)) {
            $query->whereIn('id', $modelIds);
        }

        $models = $query->get();

        if ($models->isEmpty()) {
            return 0;
        }

        // Normalize locale codes
        $normalizedSource = $this->normalizer->normalize($sourceLocale);
        $normalizedTarget = $this->normalizer->normalize($targetLocale);

        $overwrite = $options['overwrite'] ?? false;
        $batchSize = $options['batch_size'] ?? null;

        // Prepare translatable attributes
        $firstModel = $models->first();
        $this->validateTranslatableModel($firstModel);
        $attributes = $this->prepareAttributesForTranslation($firstModel, $options);

        if (empty($attributes)) {
            \Log::warning("No translatable attributes found for model {$modelClass}");
            return 0;
        }

        \Log::info("Starting batch translation for {$models->count()} {$modelClass} models, {$sourceLocale} -> {$targetLocale}");

        // Collect all translatable texts from all models
        $collection = $this->batchCollector->collectFromModels($models, $attributes, $sourceLocale, $targetLocale, $overwrite);

        if (empty($collection['texts'])) {
            \Log::info("No texts to translate (skipped: {$collection['stats']['skipped_empty']} empty, {$collection['stats']['skipped_existing']} existing)");
            return 0;
        }

        // Create batch strategy and process
        $batchStrategy = new DeepLBatchStrategy($this->provider, $batchSize);
        $batches = $batchStrategy->createBatches($collection['texts']);

        \Log::info("Processing " . count($collection['texts']) . " texts in " . count($batches) . " batch(es)");

        // Process all batches
        $allResults = [];
        foreach ($batches as $batchIndex => $batch) {
            try {
                \Log::debug("Processing batch " . ($batchIndex + 1) . " of " . count($batches) . " (" . count($batch) . " items)");

                $results = $batchStrategy->processBatch($batch, $normalizedSource, $normalizedTarget, $options);
                $allResults = array_merge($allResults, $results);
            } catch (\Exception $e) {
                \Log::error("Batch translation failed for batch {$batchIndex}: " . $e->getMessage());
                continue;
            }
        }

        // Map results back and save
        $mapped = $this->batchCollector->mapResults($allResults, $collection['map']);

        // Group by model for efficient saving
        $translationsByModel = [];
        foreach ($mapped as $result) {
            $model = $result['item']['model'];
            $attribute = $result['item']['attribute'];
            $translatedText = $result['translated'];

            $modelId = $model->id;
            if (!isset($translationsByModel[$modelId])) {
                $translationsByModel[$modelId] = [
                    'model' => $model,
                    'translations' => []
                ];
            }

            $translationsByModel[$modelId]['translations'][$attribute] = $translatedText;
        }

        // Save all translations
        $count = 0;
        foreach ($translationsByModel as $data) {
            try {
                $this->saveTranslatedModel($data['model'], $data['translations'], $targetLocale);
                $count++;
                \Log::info("Saved translations for model {$modelClass} ID {$data['model']->id}");
            } catch (\Exception $e) {
                \Log::error("Failed to save translations for model ID {$data['model']->id}: " . $e->getMessage());
            }
        }

        \Log::info("Batch translation complete: {$count} models translated");

        return $count;
    }

    /**
     * Translate multiple models in batch (original individual method)
     * Kept for backward compatibility. Use translateModelsInBatch() for better performance.
     */
    public function translateModels(
        string $modelClass,
        string $sourceLocale,
        string $targetLocale,
        array $modelIds = [],
        array $options = []
    ): int
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
     */
    public function getTranslatableAttributes(Model $model): array
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
     * Validate that model implements TranslatableModel behavior
     */
    protected function validateTranslatableModel(Model $model): void
    {
        if (!$model->isClassExtendedWith(TranslatableModel::class)) {
            throw new \Exception('Model must implement TranslatableModel behavior');
        }
    }

    /**
     * Prepare attributes list for translation
     */
    protected function prepareAttributesForTranslation(Model $model, array $options): array
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
     */
    protected function translateAttributes(
        Model $model,
        array $attributes,
        string $sourceLocale,
        string $targetLocale,
        string $normalizedSource,
        string $normalizedTarget,
        bool $overwrite,
        array $options
    ): array
    {
        $translated = [];
        // Use original locale code for model context
        $model->translateContext($sourceLocale);

        foreach ($attributes as $attribute) {
            $result = $this->translateAttribute(
                $model,
                $attribute,
                $sourceLocale,
                $targetLocale,
                $normalizedSource,
                $normalizedTarget,
                $overwrite,
                $options
            );

            if ($result !== null) {
                $translated[$attribute] = $result;
            }
        }

        return $translated;
    }

    /**
     * Translate a single attribute
     */
    protected function translateAttribute(
        Model $model,
        string $attribute,
        string $sourceLocale,
        string $targetLocale,
        string $normalizedSource,
        string $normalizedTarget,
        bool $overwrite,
        array $options
    ): ?string
    {
        $sourceValue = $model->getAttribute($attribute);

        if ($this->isEmptyValue($sourceValue)) {
            $this->logEmptyAttribute($model, $attribute, $sourceLocale);
            return null;
        }

        if ($this->shouldSkipTranslation($model, $attribute, $targetLocale, $overwrite)) {
            return null;
        }

        try {
            // Use normalized codes for translation provider
            $translatedValue = $this->provider->translateText($sourceValue, $normalizedSource, $normalizedTarget, $options);
            $this->logSuccessfulTranslation($model, $attribute, $sourceLocale, $targetLocale);
            return $translatedValue;
        } catch (\Exception $e) {
            $this->logTranslationFailure($model, $attribute, $e);
            return null;
        }
    }

    /**
     * Save translated model
     */
    protected function saveTranslatedModel(Model $model, array $translated, string $targetLocale): void
    {
        // Store original context to restore later
        $originalContext = $model->translateContext();

        // Use the proper RainLab.Translate API to set translated attributes
        foreach ($translated as $attribute => $value) {
            $model->setAttributeTranslated($attribute, $value, $targetLocale);
        }

        // Save triggers syncTranslatableAttributes()
        $model->save();

        // Restore original context
        $model->translateContext($originalContext);
    }

    /**
     * Check if translation should be skipped
     */
    protected function shouldSkipTranslation(Model $model, string $attribute, string $targetLocale, bool $overwrite): bool
    {
        $hasTranslation = $this->modelHasTranslation($model, $attribute, $targetLocale);

        if ($hasTranslation && !$overwrite) {
            $this->logSkippedTranslation($model, $attribute);
            return true;
        }

        return false;
    }

    /**
     * Check if model has translation for attribute in target locale
     */
    protected function modelHasTranslation(Model $model, string $attribute, string $targetLocale): bool
    {
        // Get the default locale
        $defaultLocale = $this->getDefaultLocale();

        // If checking the default locale, translations always "exist" (it's the source)
        if ($targetLocale === $defaultLocale) {
            return !$this->isEmptyValue($model->getAttribute($attribute));
        }

        // Store the current fallback state and context
        $originalContext = $model->translateContext();
        $wasUsingFallback = property_exists($model, 'translatableUseFallback')
            ? $model->translatableUseFallback
            : true;

        // Disable fallback temporarily
        $model->noFallbackLocale()->translateContext($targetLocale);
        $translatedValue = $model->getAttribute($attribute);

        // Restore original state
        if ($wasUsingFallback) {
            $model->withFallbackLocale();
        }
        $model->translateContext($originalContext);

        return !$this->isEmptyValue($translatedValue);
    }

    /**
     * Check if value is empty
     */
    protected function isEmptyValue(mixed $value): bool
    {
        return empty($value) || trim($value) === '';
    }

    /**
     * Get default locale
     */
    protected function getDefaultLocale(): string
    {
        $locale = Locale::getDefault();
        return $locale ? $locale->code : 'en';
    }

    /**
     * Log empty attribute
     */
    protected function logEmptyAttribute(Model $model, string $attribute, string $sourceLocale): void
    {
        \Log::debug("Model {$model->id} attribute '{$attribute}' empty in locale '{$sourceLocale}'");
    }

    /**
     * Log skipped translation
     */
    protected function logSkippedTranslation(Model $model, string $attribute): void
    {
        \Log::debug("Model {$model->id} attribute '{$attribute}' already translated, skipping");
    }

    /**
     * Log successful translation
     */
    protected function logSuccessfulTranslation(Model $model, string $attribute, string $sourceLocale, string $targetLocale): void
    {
        \Log::info("Translated model {$model->id} attribute '{$attribute}' from {$sourceLocale} to {$targetLocale}");
    }

    /**
     * Log translation failure
     */
    protected function logTranslationFailure(Model $model, string $attribute, \Exception $e): void
    {
        \Log::error("Failed to translate attribute '{$attribute}' for model {$model->id}: " . $e->getMessage());
    }
}