<?php namespace Pensoft\AutoTranslation\Models;

use Model;
use RainLab\Translate\Classes\Locale;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;

/**
 * Auto Translation Settings Model
 */
class Settings extends Model
{
    /**
     * @var array Behaviors implemented by this model
     */
    public $implement = [\System\Behaviors\SettingsModel::class];

    /**
     * @var string Unique code for settings
     */
    public string $settingsCode = 'pensoft_autotranslation_settings';

    /**
     * @var string Reference to field configuration
     */
    public string $settingsFields = 'fields.yaml';

    /**
     * Validation rules
     *
     * @var array
     */
    public $rules = [
        'deepl_api_key' => 'required|min:20',
        'deepl_server_type' => 'required|in:free,pro',
        'default_source_locale' => 'required',
    ];

    /**
     * Get locale options for dropdown
     */
    public function getLocaleOptions(): array
    {
        return Locale::listEnabled();
    }

    /**
     * Get default source locale options
     */
    public function getDefaultSourceLocaleOptions(): array
    {
        return Locale::listEnabled();
    }

}