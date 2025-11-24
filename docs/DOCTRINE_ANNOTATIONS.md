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
- ✅ Now owns the `NoticeFailureReport` model (merged from shoutbomb-reports)

### About dcplibrary/shoutbomb-reports

**Findings from package analysis:**
- ✅ Does NOT use `doctrine/annotations` in its code
- ✅ Uses Laravel Eloquent ORM (same as notices package)
- ✅ Uses standard PHP arrays for model configuration
- ⚠️ `doctrine/annotations` appears in `composer.lock` as a **transitive dependency**
  - Pulled in by `microsoft/microsoft-graph` package
  - Not used by shoutbomb-reports code itself
  - Safe to ignore - it's a dependency-of-a-dependency

**Package Details:**
- **Name:** dcplibrary/shoutbomb-reports
- **Purpose:** Parse Shoutbomb report emails via Microsoft Graph API
- **Architecture:** Laravel Eloquent models, no Doctrine annotations
- **Integration:** Now **depends on** dcplibrary/notices package (as of v1.x)

**Recent Changes (Package Merge):**
As of version 1.x, the `NoticeFailureReport` model and its migration have been moved from shoutbomb-reports to the notices package to consolidate all notice-related models in one place. The shoutbomb-reports package now:
- Requires `dcplibrary/notices` as a dependency
- Uses a class alias for backwards compatibility
- Delegates table creation to the notices package

**Conclusion:** Neither notices nor shoutbomb-reports packages use doctrine/annotations. If you see it in your dependency tree, it's coming from microsoft/microsoft-graph (used by shoutbomb-reports) or other third-party packages.

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
