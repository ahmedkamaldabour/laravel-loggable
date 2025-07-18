# Laravel Loggable

Enhanced activity logging for Laravel with metadata, relationships tracking, and size management.

## Features

- 🚀 Easy to implement activity logging
- 📝 Customizable attribute mapping
- 🔍 Metadata tracking (IP, User Agent)
- 🔗 Relationship logging
- 📊 Content size management
- 🎯 Selective attribute logging
- 💾 Efficient storage handling
- 🌐 Translatable fields support
- 📄 JSON field handling
- 🧩 Nested JSON structure support
- ✨ Automatic translatable field processing (v1.0.5+)
- 🛡️ Support for Laravel 8.x, 9.x, and 10.x

## Installation

You can install the package via composer:

```bash
composer require devdabour/laravel-loggable
```

The package will automatically register its service provider.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Devdabour\LaravelLoggable\Providers\LoggableServiceProvider"
```

This will create a `loggable.php` configuration file in your `config` directory.

## Database Migration

After installing the package, you need to run the migration to create the activity log table:

```bash
php artisan migrate
```

This will create the necessary database table for storing activity logs.

## Basic Usage

1. Add the Loggable trait to your model:

```php
use Devdabour\LaravelLoggable\Traits\Loggable;

class Product extends Model
{
    use Loggable;

    protected static array $logAttributes = [
        'name' => 'Product Name',
        'price' => 'Price',
        'status' => 'Status',
    ];
}
```

## Advanced Usage

### Metadata Logging

Enable metadata logging to track IP address, user agent, and custom data:

```php
class Product extends Model
{
    use Loggable;

    // Enable metadata logging
    protected static $logMetadata = true;

    // Add custom metadata
    protected static $logAdditionalData = [
        'custom_field' => 'Custom Label',
        'department' => 'Department Name'
    ];
}
```

### Relationship Tracking

Track changes in related models:

```php
class Product extends Model
{
    use Loggable;

    protected static $logRelationships = [
        'category' => ['id', 'name'],
        'tags' => ['id', 'name'],
        'manufacturer' => ['id', 'company_name']
    ];
}
```

### Translatable Fields Support

If you're using Spatie's Translatable trait, Loggable will automatically handle translatable fields and log each language separately:

> **Note:** Starting from version 1.0.5, translatable fields are processed automatically without requiring any custom methods.

#### Solution 1: Using the Nested Array Format (Recommended)

The most reliable way to ensure all languages are logged is to use the nested array format for your `$logAttributes`:

```php
use Devdabour\LaravelLoggable\Traits\Loggable;
use Spatie\Translatable\HasTranslations;

class Phase extends Model
{
    use HasFactory;
    use Loggable;
    use HasTranslations;

    public $translatable = ['name'];

    // Define language-specific labels in a nested structure
    protected static array $logAttributes = [
        'name' => [
            'en' => 'English Name',
            'ar' => 'Arabic Name',
        ],
    ];
}
```

This ensures that each language translation is logged separately, regardless of the current application locale.

#### Solution 2: Explicitly Define JSON Fields

You can also explicitly declare your translatable fields as JSON fields:

```php
protected static array $jsonFields = ['name', 'description'];

protected static array $logAttributes = [
    'name_en' => 'English Name',
    'name_ar' => 'Arabic Name',
];
```

#### Solution 3: No Configuration Needed (v1.0.5+)

With version 1.0.5 and above, translatable fields are automatically processed without requiring any custom methods:

```php
use Devdabour\LaravelLoggable\Traits\Loggable;
use Spatie\Translatable\HasTranslations;

class Phase extends Model
{
    use HasFactory;
    use Loggable;
    use HasTranslations;

    public $translatable = ['name'];
    
    // That's it! All languages will be logged automatically
    // Each language will appear as "name (en)", "name (ar)", etc.
}
```

If you do need custom processing, you can still implement a `modelCustomTapActivity` method which will be called after the automatic processing.

### JSON Field Handling

Loggable now supports any JSON column, not just translatable fields:

```php
class UserSettings extends Model
{
    use Loggable;

    protected $casts = [
        'preferences' => 'json',
        'notifications' => 'json',
    ];

    // Tell Loggable which fields contain JSON data
    protected static array $jsonFields = ['preferences', 'notifications'];

    // Optional: Map specific JSON paths to readable labels
    protected static array $logAttributes = [
        'preferences' => [
            'theme' => 'UI Theme',
            'language' => 'Display Language',
        ],
        'notifications.email' => 'Email Notifications',
        'notifications.push' => 'Push Notifications',
    ];
}
```

### Nested JSON Structures

For complex nested JSON data, Loggable automatically flattens the structure using dot notation:

```php
// Database JSON: {"settings":{"theme":"dark","notifications":{"email":true,"push":false}}}

// Activity log will show:
// settings.theme: dark
// settings.notifications.email: true
// settings.notifications.push: false
```

You can provide custom labels for nested paths:

```php
protected static array $logAttributes = [
    'settings.theme' => 'Theme Setting',
    'settings.notifications.email' => 'Email Notifications',
];
```

## Utility Methods

The package provides several utility methods:

```php
// Check if a string is valid JSON
$model->isJson($string);

// Get human-readable attributes
$attributes = $model->getLogAttributes();

// Get custom log name
$logName = $model->getLogName();
```

## Changelog

### 1.0.5 (2025-07-18)
- Added automatic processing of translatable fields
- Fixed issues with modelCustomTapActivity method
- Improved handling of translatable fields across multiple languages

### 1.0.4 (2025-07-18)
- Made isJson method public for better utility access
- Fixed accessibility issues with helper methods

### 1.0.3 (2025-07-18)
- Added support for JSON field handling
- Added nested JSON structure support
- Enhanced mapping for complex data structures

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
