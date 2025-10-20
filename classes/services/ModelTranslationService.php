<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Models\Locale;
use RainLab\Translate\Behaviors\TranslatableModel;
use October\Rain\Database\Model;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\FieldFilter;

/**
 * Model Translation Service
 *
 * Handles translation of October CMS models that implement TranslatableModel behavior
 * Single Responsibility: Model-specific translation operations
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
     * Constructor
     *
     * @param TranslationProviderInterface $provider
     * @param FieldFilter|null $filter
     * @param LocaleNormalizer|null $normalizer
     */
    public function __construct(
        TranslationProviderInterface $provider,
        ?FieldFilter $filter = null,
        ?LocaleNormalizer $normalizer = null
    )
    {
        $this->provider = $provider;
        $this->filter = $filter ?: new FieldFilter();
        $this->normalizer = $normalizer ?: new LocaleNormalizer();
    }

    /**
     * Translate model attributes from source to target locale
     *
     * @param Model $model
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array $options
     * @return array Array of translated attributes
     * @throws \Exception
     */
    public function translateModel(Model $model, $sourceLocale, $targetLocale, array $options = [])
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
     * Translate multiple models in batch
     *
     * @param string $modelClass
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param array $modelIds
     * @param array $options
     * @return int Number of translated models
     */
    public function translateModels(
        $modelClass,
        $sourceLocale,
        $targetLocale,
        array $modelIds = [],
        array $options = []
    )
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
    public function getTranslatableAttributes(Model $model)
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
     * @param string $sourceLocale Original locale code for storage
     * @param string $targetLocale Original locale code for storage
     * @param string $normalizedSource Normalized locale code for API
     * @param string $normalizedTarget Normalized locale code for API
     * @param bool $overwrite
     * @param array $options
     * @return array
     */
    protected function translateAttributes(
        Model $model,
        array $attributes,
        $sourceLocale,
        $targetLocale,
        $normalizedSource,
        $normalizedTarget,
        $overwrite,
        array $options
    )
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
     *
     * @param Model $model
     * @param string $attribute
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param string $normalizedSource
     * @param string $normalizedTarget
     * @param bool $overwrite
     * @param array $options
     * @return string|null
     */
    protected function translateAttribute(
        Model $model,
        $attribute,
        $sourceLocale,
        $targetLocale,
        $normalizedSource,
        $normalizedTarget,
        $overwrite,
        array $options
    )
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
     *
     * @param Model $model
     * @param array $translated
     * @param string $targetLocale
     * @return void
     */
    protected function saveTranslatedModel(Model $model, array $translated, $targetLocale)
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
     *
     * @param Model $model
     * @param string $attribute
     * @param string $targetLocale
     * @param bool $overwrite
     * @return bool
     */
    protected function shouldSkipTranslation(Model $model, $attribute, $targetLocale, $overwrite)
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
     *
     * @param Model $model
     * @param string $attribute
     * @param string $targetLocale
     * @return bool
     */
    protected function modelHasTranslation(Model $model, $attribute, $targetLocale)
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
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmptyValue($value)
    {
        return empty($value) || trim($value) === '';
    }

    /**
     * Get default locale
     *
     * @return string
     */
    protected function getDefaultLocale()
    {
        $locale = Locale::getDefault();
        return $locale ? $locale->code : 'en';
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
}
