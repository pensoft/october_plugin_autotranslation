<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Behaviors\TranslatableModel;
use Pensoft\AutoTranslation\Classes\FieldFilter;

/**
 * Model Discovery Service
 *
 * Discovers and catalogs translatable models across all installed plugins
 * Single Responsibility: Model scanning and metadata extraction
 */
class ModelDiscoveryService
{
    /**
     * @var FieldFilter
     */
    protected $filter;

    /**
     * Field type heuristics loaded from config
     *
     * @var array
     */
    protected $heuristics;

    /**
     * Constructor
     *
     * @param FieldFilter|null $filter
     */
    public function __construct(?FieldFilter $filter = null)
    {
        $this->filter = $filter ?: new FieldFilter();

        // Load field type heuristics from config
        $this->heuristics = \Config::get('pensoft.autotranslation::field_type_heuristics', [
            'rich_text_fields' => [],
            'slug_fields' => [],
            'meta_fields' => [],
            'skip_translation_fields' => [],
        ]);
    }

    /**
     * Get list of translatable models with detailed information
     *
     * @return array Array of model metadata keyed by class name
     */
    public function getTranslatableModels()
    {
        $models = [];
        $plugins = $this->getAllPlugins();

        foreach ($plugins as $pluginCode => $pluginObj) {
            $pluginModels = $this->scanPluginForModels($pluginCode);
            $models = array_merge($models, $pluginModels);
        }

        return $this->sortModelsByLabel($models);
    }

    /**
     * Get translatable fields from a model with their metadata
     *
     * @param \October\Rain\Database\Model $model
     * @return array Array of field metadata keyed by field name
     */
    public function getModelTranslatableFields($model)
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
     * Get all plugins from PluginManager
     *
     * @return array
     */
    protected function getAllPlugins()
    {
        $pluginManager = \System\Classes\PluginManager::instance();
        return $pluginManager->getPlugins();
    }

    /**
     * Scan plugin for translatable models
     *
     * @param string $pluginCode Plugin identifier (e.g., 'Author.Plugin')
     * @return array Array of model metadata
     */
    protected function scanPluginForModels($pluginCode)
    {
        $models = [];
        [$author, $plugin] = explode('.', $pluginCode);

        $modelsPath = $this->getPluginModelsPath($author, $plugin);

        if (!is_dir($modelsPath)) {
            return $models;
        }

        $modelFiles = glob($modelsPath . '/*.php');

        foreach ($modelFiles as $modelFile) {
            $modelInfo = $this->processModelFile($modelFile, $author, $plugin, $pluginCode);

            if ($modelInfo) {
                $models[$modelInfo['className']] = $modelInfo['data'];
            }
        }

        return $models;
    }

    /**
     * Get plugin models directory path
     *
     * @param string $author
     * @param string $plugin
     * @return string
     */
    protected function getPluginModelsPath($author, $plugin)
    {
        return plugins_path(strtolower($author) . '/' . strtolower($plugin) . '/models');
    }

    /**
     * Process a model file
     *
     * @param string $modelFile
     * @param string $author
     * @param string $plugin
     * @param string $pluginCode
     * @return array|null
     */
    protected function processModelFile($modelFile, $author, $plugin, $pluginCode)
    {
        $modelName = basename($modelFile, '.php');

        if ($this->shouldSkipModel($modelName)) {
            return null;
        }

        $className = $this->buildModelClassName($author, $plugin, $modelName);

        if (!class_exists($className)) {
            return null;
        }

        try {
            $instance = new $className();

            if (!$this->isTranslatableModel($instance)) {
                return null;
            }

            $fields = $this->getModelTranslatableFields($instance);

            if (empty($fields)) {
                return null;
            }

            return [
                'className' => $className,
                'data' => $this->buildModelInfo($instance, $className, $author, $plugin, $pluginCode, $modelName, $fields)
            ];
        } catch (\Exception $e) {
            \Log::debug("Could not process model {$className}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if model should be skipped
     *
     * @param string $modelName
     * @return bool
     */
    protected function shouldSkipModel($modelName)
    {
        // Skip models starting with lowercase (usually helpers or traits)
        return ctype_lower($modelName[0]);
    }

    /**
     * Build model class name
     *
     * @param string $author
     * @param string $plugin
     * @param string $modelName
     * @return string
     */
    protected function buildModelClassName($author, $plugin, $modelName)
    {
        return ucfirst($author) . '\\' . ucfirst($plugin) . '\\Models\\' . $modelName;
    }

    /**
     * Check if model is translatable
     *
     * @param \October\Rain\Database\Model $instance
     * @return bool
     */
    protected function isTranslatableModel($instance)
    {
        return $instance->isClassExtendedWith(TranslatableModel::class);
    }

    /**
     * Build model info array
     *
     * @param \October\Rain\Database\Model $instance
     * @param string $className
     * @param string $author
     * @param string $plugin
     * @param string $pluginCode
     * @param string $modelName
     * @param array $fields
     * @return array
     */
    protected function buildModelInfo($instance, $className, $author, $plugin, $pluginCode, $modelName, array $fields)
    {
        $label = ucfirst($author) . ' › ' . $this->makeLabel($plugin) . ' › ' . $this->makeLabel($modelName);

        return [
            'label' => $label,
            'plugin' => $pluginCode,
            'author' => $author,
            'pluginName' => $plugin,
            'modelName' => $modelName,
            'fields' => $fields,
            'recordCount' => $className::count(),
            'tableName' => $instance->getTable()
        ];
    }

    /**
     * Sort models by label
     *
     * @param array $models
     * @return array
     */
    protected function sortModelsByLabel(array $models)
    {
        uasort($models, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $models;
    }

    /**
     * Guess the field type based on field name
     * Uses heuristics from config file
     *
     * @param string $fieldName
     * @return string
     */
    protected function guessFieldType($fieldName)
    {
        $lowerFieldName = strtolower($fieldName);

        // Check rich text fields
        if (in_array($lowerFieldName, $this->heuristics['rich_text_fields'])) {
            return 'richeditor';
        }

        // Check slug fields
        if (in_array($lowerFieldName, $this->heuristics['slug_fields'])) {
            return 'slug';
        }

        // Check meta fields
        if (in_array($lowerFieldName, $this->heuristics['meta_fields'])) {
            return 'meta';
        }

        // Default to text
        return 'text';
    }

    /**
     * Determine if a field should be translated by default
     * Uses skip list from config file
     *
     * @param string $fieldName
     * @param string $fieldType
     * @return bool
     */
    protected function shouldFieldBeTranslated($fieldName, $fieldType)
    {
        // Check against skip list from config
        return !in_array(strtolower($fieldName), $this->heuristics['skip_translation_fields']);
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
