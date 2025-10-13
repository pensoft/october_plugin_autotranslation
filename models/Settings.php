<?php namespace Pensoft\AutoTranslation\Models;

use Model;
use RainLab\Translate\Models\Locale;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;

/**
 * Auto Translation Settings Model
 */
class Settings extends Model
{
    /**
     * @var array Behaviors implemented by this model
     */
    public $implement = ['System.Behaviors.SettingsModel'];

    /**
     * @var string Unique code for settings
     */
    public $settingsCode = 'pensoft_autotranslation_settings';

    /**
     * @var string Reference to field configuration
     */
    public $settingsFields = 'fields.yaml';

    /**
     * Validation rules
     *
     * @var array
     */
    public $rules = [
        'deepl_api_key' => 'required|min:20',
        'deepl_server_type' => 'required|in:free,pro',
    ];

    /**
     * Get locale options for dropdown
     *
     * @return array
     */
    public function getLocaleOptions()
    {
        return Locale::listEnabled();
    }

    /**
     * Get default source locale options
     *
     * @return array
     */
    public function getDefaultSourceLocaleOptions()
    {
        return Locale::listEnabled();
    }

}

