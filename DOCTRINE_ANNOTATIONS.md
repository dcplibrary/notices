# Doctrine Annotations - Not Used

This package **does not use** `doctrine/annotations` and has no dependency on it.

## Package Architecture

This package uses **Laravel Eloquent ORM**, which uses:
- PHP arrays for configuration
- Eloquent model syntax
- No annotation-based mapping

## dcplibrary Package Dependencies

**This package (`dcplibrary/notices`):**
- ✅ Has NO composer dependencies on other `dcplibrary/*` packages
- ✅ Has NO `doctrine/annotations` dependency
- ✅ Has an optional integration with `dcplibrary/shoutbomb-reports` (NOT a hard dependency)

**Optional Integration:**
The package references `dcplibrary/shoutbomb-reports` but intentionally avoids making it a hard dependency by using a lightweight model that reads the table if it exists.

To check if `dcplibrary/shoutbomb-reports` (if you use it) has `doctrine/annotations`:
```bash
# From your main Laravel application
composer show dcplibrary/shoutbomb-reports --all | grep -A 20 "requires"
```

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
