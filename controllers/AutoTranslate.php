<?php namespace Pensoft\AutoTranslation\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Pensoft\AutoTranslation\Classes\TranslationManager;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;
use Pensoft\AutoTranslation\Classes\Services\ModelDiscoveryService;
use RainLab\Translate\Models\Locale;
use RainLab\Translate\Models\Message;
use Flash;

/**
 * Auto Translate Backend Controller
 *
 * Simplified to act as thin layer between HTTP and business logic
 * Delegates to specialized services for actual work
 */
class AutoTranslate extends Controller
{
    /**
     * @var array Required permissions
     */
    public $requiredPermissions = ['pensoft.autotranslation.access'];

    /**
     * @var TranslationManager
     */
    protected $translationManager;

    /**
     * @var ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Default context - will be overridden by individual page methods
        BackendMenu::setContext('Pensoft.AutoTranslation', 'autotranslation');

        // Register shared CSS (used across all pages)
        $this->addCss('/plugins/pensoft/autotranslation/assets/css/autotranslation.css', 'Pensoft.AutoTranslation');

        // Note: Page-specific JS is loaded in individual action methods to prevent conflicts
    }

    /**
     * Get or create translation manager instance
     *
     * @return TranslationManager
     */
    protected function getTranslationManager()
    {
        if (!$this->translationManager) {
            $this->translationManager = new TranslationManager();
        }

        return $this->translationManager;
    }

    /**
     * Get or create model discovery service instance
     *
     * @return ModelDiscoveryService
     */
    protected function getModelDiscovery()
    {
        if (!$this->modelDiscovery) {
            $this->modelDiscovery = new ModelDiscoveryService();
        }

        return $this->modelDiscovery;
    }

    /**
     * Get configured source locale from settings
     *
     * @return Locale
     */
    protected function getConfiguredSourceLocale()
    {
        $settings = \Pensoft\AutoTranslation\Models\Settings::instance();
        $sourceLocaleCode = $settings->get('default_source_locale');

        if ($sourceLocaleCode) {
            $locale = Locale::where('code', $sourceLocaleCode)->first();
            if ($locale) {
                return $locale;
            }
        }

        // Fall back to system default locale
        return Locale::getDefault();
    }
    
    /**
     * Messages translation page
     */
    public function messages()
    {
        $this->pageTitle = 'Translate Messages';
        BackendMenu::setContext('Pensoft.AutoTranslation', 'autotranslation', 'messages');

        // Register page-specific JavaScript
        $this->addJs('/plugins/pensoft/autotranslation/assets/js/messages.js', 'Pensoft.AutoTranslation');

        $this->vars['locales'] = Locale::isEnabled()->get();
        $this->vars['defaultLocale'] = Locale::getDefault();
        $this->vars['sourceLocale'] = $this->getConfiguredSourceLocale();
        $this->vars['messages'] = Message::paginate(50);
        $this->vars['totalMessages'] = Message::count();
    }
    
    /**
     * Models translation page
     */
    public function models()
    {
        $this->pageTitle = 'Translate Models';
        BackendMenu::setContext('Pensoft.AutoTranslation', 'autotranslation', 'models');

        // Register page-specific JavaScript
        $this->addJs('/plugins/pensoft/autotranslation/assets/js/models.js', 'Pensoft.AutoTranslation');

        $this->vars['locales'] = Locale::isEnabled()->get();
        $this->vars['defaultLocale'] = Locale::getDefault();
        $this->vars['sourceLocale'] = $this->getConfiguredSourceLocale();

        // Get translatable models from the system using ModelDiscoveryService
        $this->vars['translatableModels'] = $this->getModelDiscovery()->getTranslatableModels();
    }
    
    /**
     * AJAX handler: Translate messages
     */
    public function onTranslateMessages()
    {
        $data = $this->getMessageTranslationData();

        if (!$this->validateMessageTranslationData($data)) {
            return;
        }

        try {
            $totalTranslated = $this->performMessagesTranslation($data);
            $this->showTranslationResult($totalTranslated);
        } catch (\Exception $e) {
            $this->handleTranslationError('Message translation error', $e);
        }
    }

    /**
     * Get message translation data from post
     *
     * @return array
     */
    protected function getMessageTranslationData()
    {
        $sourceLocale = $this->getConfiguredSourceLocale();

        return [
            'sourceLocale' => $sourceLocale->code,
            'targetLocales' => post('target_locales', []),
            'messageIds' => post('message_ids', []),
            'overwrite' => (bool) post('overwrite', false)
        ];
    }

    /**
     * Validate message translation data
     *
     * @param array $data
     * @return bool
     */
    protected function validateMessageTranslationData(array $data)
    {
        if (empty($data['targetLocales'])) {
            Flash::error('Please select at least one target language');
            return false;
        }

        return true;
    }

    /**
     * Perform messages translation
     *
     * @param array $data
     * @return int
     */
    protected function performMessagesTranslation(array $data)
    {
        $manager = $this->getTranslationManager();
        $totalTranslated = 0;

        foreach ($data['targetLocales'] as $targetLocale) {
            if ($targetLocale === $data['sourceLocale']) {
                continue;
            }

            $count = $manager->translateMessages(
                $data['sourceLocale'],
                $targetLocale,
                $data['messageIds'],
                $data['overwrite']
            );

            $totalTranslated += $count;
        }

        return $totalTranslated;
    }

    /**
     * Show translation result flash message
     *
     * @param int $totalTranslated
     * @return void
     */
    protected function showTranslationResult($totalTranslated)
    {
        if ($totalTranslated > 0) {
            Flash::success("Successfully translated {$totalTranslated} messages");
        } else {
            Flash::warning('No messages were translated. They may already be translated or empty.');
        }
    }

