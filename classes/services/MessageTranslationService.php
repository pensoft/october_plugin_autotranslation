<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Models\Message;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;

/**
 * Message Translation Service
 *
 * Handles translation of RainLab.Translate messages (UI strings, labels, etc.)
 * Single Responsibility: Message-specific translation operations
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
     * Constructor
     *
     * @param TranslationProviderInterface $provider
     * @param LocaleNormalizer|null $normalizer
     */
    public function __construct(
        TranslationProviderInterface $provider,
        ?LocaleNormalizer $normalizer = null
    )
    {
        $this->provider = $provider;
        $this->normalizer = $normalizer ?: new LocaleNormalizer();
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
     *
     * @param string $targetLocale
     * @return void
     * @throws \Exception
     */
    protected function validateTargetLanguage($targetLocale)
    {
        $availableLanguages = $this->provider->getTargetLanguages();

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
     * @param string $normalizedSource
     * @param string $normalizedTarget
     * @param bool $overwrite
     * @param array &$stats
     * @return void
     */
    protected function processMessage(
        Message $message,
        $sourceLocale,
        $targetLocale,
        $normalizedSource,
        $normalizedTarget,
        $overwrite,
        array &$stats
    )
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
     *
     * @param Message $message
     * @param string $targetLocale
     * @return bool
     */
    protected function messageHasTranslation(Message $message, $targetLocale)
    {
        $messageData = $message->message_data ?? [];

        if (!is_array($messageData)) {
            return false;
        }

        return isset($messageData[$targetLocale]) && !empty($messageData[$targetLocale]);
    }

    /**
     * Translate and save message
     *
     * @param Message $message
     * @param string $sourceText
     * @param string $normalizedSource
     * @param string $normalizedTarget
     * @param string $originalTarget
     * @param array &$stats
     * @return void
     */
    protected function translateAndSaveMessage(
        Message $message,
        $sourceText,
        $normalizedSource,
        $normalizedTarget,
        $originalTarget,
        array &$stats
    )
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
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmptyValue($value)
    {
        return empty($value) || trim($value) === '';
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
        throw new \Exception("Language '{$targetLocale}' is not supported. Available languages: {$languagesList}");
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
}
