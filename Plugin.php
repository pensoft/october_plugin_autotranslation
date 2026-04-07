<?php namespace Pensoft\AutoTranslation;

use Backend;
use System\Classes\PluginBase;
use Pensoft\AutoTranslation\Models\Settings;

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
     */
    public function pluginDetails(): array
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
     */
    public function register(): void
    {
        //
    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Registers any back-end permissions used by this plugin.
     */
    public function registerPermissions(): array
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
     */
    public function registerNavigation(): array
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
     */
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Auto Translation',
                'description' => 'Configure DeepL API and translation settings',
                'category'    => 'rainlab.translate::lang.plugin.name',
                'icon'        => 'icon-language',
                'class'       => Settings::class,
                'order'       => 552,
                'permissions' => ['pensoft.autotranslation.manage_settings'],
                'keywords'    => 'translate deepl ai automatic',
            ]
        ];
    }
}