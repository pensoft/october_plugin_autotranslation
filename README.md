# Auto Translation Plugin

AI-powered automatic translation for October CMS using DeepL API. This plugin extends the RainLab.Translate plugin with intelligent auto-translation capabilities.

## Features

- 🤖 **AI-Powered Translation** - Uses DeepL's state-of-the-art neural translation
- 🎯 **Smart Field Detection** - Automatically identifies which fields should be translated
- 📝 **HTML Preservation** - Maintains formatting in rich editor content
- 🔄 **Batch Processing** - Efficiently translates multiple items at once
- 🎛️ **Flexible Configuration** - Extensive customization options
- 📊 **Usage Tracking** - Monitor your DeepL API usage
- 🌍 **Multi-Language Support** - Works with all RainLab.Translate enabled locales

## Requirements

- PHP >= 7.2.9
- October CMS 2.0+
- Laravel 6.0+
- RainLab.Translate Plugin
- DeepL API Key (Free or Pro)

## Installation

1. Install the plugin via Composer:
   ```bash
   cd plugins/pensoft/autotranslation
   composer install
   ```

2. Run database migrations:
   ```bash
   php artisan october:migrate
   ```

3. Configure your DeepL API key:
   - Go to **Settings → Auto Translation**
   - Enter your DeepL API key
   - Select your API plan type (Free or Pro)
   - Save settings

## Usage

### Translate Messages

1. Navigate to **Auto Translation → Translate Messages**
2. Select source language (usually your default locale)
3. Select target language(s) you want to translate to
4. Click "Translate All Messages"

### Translate Models

1. Navigate to **Auto Translation → Translate Models**
2. Select the model type (e.g., Blog Posts, Pages)
3. Select source and target languages
4. Click "Translate All Records"

### Smart Field Filtering

The plugin automatically excludes certain fields from translation:

**Excluded field types:**
- Dropdowns, checkboxes, radios
- Dates, numbers
- File uploads, media
- Relations

**Excluded field patterns:**
- Fields ending in: `_id`, `_at`, `slug`, `url`, `code`, `key`
- System fields: `id`, `created_at`, `updated_at`

**Custom exclusions:**
You can add custom field exclusions in Settings → Auto Translation → Excluded Field Names

### HTML Content

Rich editor content (RichEditor, Markdown) is automatically detected and HTML structure is preserved during translation.

## Configuration

### Settings Options

**API Configuration:**
- DeepL API Key (required)
- Server Type (Free or Pro)

**Translation Options:**
- Default Source Language
- Preserve HTML Formatting
- Excluded Field Names

**Advanced:**
- Batch Size (1-100)
- Enable Queue Processing
- Auto-translate on Save (experimental)
- Log Translation Activity

## Model Integration

To make your custom model translatable and compatible with Auto Translation:

```php
class YourModel extends Model
{
    public $implement = ['@RainLab.Translate.Behaviors.TranslatableModel'];
    
    public $translatable = [
        'title',
        'content',
        'description'
    ];
}
```

## API Usage

You can also use the translation services programmatically:

```php
use Pensoft\AutoTranslation\Classes\TranslationManager;
use Pensoft\AutoTranslation\Classes\DeepLTranslator;

// Translate text directly
$translator = new DeepLTranslator();
$result = $translator->translateText('Hello, world!', 'en', 'de');

// Translate a model
$manager = new TranslationManager();
$manager->translateModel($model, 'en', 'de');

// Translate messages
$count = $manager->translateMessages('en', 'de');
```

## Supported Languages

DeepL supports translation between many languages. When configuring locales in **Settings → Translate → Locales**, use DeepL-compatible codes:

- English: `EN-US`, `EN-GB`
- Portuguese: `PT-PT`, `PT-BR`
- Most other languages: Use uppercase 2-letter codes (`BG`, `FR`, `DE`, `ES`, `IT`, `NL`, `PL`, `RU`, `JA`, `ZH`, etc.)

**Important:** Your locale codes in RainLab.Translate must match DeepL's format exactly. For example:
- Use `BG` (not `bg` or `bulgarian`)
- Use `EN-US` or `EN-GB` (not just `en`)
- Use `PT-BR` or `PT-PT` (not just `pt`)

See [DeepL Documentation](https://www.deepl.com/docs-api/) for the complete list of supported languages and their codes.

## Troubleshooting

### "API key is not configured"
Make sure you've entered your DeepL API key in Settings → Auto Translation.

### "Connection failed"
1. Verify your API key is correct
2. Check you've selected the correct server type (Free vs Pro)
3. Ensure your server can connect to `api.deepl.com` or `api-free.deepl.com`

### Translations not appearing
1. Make sure the model implements `TranslatableModel` behavior
2. Check the field is in the model's `$translatable` array
3. Verify the field type is translatable (text, textarea, richeditor, markdown)

## License

Proprietary

## Support

For issues and questions, please contact Pensoft support.

## Credits

- Built with [DeepL PHP SDK](https://github.com/DeepLcom/deepl-php)
- Extends [RainLab.Translate Plugin](https://github.com/rainlab/translate-plugin)

