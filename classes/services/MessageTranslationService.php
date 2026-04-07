<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Models\Message;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\Strategies\DeepLBatchStrategy;
use Pensoft\AutoTranslation\Classes\Services\TranslationBatchCollector;

/**
 * Message Translation Service
 *
 * Handles translation of RainLab.Translate messages (UI strings, labels, etc.)
 */
class MessageTranslationService
{
    /**
     * @var TranslationProviderInterface
     */
    protected $provider;

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
        ?LocaleNormalizer $normalizer = null,
        ?TranslationBatchCollector $batchCollector = null
    )
    {
        $this->provider = $provider;
        $this->normalizer = $normalizer ?: new LocaleNormalizer();
        $this->batchCollector = $batchCollector ?: new TranslationBatchCollector();
    }

    /**
     * Translate messages using batch processing (recommended for performance)
     */
    public function translateMessagesInBatch(string $sourceLocale, string $targetLocale, array $messageIds = [], bool $overwrite = false, ?int $batchSize = null): int
    {
        // Normalize locale codes for translation provider
        $normalizedTarget = $this->normalizer->normalize($targetLocale);
        $normalizedSource = $this->normalizer->normalize($sourceLocale);

        $this->validateTargetLanguage($normalizedTarget);

        $messages = $this->fetchMessages($messageIds);

        $this->logTranslationStart($messages, $sourceLocale, $targetLocale, $overwrite);

        // Collect translatable texts
        $collection = $this->batchCollector->collectFromMessages($messages, $sourceLocale, $targetLocale, $overwrite);

        $stats = [
            'count' => 0,
            'skipped' => [
                'empty' => $collection['stats']['skipped_empty'],
                'already_translated' => $collection['stats']['skipped_existing']
            ]
        ];

        if (empty($collection['texts'])) {
            $this->logTranslationComplete($stats);
            return $stats['count'];
        }

        // Create batch strategy
        $batchStrategy = new DeepLBatchStrategy($this->provider, $batchSize);
        $batches = $batchStrategy->createBatches($collection['texts']);

        \Log::info("Processing {$messages->count()} messages in " . count($batches) . " batch(es)");

        // Process all batches
        $allResults = [];
        foreach ($batches as $batchIndex => $batch) {
            try {
                \Log::debug("Processing batch " . ($batchIndex + 1) . " of " . count($batches) . " (" . count($batch) . " items)");

                $results = $batchStrategy->processBatch($batch, $normalizedSource, $normalizedTarget);
                $allResults = array_merge($allResults, $results);
            } catch (\Exception $e) {
                \Log::error("Batch translation failed for batch {$batchIndex}: " . $e->getMessage());
                continue;
            }
        }

        // Map results back and save
        $mapped = $this->batchCollector->mapResults($allResults, $collection['map']);

        foreach ($mapped as $result) {
            try {
                $message = $result['item'];
                $translatedText = $result['translated'];

                $this->logMessageTranslationResult($message, $translatedText);

                // Use original code for October CMS storage
                $message->toLocale($targetLocale, $translatedText);
                $stats['count']++;
            } catch (\Exception $e) {
                \Log::error("Failed to save translated message: " . $e->getMessage());
            }
        }

        $this->logTranslationComplete($stats);

        return $stats['count'];
    }

    /**
     * Translate messages from source to target locale (original individual method)
     * Kept for backward compatibility. Use translateMessagesInBatch() for better performance.
     */
    public function translateMessages(string $sourceLocale, string $targetLocale, array $messageIds = [], bool $overwrite = false): int
    {
        // Normalize locale codes for translation provider
        $normalizedTarget = $this->normalizer->normalize($targetLocale);
        $normalizedSource = $this->normalizer->normalize($sourceLocale);

        $this->validateTargetLanguage($normalizedTarget);

        $messages = $this->fetchMessages($messageIds);
        $stats = ['count' => 0, 'skipped' => ['empty' => 0, 'already_translated' => 0]];

        $this->logTranslationStart($messages, $sourceLocale, $targetLocale, $overwrite);

        foreach ($messages as $message) {
            $this->processMessage(
                $message,
                $sourceLocale,      // Original for storage
                $targetLocale,      // Original for storage
                $normalizedSource,  // Normalized for API
                $normalizedTarget,  // Normalized for API
                $overwrite,
                $stats
            );
        }

        $this->logTranslationComplete($stats);

        return $stats['count'];
    }

    /**
     * Validate target language is supported
     */
    protected function validateTargetLanguage(string $targetLocale): void
    {
        $availableLanguages = $this->provider->getTargetLanguages();

        if (!isset($availableLanguages[$targetLocale])) {
            $this->logUnsupportedLanguage($targetLocale, $availableLanguages);
            $this->throwUnsupportedLanguageException($targetLocale, $availableLanguages);
        }
    }

    /**
     * Fetch messages to translate
     */
    protected function fetchMessages(array $messageIds): \Illuminate\Database\Eloquent\Collection
    {
        $query = Message::query();

        if (!empty($messageIds)) {
            $query->whereIn('id', $messageIds);
        }

        return $query->get();
    }

    /**
     * Process a single message
     */
    protected function processMessage(
        Message $message,
        string $sourceLocale,
        string $targetLocale,
        string $normalizedSource,
        string $normalizedTarget,
        bool $overwrite,
        array &$stats
    ): void
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

        $this->translateAndSaveMessage($message, $sourceText, $normalizedSource, $normalizedTarget, $targetLocale, $stats);
    }

    /**
     * Get source text for message
     */
    protected function getMessageSourceText(Message $message, string $sourceLocale): ?string
    {
        $sourceText = $message->forLocale($sourceLocale);

        if ($this->isEmptyValue($sourceText)) {
            return $this->getFallbackSourceText($message, $sourceLocale);
        }

        return $sourceText;
    }

    /**
     * Get fallback source text from any available locale
     */
    protected function getFallbackSourceText(Message $message, string $sourceLocale): ?string
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
     */
    protected function shouldSkipMessage(Message $message, string $targetLocale, bool $overwrite): bool
    {
        $hasTranslation = $this->messageHasTranslation($message, $targetLocale);

        if ($hasTranslation && !$overwrite) {
            $existingTranslation = $message->forLocale($targetLocale);
            $this->logSkippedMessage($message, $existingTranslation);
            return true;
        }

        return false;
    }

    /**
     * Check if message has translation in target locale
     */
    protected function messageHasTranslation(Message $message, string $targetLocale): bool
    {
        $messageData = $message->message_data ?? [];

        if (!is_array($messageData)) {
            return false;
        }

        return isset($messageData[$targetLocale]) && !empty($messageData[$targetLocale]);
    }

    /**
     * Translate and save message
     */
    protected function translateAndSaveMessage(
        Message $message,
        string $sourceText,
        string $normalizedSource,
        string $normalizedTarget,
        string $originalTarget,
        array &$stats
    ): void
    {
        try {
            $this->logMessageTranslationStart($message, $normalizedSource, $normalizedTarget);

            // Use normalized codes for translation provider
            $translatedText = $this->provider->translateText($sourceText, $normalizedSource, $normalizedTarget);

            $this->logMessageTranslationResult($message, $translatedText);

            // Use original code for October CMS storage
            $message->toLocale($originalTarget, $translatedText);
            $stats['count']++;
        } catch (\Exception $e) {
            \Log::error("Failed to translate message ID {$message->id}: " . $e->getMessage());
        }
    }

    /**
     * Check if value is empty
     */
    protected function isEmptyValue(mixed $value): bool
    {
        return empty($value) || trim($value) === '';
    }

    /**
     * Log unsupported language warning
     */
    protected function logUnsupportedLanguage(string $targetLocale, array $availableLanguages): void
    {
        \Log::warning("Target language '{$targetLocale}' is not supported by DeepL", [
            'available_languages' => array_keys($availableLanguages)
        ]);
    }

    /**
     * Throw unsupported language exception
     */
    protected function throwUnsupportedLanguageException(string $targetLocale, array $availableLanguages): void
    {
        $languagesList = implode(', ', array_keys($availableLanguages));
        throw new \Exception("Language '{$targetLocale}' is not supported. Available languages: {$languagesList}");
    }

    /**
     * Log translation start
     */
    protected function logTranslationStart($messages, string $sourceLocale, string $targetLocale, bool $overwrite): void
    {
        \Log::info("Starting translation: {$messages->count()} messages from {$sourceLocale} to {$targetLocale}, overwrite={$overwrite}");
    }

    /**
     * Log empty message
     */
    protected function logEmptyMessage(Message $message): void
    {
        \Log::debug("Message ID {$message->id} - SKIPPED: empty source");
    }

    /**
     * Log skipped message
     */
    protected function logSkippedMessage(Message $message, string $existingTranslation): void
    {
        $preview = substr($existingTranslation, 0, 50);
        \Log::debug("Message ID {$message->id} - SKIPPED: already translated (existing: {$preview})");
    }

    /**
     * Log message translation start
     */
    protected function logMessageTranslationStart(Message $message, string $sourceLocale, string $targetLocale): void
    {
        \Log::info("Message ID {$message->id} - TRANSLATING from {$sourceLocale} to {$targetLocale}");
    }

    /**
     * Log message translation result
     */
    protected function logMessageTranslationResult(Message $message, string $translatedText): void
    {
        $preview = substr($translatedText, 0, 50);
        \Log::info("Message ID {$message->id} - Translation result: {$preview}");
    }

    /**
     * Log translation complete
     */
    protected function logTranslationComplete(array $stats): void
    {
        \Log::info("Translation complete: {$stats['count']} translated, {$stats['skipped']['empty']} empty, {$stats['skipped']['already_translated']} already translated");
    }
}