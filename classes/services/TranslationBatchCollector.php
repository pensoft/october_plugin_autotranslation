<?php namespace Pensoft\AutoTranslation\Classes\Services;

/**
 * Translation Batch Collector
 *
 * Collects translatable items and maps batch results back to original items.
 * Single Responsibility: Data collection, filtering, and result mapping.
 */
class TranslationBatchCollector
{
    /**
     * Collect translatable texts and create mapping
     *
     * @param array $items Items to collect from
     * @param callable $textExtractor Function to extract text from item
     * @param callable $shouldTranslate Function to check if item should be translated
     * @return array ['texts' => [...], 'map' => [...]]
     */
    public function collect(array $items, callable $textExtractor, callable $shouldTranslate)
    {
        $texts = [];
        $map = [];

        foreach ($items as $index => $item) {
            if (!$shouldTranslate($item)) {
                continue;
            }

            $text = $textExtractor($item);

            if ($this->isEmptyText($text)) {
                continue;
            }

            $texts[] = $text;
            $map[] = [
                'index' => $index,
                'item' => $item
            ];
        }

        return [
            'texts' => $texts,
            'map' => $map
        ];
    }

    /**
     * Map batch translation results back to original items
     *
     * @param array $results Translation results from provider
     * @param array $map Mapping from collect()
     * @return array Mapped results [item => translatedText]
     */
    public function mapResults(array $results, array $map)
    {
        $mapped = [];

        foreach ($results as $idx => $result) {
            if (!isset($map[$idx])) {
                continue;
            }

            $mapping = $map[$idx];
            $translatedText = is_object($result) ? $result->text : $result;

            $mapped[] = [
                'item' => $mapping['item'],
                'translated' => $translatedText
            ];
        }

        return $mapped;
    }

    /**
     * Collect texts from messages for batch translation
     *
     * @param \Illuminate\Database\Eloquent\Collection $messages Messages to collect from
     * @param string $sourceLocale Source locale code
     * @param string $targetLocale Target locale code
     * @param bool $overwrite Whether to overwrite existing translations
     * @return array ['texts' => [...], 'map' => [...], 'stats' => [...]]
     */
    public function collectFromMessages($messages, $sourceLocale, $targetLocale, $overwrite = false)
    {
        $texts = [];
        $map = [];
        $stats = ['skipped_empty' => 0, 'skipped_existing' => 0];

        foreach ($messages as $message) {
            $sourceText = $message->forLocale($sourceLocale);

            if ($this->isEmptyText($sourceText)) {
                $sourceText = $this->getFallbackMessageText($message, $sourceLocale);
            }

            if ($this->isEmptyText($sourceText)) {
                $stats['skipped_empty']++;
                continue;
            }

            $hasTranslation = $this->messageHasTranslation($message, $targetLocale);
            if ($hasTranslation && !$overwrite) {
                $stats['skipped_existing']++;
                continue;
            }

            $texts[] = $sourceText;
            $map[] = [
                'item' => $message,
                'source_text' => $sourceText
            ];
        }

        return [
            'texts' => $texts,
            'map' => $map,
            'stats' => $stats
        ];
    }

    /**
     * Collect texts from model attributes for batch translation
     *
     * @param \Illuminate\Database\Eloquent\Collection $models Models to collect from
     * @param array $attributes Attribute names to translate
     * @param string $sourceLocale Source locale code
     * @param string $targetLocale Target locale code
     * @param bool $overwrite Whether to overwrite existing translations
     * @return array ['texts' => [...], 'map' => [...], 'stats' => [...]]
     */
    public function collectFromModels($models, array $attributes, $sourceLocale, $targetLocale, $overwrite = false)
    {
        $texts = [];
        $map = [];
        $stats = ['skipped_empty' => 0, 'skipped_existing' => 0];

        foreach ($models as $model) {
            $model->translateContext($sourceLocale);

            foreach ($attributes as $attribute) {
                $sourceValue = $model->getAttribute($attribute);

                if ($this->isEmptyText($sourceValue)) {
                    $stats['skipped_empty']++;
                    continue;
                }

                if ($this->modelHasTranslation($model, $attribute, $targetLocale) && !$overwrite) {
                    $stats['skipped_existing']++;
                    continue;
                }

                $texts[] = $sourceValue;
                $map[] = [
                    'item' => [
                        'model' => $model,
                        'attribute' => $attribute
                    ],
                    'source_value' => $sourceValue
                ];
            }
        }

        return [
            'texts' => $texts,
            'map' => $map,
            'stats' => $stats
        ];
    }

    /**
     * Check if text is empty
     *
     * @param mixed $text
     * @return bool
     */
    protected function isEmptyText($text)
    {
        return empty($text) || trim($text) === '';
    }

    /**
     * Get fallback message text from any available locale
     *
     * @param \RainLab\Translate\Models\Message $message
     * @param string $sourceLocale
     * @return string|null
     */
    protected function getFallbackMessageText($message, $sourceLocale)
    {
        $messageData = $message->message_data ?? [];

        if (empty($messageData) || !is_array($messageData)) {
            return null;
        }

        $sourceText = reset($messageData);
        $actualSourceLocale = key($messageData);

        if ($actualSourceLocale !== $sourceLocale) {
            \Log::debug("Message ID {$message->id}: Using fallback locale '{$actualSourceLocale}' instead of '{$sourceLocale}'");
        }

        return $sourceText;
    }

    /**
     * Check if message has translation
     *
     * @param \RainLab\Translate\Models\Message $message
     * @param string $targetLocale
     * @return bool
     */
    protected function messageHasTranslation($message, $targetLocale)
    {
        $messageData = $message->message_data ?? [];

        if (!is_array($messageData)) {
            return false;
        }

        return isset($messageData[$targetLocale]) && !empty($messageData[$targetLocale]);
    }

    /**
     * Check if model has translation for attribute
     *
     * @param \October\Rain\Database\Model $model
     * @param string $attribute
     * @param string $targetLocale
     * @return bool
     */
    protected function modelHasTranslation($model, $attribute, $targetLocale)
    {
        $originalContext = $model->translateContext();
        $wasUsingFallback = property_exists($model, 'translatableUseFallback')
            ? $model->translatableUseFallback
            : true;

        $model->noFallbackLocale()->translateContext($targetLocale);
        $translatedValue = $model->getAttribute($attribute);

        if ($wasUsingFallback) {
            $model->withFallbackLocale();
        }
        $model->translateContext($originalContext);

        return !$this->isEmptyText($translatedValue);
    }
}
