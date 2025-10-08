<?php namespace Pensoft\AutoTranslation;

use Backend;
use System\Classes\PluginBase;

/**
 * AutoTranslation Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * @var array Plugin dependencies
     */
    public $require = ['RainLab.Translate'];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Auto Translation',
            'description' => 'AI-powered translation using DeepL API for Rainlab.Translate',
            'author'      => 'Pensoft',
            'icon'        => 'icon-language',
            'homepage'    => ''
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'pensoft.autotranslation.access' => [
                'tab'   => 'Auto Translation',
                'label' => 'Access auto translation features'
            ],
            'pensoft.autotranslation.manage_settings' => [
                'tab'   => 'Auto Translation',
                'label' => 'Manage translation settings'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return [
            'autotranslation' => [
                'label'       => 'Auto Translation',
                'url'         => Backend::url('pensoft/autotranslation/autotranslate'),
                'icon'        => 'icon-language',
                'permissions' => ['pensoft.autotranslation.access'],
                'order'       => 500,
                
                'sideMenu' => [
                    'messages' => [
                        'label'       => 'Translate Messages',
                        'icon'        => 'icon-list-alt',
                        'url'         => Backend::url('pensoft/autotranslation/autotranslate/messages'),
                        'permissions' => ['pensoft.autotranslation.access']
                    ],
                    'models' => [
                        'label'       => 'Translate Models',
                        'icon'        => 'icon-database',
                        'url'         => Backend::url('pensoft/autotranslation/autotranslate/models'),
                        'permissions' => ['pensoft.autotranslation.access']
                    ],
                ],
            ],
        ];
    }

    /**
     * Registers settings for this plugin.
     *
     * @return array
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Auto Translation',
                'description' => 'Configure DeepL API and translation settings',
                'category'    => 'rainlab.translate::lang.plugin.name',
                'icon'        => 'icon-language',
                'class'       => 'Pensoft\AutoTranslation\Models\Settings',
                'order'       => 552,
                'permissions' => ['pensoft.autotranslation.manage_settings'],
                'keywords'    => 'translate deepl ai automatic',
            ]
        ];
    }
}
