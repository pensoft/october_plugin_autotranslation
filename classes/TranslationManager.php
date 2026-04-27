<?php namespace Pensoft\AutoTranslation\Classes;

use RainLab\Translate\Classes\Locale;
use RainLab\Translate\Models\Message;
use October\Rain\Database\Model;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\Providers\DeepLProvider;
use Pensoft\AutoTranslation\Classes\Services\ModelTranslationService;
use Pensoft\AutoTranslation\Classes\Services\MessageTranslationService;
use Pensoft\AutoTranslation\Classes\Services\LocaleNormalizer;
use Pensoft\AutoTranslation\Models\Settings;

/**
 * Translation Manager - Orchestrates translation operations
 */
class TranslationManager
{
    /**
     * @var TranslationProviderInterface
     */
    protected $provider;

    /**
     * @var ModelTranslationService
     */
    protected $modelService;

    /**
     * @var MessageTranslationService
     */
    protected $messageService;

    /**
     * @var LocaleNormalizer
     */
    protected $normalizer;

    /**
     * Constructor
     */
    public function __construct(
        ?TranslationProviderInterface $provider = null,
        ?ModelTranslationService $modelService = null,
        ?MessageTranslationService $messageService = null,
        ?LocaleNormalizer $normalizer = null
    )
    {
        // Default to DeepL provider if none specified
        $this->provider = $provider ?: new DeepLProvider();
        $this->normalizer = $normalizer ?: new LocaleNormalizer();

        // Initialize services with dependencies
        $this->modelService = $modelService ?: new ModelTranslationService($this->provider, null, $this->normalizer);
        $this->messageService = $messageService ?: new MessageTranslationService($this->provider, $this->normalizer);
    }

    /**
     * Translate model attributes from source to target locale
     */
    public function translateModel(Model $model, string $sourceLocale, string $targetLocale, array $options = []): array
    {
        return $this->modelService->translateModel($model, $sourceLocale, $targetLocale, $options);
    }

    /**
     * Translate messages from source to target locale
     * Automatically uses batch processing if enabled in settings
     */
    public function translateMessages(string $sourceLocale, string $targetLocale, array $messageIds = [], bool $overwrite = false): int
    {
        $useBatch = $this->isBatchingEnabled();
        $batchSize = $this->getBatchSize();

        if ($useBatch) {
            \Log::info("Using batch translation for messages (batch size: {$batchSize})");
            return $this->messageService->translateMessagesInBatch($sourceLocale, $targetLocale, $messageIds, $overwrite, $batchSize);
        } else {
            \Log::info("Using individual translation for messages (batch mode disabled)");
            return $this->messageService->translateMessages($sourceLocale, $targetLocale, $messageIds, $overwrite);
        }
    }

    /**
     * Translate multiple models in batch
     * Automatically uses batch processing if enabled in settings
     */
    public function translateModels(
        string $modelClass,
        string $sourceLocale,
        string $targetLocale,
        array $modelIds = [],
        array $options = []
    ): int
    {
        $useBatch = $this->isBatchingEnabled();
        $batchSize = $this->getBatchSize();

        // Add batch size to options if not already set
        if ($useBatch && !isset($options['batch_size'])) {
            $options['batch_size'] = $batchSize;
        }

        if ($useBatch) {
            \Log::info("Using batch translation for models (batch size: {$batchSize})");
            return $this->modelService->translateModelsInBatch($modelClass, $sourceLocale, $targetLocale, $modelIds, $options);
        } else {
            \Log::info("Using individual translation for models (batch mode disabled)");
            return $this->modelService->translateModels($modelClass, $sourceLocale, $targetLocale, $modelIds, $options);
        }
    }

    /**
     * Get translatable attributes from model
     */
    public function getTranslatableAttributes(Model $model): array
    {
        return $this->modelService->getTranslatableAttributes($model);
    }

    /**
     * Get enabled locales from Rainlab.Translate
     */
    public function getEnabledLocales(): array
    {
        return Locale::listEnabled();
    }

    /**
     * Get default locale
     */
    public function getDefaultLocale(): string
    {
        $locale = Locale::getDefault();
        return $locale ? $locale->code : 'en';
    }

    /**
     * Check if a locale is enabled
     */
    public function isLocaleEnabled(string $localeCode): bool
    {
        return Locale::isValid($localeCode);
    }

    /**
     * Get translation statistics
     */
    public function getTranslationStats(string $sourceLocale, string $targetLocale): array
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

    /**
     * Get the translation provider instance
     */
    public function getProvider(): TranslationProviderInterface
    {
        return $this->provider;
    }

    /**
     * Set a different translation provider
     * Allows runtime switching between providers (DeepL, Google, AWS, etc.)
     */
    public function setProvider(TranslationProviderInterface $provider): void
    {
        $this->provider = $provider;

        // Reinitialize services with new provider
        $this->modelService = new ModelTranslationService($provider, null, $this->normalizer);
        $this->messageService = new MessageTranslationService($provider, $this->normalizer);
    }

    /**
     * Get the model translation service
     */
    public function getModelService(): ModelTranslationService
    {
        return $this->modelService;
    }

    /**
     * Get the message translation service
     */
    public function getMessageService(): MessageTranslationService
    {
        return $this->messageService;
    }

    /**
     * Get the locale normalizer
     */
    public function getNormalizer(): LocaleNormalizer
    {
        return $this->normalizer;
    }

    /**
     * Check if batch translation is enabled in settings
     */
    protected function isBatchingEnabled(): bool
    {
        return Settings::get('batch_enabled', true);
    }

    /**
     * Get batch size from settings
     */
    protected function getBatchSize(): int
    {
        return (int) Settings::get('batch_size', 50);
    }
}