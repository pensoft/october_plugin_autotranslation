<?php namespace Pensoft\AutoTranslation\Classes;

use RainLab\Translate\Models\Locale;
use RainLab\Translate\Models\Message;
use October\Rain\Database\Model;
use Pensoft\AutoTranslation\Classes\Contracts\TranslationProviderInterface;
use Pensoft\AutoTranslation\Classes\Providers\DeepLProvider;
use Pensoft\AutoTranslation\Classes\Services\ModelTranslationService;
use Pensoft\AutoTranslation\Classes\Services\MessageTranslationService;
use Pensoft\AutoTranslation\Classes\Services\LocaleNormalizer;

/**
 * Translation Manager - Orchestrates translation operations
 *
 * Refactored to follow SOLID principles:
 * - Single Responsibility: Orchestration only, delegates to specialized services
 * - Open/Closed: Extensible via TranslationProviderInterface
 * - Dependency Inversion: Depends on abstractions (interfaces) not concrete classes
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
     *
     * @param TranslationProviderInterface|null $provider
     * @param ModelTranslationService|null $modelService
     * @param MessageTranslationService|null $messageService
     * @param LocaleNormalizer|null $normalizer
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
        return $this->modelService->translateModel($model, $sourceLocale, $targetLocale, $options);
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
        return $this->messageService->translateMessages($sourceLocale, $targetLocale, $messageIds, $overwrite);
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
        return $this->modelService->translateModels($modelClass, $sourceLocale, $targetLocale, $modelIds, $options);
    }

    /**
     * Get translatable attributes from model
     *
     * @param Model $model
     * @return array
     */
    public function getTranslatableAttributes(Model $model)
    {
        return $this->modelService->getTranslatableAttributes($model);
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

    /**
     * Get the translation provider instance
     *
     * @return TranslationProviderInterface
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set a different translation provider
     * Allows runtime switching between providers (DeepL, Google, AWS, etc.)
     *
     * @param TranslationProviderInterface $provider
     * @return void
     */
    public function setProvider(TranslationProviderInterface $provider)
    {
        $this->provider = $provider;

        // Reinitialize services with new provider
        $this->modelService = new ModelTranslationService($provider, null, $this->normalizer);
        $this->messageService = new MessageTranslationService($provider, $this->normalizer);
    }

    /**
     * Get the model translation service
     *
     * @return ModelTranslationService
     */
    public function getModelService()
    {
        return $this->modelService;
    }

    /**
     * Get the message translation service
     *
     * @return MessageTranslationService
     */
    public function getMessageService()
    {
        return $this->messageService;
    }

    /**
     * Get the locale normalizer
     *
     * @return LocaleNormalizer
     */
    public function getNormalizer()
    {
        return $this->normalizer;
    }
}

