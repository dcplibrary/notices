# notices

[![CI](https://github.com/dcplibrary/notices/actions/workflows/ci.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/ci.yml)
[![Code Quality](https://github.com/dcplibrary/notices/actions/workflows/code-quality.yml/badge.svg)](https://github.com/dcplibrary/notices/actions/workflows/code-quality.yml)
[![Latest Stable Version](https://poser.pugx.org/dcplibrary/notices/v/stable)](https://packagist.org/packages/dcplibrary/notices)
[![License](https://poser.pugx.org/dcplibrary/notices/license)](https://packagist.org/packages/dcplibrary/notices)

A Laravel package for notices functionality.

## Installation

You can install the package via composer:

```bash
composer require dcplibrary/notices
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="notices-config"
```

This will publish the configuration file to `config/notices.php`.

## Usage

### Basic Usage

```php
use Dcplibrary\notices\notices;

$instance = new notices();
echo $instance->name(); // notices
echo $instance->version(); // 1.0.0
```

### Using the Facade

```php
use Dcplibrary\notices\Facades\notices;

notices::name(); // notices
notices::version(); // 1.0.0
```

### Service Provider Registration

The service provider is automatically registered. The package provides:

- Routes at `/notices`
- Views under the `notices` namespace
- Configuration merging
- Database migrations

## Testing

Run the tests with:

```bash
composer test
```

## Code Quality

Run code formatting:

```bash
composer format
```

Run static analysis:

```bash
composer analyse
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email blashbrook@dcplibrary.org instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Brian Lashbrook](https://github.com/blashbrook)
- [All Contributors](../../contributors)

## About DC Public Library

This package is developed and maintained by the DC Public Library development team.