    /**
     * Handle translation error
     *
     * @param string $context
     * @param \Exception $e
     * @return void
     */
    protected function handleTranslationError($context, \Exception $e)
    {
        Flash::error('Translation failed: ' . $e->getMessage());
        \Log::error("{$context}: " . $e->getMessage());
    }
    
    /**
     * AJAX handler: Translate models
     */
    public function onTranslateModels()
    {
        $data = $this->getModelTranslationData();

        if (!$this->validateModelTranslationData($data)) {
            return;
        }

        try {
            $translatedCount = $this->performModelsTranslation($data);
            $this->showModelTranslationResult($translatedCount);
        } catch (\Exception $e) {
            $this->handleTranslationError('Model translation error', $e);
        }
    }

    /**
     * Get model translation data from post
     *
     * @return array
     */
    protected function getModelTranslationData()
    {
        $sourceLocale = $this->getConfiguredSourceLocale();

        return [
            'modelClass' => post('model_class'),
            'sourceLocale' => $sourceLocale->code,
            'targetLocales' => post('target_locales', []),
            'modelIds' => post('model_ids', []),
            'overwrite' => (bool) post('overwrite', false)
        ];
    }

    /**
     * Validate model translation data
     *
     * @param array $data
     * @return bool
     */
    protected function validateModelTranslationData(array $data)
    {
        if (empty($data['modelClass'])) {
            Flash::error('Please select a model class');
            return false;
        }

        if (!class_exists($data['modelClass'])) {
            Flash::error('Invalid model class');
            return false;
        }

        if (empty($data['targetLocales'])) {
            Flash::error('Please select at least one target language');
            return false;
        }

        return true;
    }

    /**
     * Perform models translation
     *
     * @param array $data
     * @return int
     */
    protected function performModelsTranslation(array $data)
    {
        $manager = $this->getTranslationManager();
        $translatedCount = 0;

        foreach ($data['targetLocales'] as $targetLocale) {
            if ($targetLocale === $data['sourceLocale']) {
                continue;
            }

            $count = $manager->translateModels(
                $data['modelClass'],
                $data['sourceLocale'],
                $targetLocale,
                $data['modelIds'],
                ['overwrite' => $data['overwrite']]
            );

            $translatedCount += $count;
        }

        return $translatedCount;
    }

    /**
     * Show model translation result flash message
     *
     * @param int $translatedCount
     * @return void
     */
    protected function showModelTranslationResult($translatedCount)
    {
        if ($translatedCount > 0) {
            Flash::success("Successfully translated {$translatedCount} model records");
        } else {
            Flash::warning('No models were translated');
        }
    }
    
    /**
     * AJAX handler: Check DeepL API usage
     */
    public function onCheckUsage()
    {
        try {
            $translator = new DeepLTranslator();
            $usage = $translator->getUsage();

            $this->logAvailableLanguages($translator);

            return $this->makeUsagePartial($usage);
        } catch (\Exception $e) {
            Flash::error('Failed to check usage: ' . $e->getMessage());
            return $this->makeUsageErrorPartial($e);
        }
    }

    /**
     * Log available languages for debugging
     *
     * @param DeepLTranslator $translator
     * @return void
     */
    protected function logAvailableLanguages(DeepLTranslator $translator)
    {
        $targetLanguages = $translator->getTargetLanguages();
        \Log::info('DeepL available target languages:', $targetLanguages);
    }

    /**
     * Make usage stats partial
     *
     * @param \DeepL\Usage $usage
     * @return array
     */
    protected function makeUsagePartial($usage)
    {
        return [
            '#usage-info-container' => $this->makePartial('usage_stats', ['usage' => $usage])
        ];
    }

    /**
     * Make usage error partial
     *
     * @param \Exception $e
     * @return array
     */
    protected function makeUsageErrorPartial(\Exception $e)
    {
        return [
            '#usage-info-container' => '<div class="callout callout-danger">
                <i class="icon-warning"></i> Error: ' . e($e->getMessage()) . '
            </div>'
        ];
    }

    /**
     * AJAX handler: Test API connection
     */
    public function onTestConnection()
    {
        try {
            $translator = new DeepLTranslator();

            if ($translator->testConnection()) {
                Flash::success('Connection successful! DeepL API is working correctly.');
            } else {
                Flash::error('Connection failed. Please check your API key and server type.');
            }
        } catch (\Exception $e) {
            Flash::error('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler: Get translation statistics
     */
    public function onGetStats()
    {
        $sourceLocale = post('source_locale');
        $targetLocale = post('target_locale');

        if (empty($sourceLocale) || empty($targetLocale)) {
            return ['error' => 'Please select both source and target languages'];
        }

        try {
            $manager = $this->getTranslationManager();
            $stats = $manager->getTranslationStats($sourceLocale, $targetLocale);

            return $this->makeStatsResponse($stats, $sourceLocale, $targetLocale);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Make stats response
     *
     * @param array $stats
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return array
     */
    protected function makeStatsResponse(array $stats, $sourceLocale, $targetLocale)
    {
        return [
            'stats' => $stats,
            'html' => $this->makePartial('stats', [
                'stats' => $stats,
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale
            ])
        ];
    }
    
    /**
     * AJAX handler: Get model fields for selection
     */
    public function onGetModelFields()
    {
        $modelClass = post('model_class');

        if (empty($modelClass) || !class_exists($modelClass)) {
            return ['error' => 'Invalid model class'];
        }

        try {
            $instance = new $modelClass();
            $fields = $this->getModelDiscovery()->getModelTranslatableFields($instance);

            return [
                'fields' => $fields,
                'recordCount' => $modelClass::count()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

