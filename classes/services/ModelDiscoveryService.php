<?php namespace Pensoft\AutoTranslation\Classes\Services;

use RainLab\Translate\Behaviors\TranslatableModel;
use Pensoft\AutoTranslation\Classes\FieldFilter;

/**
 * Model Discovery Service
 *
 * Discovers and catalogs translatable models across all installed plugins
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
     */
    public function getTranslatableModels(): array
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
     */
    public function getModelTranslatableFields($model): array
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
     */
    protected function getAllPlugins(): array
    {
        $pluginManager = \System\Classes\PluginManager::instance();
        return $pluginManager->getPlugins();
    }

    /**
     * Scan plugin for translatable models
     */
    protected function scanPluginForModels(string $pluginCode): array
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
     */
    protected function getPluginModelsPath(string $author, string $plugin): string
    {
        return plugins_path(strtolower($author) . '/' . strtolower($plugin) . '/models');
    }

    /**
     * Process a model file
     */
    protected function processModelFile(string $modelFile, string $author, string $plugin, string $pluginCode): ?array
    {
        $modelName = basename($modelFile, '.php');

        if ($this->shouldSkipModel($modelName)) {
            return null;
        }

        $className = $this->buildModelClassName($author, $plugin, $modelName);

        if (!class_exists($className)) {
            return null;
        }

        $reflection = new \ReflectionClass($className);
        if ($reflection->isAbstract()) {
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
        } catch (\Throwable $e) {
            \Log::debug("Could not process model {$className}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if model should be skipped
     */
    protected function shouldSkipModel(string $modelName): bool
    {
        // Skip models starting with lowercase (usually helpers or traits)
        return ctype_lower($modelName[0]);
    }

    /**
     * Build model class name
     */
    protected function buildModelClassName(string $author, string $plugin, string $modelName): string
    {
        return ucfirst($author) . '\\' . ucfirst($plugin) . '\\Models\\' . $modelName;
    }

    /**
     * Check if model is translatable
     */
    protected function isTranslatableModel($instance): bool
    {
        return $instance->isClassExtendedWith(TranslatableModel::class);
    }

    /**
     * Build model info array
     */
    protected function buildModelInfo($instance, string $className, string $author, string $plugin, string $pluginCode, string $modelName, array $fields): array
    {
        $label = ucfirst($author) . ' > ' . $this->makeLabel($plugin) . ' > ' . $this->makeLabel($modelName);

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
     */
    protected function sortModelsByLabel(array $models): array
    {
        uasort($models, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $models;
    }

    /**
     * Guess the field type based on field name
     * Uses heuristics from config file
     */
    protected function guessFieldType(string $fieldName): string
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
     */
    protected function shouldFieldBeTranslated(string $fieldName, string $fieldType): bool
    {
        // Check against skip list from config
        return !in_array(strtolower($fieldName), $this->heuristics['skip_translation_fields']);
    }

    /**
     * Convert model name to readable label
     */
    protected function makeLabel(string $name): string
    {
        // Convert PascalCase to Title Case with spaces
        $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $name);
        return trim($label);
    }
}