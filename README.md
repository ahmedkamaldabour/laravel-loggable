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

### Customizing Log Storage

You can customize the log table and fields in the config file:

```php
// config/loggable.php
return [
    'table_name' => 'activity_logs',
    'max_length' => 500, // Maximum length for text fields
    'store_ip' => true,
    'store_user_agent' => true,
];
```

### Excluding Attributes

Exclude specific attributes from logging:

```php
class Product extends Model
{
    use Loggable;

    protected static $logExcept = [
        'password',
        'remember_token',
        'secret_key'
    ];
}
```

## Events

The package dispatches events that you can listen to:

- `ActivityLogged`: When a new activity is logged
- `ActivityLogFailed`: When logging fails

## Testing

Run the test suite:

```bash
composer test
```

For test coverage report:

```bash
composer test-coverage
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email ahmed.kamal.dabour@gmail.com instead of using the issue tracker.

## Credits

- [Ahmed-Kamal-Dabour](https://github.com/ahmedkamaldabour)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
