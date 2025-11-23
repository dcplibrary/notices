# Doctrine Annotations - Not Used

This package **does not use** `doctrine/annotations` and has no dependency on it.

## Package Architecture

This package uses **Laravel Eloquent ORM**, which uses:
- PHP arrays for configuration
- Eloquent model syntax
- No annotation-based mapping

## If You See doctrine/annotations in Your Project

If `doctrine/annotations` appears in your main Laravel application (not this package), check:

```bash
# See what requires it
composer why doctrine/annotations

# Common causes:
# 1. laravel-doctrine/orm package
# 2. API documentation packages (some older versions)
# 3. Legacy dependencies
```

## Removal from Parent Application

If your Laravel app has it and you don't need it:

```bash
# Remove the package that requires it
composer remove laravel-doctrine/orm  # or whatever package needs it

# Or update to newer versions that use PHP 8 Attributes instead
composer update --with-dependencies
```

## Migration Path (If Using Doctrine ORM Elsewhere)

Doctrine annotations are deprecated. Modern approach uses PHP 8 Attributes:

### Old (Deprecated)
```php
/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User {}
```

### New (PHP 8+)
```php
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User {}
```

## This Package's Approach

We use Laravel Eloquent (no annotations needed):

```php
class NotificationLog extends Model
{
    protected $table = 'notification_logs';
    protected $fillable = ['patron_barcode', ...];
    protected $casts = ['notification_date' => 'datetime'];
}
```

## Compatibility

This package is compatible with:
- ✅ PHP 8.1+
- ✅ Laravel 11+
- ✅ No Doctrine dependencies
- ✅ No annotation libraries needed
