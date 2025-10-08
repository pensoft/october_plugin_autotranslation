<?php namespace Pensoft\AutoTranslation\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Pensoft\AutoTranslation\Classes\TranslationManager;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;
use Pensoft\AutoTranslation\Models\Settings;
use RainLab\Translate\Models\Locale;
use RainLab\Translate\Models\Message;
use Flash;
use Lang;

/**
 * Auto Translate Backend Controller
 */
class AutoTranslate extends Controller
{
    /**
     * @var array Required permissions
     */
    public $requiredPermissions = ['pensoft.autotranslation.access'];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        BackendMenu::setContext('Pensoft.AutoTranslation', 'autotranslation', 'messages');
    }
    
    /**
     * Messages translation page
     */
    public function messages()
    {
        $this->pageTitle = 'Translate Messages';
        
        $this->vars['locales'] = Locale::isEnabled()->get();
        $this->vars['defaultLocale'] = Locale::getDefault();
        $this->vars['messages'] = Message::paginate(50);
        $this->vars['totalMessages'] = Message::count();
    }
    
    /**
     * Models translation page
     */
    public function models()
    {
        $this->pageTitle = 'Translate Models';
        
        $this->vars['locales'] = Locale::isEnabled()->get();
        $this->vars['defaultLocale'] = Locale::getDefault();
        
        // Get translatable models from the system
        $this->vars['translatableModels'] = $this->getTranslatableModels();
    }
    
    /**
     * AJAX handler: Translate messages
     */
    public function onTranslateMessages()
    {
        $sourceLocale = post('source_locale');
        $targetLocales = post('target_locales', []);
        $messageIds = post('message_ids', []);
        $overwrite = (bool) post('overwrite', false);
        
        // Validation
        if (empty($sourceLocale)) {
            Flash::error('Please select a source language');
            return;
        }
        
        if (empty($targetLocales)) {
            Flash::error('Please select at least one target language');
            return;
        }
        
        try {
            $manager = new TranslationManager();
            $totalTranslated = 0;
            
            foreach ($targetLocales as $targetLocale) {
                if ($targetLocale === $sourceLocale) {
                    continue;
                }
                
                $count = $manager->translateMessages(
                    $sourceLocale,
                    $targetLocale,
                    $messageIds,
                    $overwrite
                );
                
                $totalTranslated += $count;
            }
            
            if ($totalTranslated > 0) {
                Flash::success("Successfully translated {$totalTranslated} messages");
            } else {
                Flash::warning('No messages were translated. They may already be translated or empty.');
            }
        } catch (\Exception $e) {
            Flash::error('Translation failed: ' . $e->getMessage());
            \Log::error('Message translation error: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler: Translate models
     */
    public function onTranslateModels()
    {
        $modelClass = post('model_class');
        $sourceLocale = post('source_locale');
        $targetLocales = post('target_locales', []);
        $modelIds = post('model_ids', []);
        $selectedFields = post('selected_fields', []); // New: selected fields to translate
        $overwrite = (bool) post('overwrite', false);

        // Validation
        if (empty($modelClass)) {
            Flash::error('Please select a model class');
            return;
        }

        if (!class_exists($modelClass)) {
            Flash::error('Invalid model class');
            return;
        }

        if (empty($sourceLocale)) {
            Flash::error('Please select a source language');
            return;
        }

        if (empty($targetLocales)) {
            Flash::error('Please select at least one target language');
            return;
        }

        if (empty($selectedFields)) {
            Flash::error('Please select at least one field to translate');
            return;
        }

        try {
            $manager = new TranslationManager();
            $translatedCount = 0;

            foreach ($targetLocales as $targetLocale) {
                if ($targetLocale === $sourceLocale) {
                    continue;
                }

                $count = $manager->translateModels(
                    $modelClass,
                    $sourceLocale,
                    $targetLocale,
                    $modelIds,
                    [
                        'fields' => $selectedFields,
                        'overwrite' => $overwrite
                    ]
                );

                $translatedCount += $count;
            }

            if ($translatedCount > 0) {
                Flash::success("Successfully translated {$translatedCount} model records");
            } else {
                Flash::warning('No models were translated');
            }
        } catch (\Exception $e) {
            Flash::error('Translation failed: ' . $e->getMessage());
            \Log::error('Model translation error: ' . $e->getMessage());
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

            // Also get available languages for debugging
            $targetLanguages = $translator->getTargetLanguages();
            \Log::info('DeepL available target languages:', $targetLanguages);

            $this->vars['usage'] = $usage;

            return [
                '#usage-info-container' => $this->makePartial('usage_stats', [
                    'usage' => $usage
                ])
            ];
        } catch (\Exception $e) {
            Flash::error('Failed to check usage: ' . $e->getMessage());

            return [
                '#usage-info-container' => '<div class="callout callout-danger">
                    <i class="icon-warning"></i> Error: ' . e($e->getMessage()) . '
                </div>'
            ];
        }
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
            $manager = new TranslationManager();
            $stats = $manager->getTranslationStats($sourceLocale, $targetLocale);
            
            return [
                'stats' => $stats,
                'html' => $this->makePartial('stats', [
                    'stats' => $stats,
                    'sourceLocale' => $sourceLocale,
                    'targetLocale' => $targetLocale
                ])
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get list of translatable models with detailed information
     *
     * @return array
     */
    protected function getTranslatableModels()
    {
        $models = [];

        // Use October CMS PluginManager to discover all plugins
        $pluginManager = \System\Classes\PluginManager::instance();
        $plugins = $pluginManager->getPlugins();

        foreach ($plugins as $pluginCode => $pluginObj) {
            // Get plugin details
            [$author, $plugin] = explode('.', $pluginCode);

            // Build the models directory path
            $modelsPath = plugins_path(strtolower($author) . '/' . strtolower($plugin) . '/models');

            if (!is_dir($modelsPath)) {
                continue;
            }

            // Scan for model files
            $modelFiles = glob($modelsPath . '/*.php');

            foreach ($modelFiles as $modelFile) {
                $modelName = basename($modelFile, '.php');

                // Skip lowercase files (settings, imports, exports)
                if (ctype_lower($modelName[0])) {
                    continue;
                }

                // Build the full class name
                $className = ucfirst($author) . '\\' . ucfirst($plugin) . '\\Models\\' . $modelName;

                if (!class_exists($className)) {
                    continue;
                }

                try {
                    $instance = new $className();

                    // Check if model uses TranslatableModel behavior
                    if (!$instance->isClassExtendedWith(\RainLab\Translate\Behaviors\TranslatableModel::class)) {
                        continue;
                    }

                    // Get translatable fields with metadata
                    $fields = $this->getModelTranslatableFields($instance);

                    if (empty($fields)) {
                        continue;
                    }

                    // Get record count
                    $recordCount = $className::count();

                    // Create readable label
                    $label = ucfirst($author) . ' › ' . $this->makeLabel($plugin) . ' › ' . $this->makeLabel($modelName);

                    $models[$className] = [
                        'label' => $label,
                        'plugin' => $pluginCode,
                        'author' => $author,
                        'pluginName' => $plugin,
                        'modelName' => $modelName,
                        'fields' => $fields,
                        'recordCount' => $recordCount,
                        'tableName' => $instance->getTable()
                    ];

                } catch (\Exception $e) {
                    \Log::debug("Could not process model {$className}: " . $e->getMessage());
                    continue;
                }
            }
        }

        // Sort by label
        uasort($models, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $models;
    }

    /**
     * Get translatable fields from a model with their metadata
     *
     * @param \October\Rain\Database\Model $model
     * @return array
     */
    protected function getModelTranslatableFields($model)
    {
        if (!isset($model->translatable) || !is_array($model->translatable)) {
            return [];
        }

        $fields = [];

        foreach ($model->translatable as $key => $value) {
            // Handle both array formats: ['field'] and ['field' => 'index']
            $fieldName = is_numeric($key) ? $value : $key;

            // Determine field type based on naming conventions
            $fieldType = $this->guessFieldType($fieldName);

            $fields[$fieldName] = [
                'name' => $fieldName,
                'label' => $this->makeLabel($fieldName),
                'type' => $fieldType,
                'recommended' => $this->shouldFieldBeTranslated($fieldName, $fieldType)
            ];
        }

        return $fields;
    }

    /**
     * Guess the field type based on field name
     *
     * @param string $fieldName
     * @return string
     */
    protected function guessFieldType($fieldName)
    {
        $richTextFields = ['content', 'description', 'body', 'text', 'excerpt', 'summary'];
        $slugFields = ['slug', 'code', 'url'];
        $metaFields = ['keywords', 'meta_title', 'meta_description', 'seo_title', 'seo_description'];

        if (in_array(strtolower($fieldName), $richTextFields)) {
            return 'richeditor';
        }

        if (in_array(strtolower($fieldName), $slugFields)) {
            return 'slug';
        }

        if (in_array(strtolower($fieldName), $metaFields)) {
            return 'meta';
        }

        return 'text';
    }

    /**
     * Determine if a field should be translated by default
     *
     * @param string $fieldName
     * @param string $fieldType
     * @return bool
     */
    protected function shouldFieldBeTranslated($fieldName, $fieldType)
    {
        // Don't translate slugs or codes by default
        $skipFields = ['slug', 'code', 'url', 'published', 'external', 'type'];

        return !in_array(strtolower($fieldName), $skipFields);
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
            $fields = $this->getModelTranslatableFields($instance);

            return [
                'fields' => $fields,
                'recordCount' => $modelClass::count()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Convert model name to readable label
     *
     * @param string $name
     * @return string
     */
    protected function makeLabel($name)
    {
        // Convert PascalCase to Title Case with spaces
        $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $name);
        return trim($label);
    }
}

