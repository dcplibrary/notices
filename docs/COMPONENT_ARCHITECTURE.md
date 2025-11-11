# Component Architecture Design

## Overview

Break the notification system into independent, composable packages similar to Orchestra/Testbench.

## Package Structure

```
dcplibrary/
├── notifications-core          (Required - Foundation)
├── notifications-shoutbomb     (Optional - Shoutbomb plugin)
├── notifications-email         (Optional - Email plugin)
├── notifications-dashboard     (Optional - Web UI)
├── notifications-api           (Optional - REST API)
└── notifications               (Meta-package - Installs common set)
```

## 1. Core Package (`dcplibrary/notices-core`)

**Purpose**: Foundation package with base interfaces and shared logic

**Includes**:
```
notifications-core/
├── src/
│   ├── Contracts/
│   │   ├── NotificationChannel.php          (Interface)
│   │   ├── NotificationVerifier.php          (Interface)
│   │   ├── NotificationStorage.php           (Interface)
│   │   └── SettingsRepository.php            (Interface)
│   ├── Models/
│   │   ├── NotificationLog.php               (Master record)
│   │   ├── NotificationSetting.php           (DB-stored settings)
│   │   └── PatronPreference.php              (Opt-in/out tracking)
│   ├── Services/
│   │   ├── NotificationManager.php           (Channel registry)
│   │   ├── VerificationService.php           (Core verification)
│   │   └── SettingsManager.php               (Settings abstraction)
│   ├── Events/
│   │   ├── NotificationCreated.php
│   │   ├── NotificationSent.php
│   │   ├── NotificationFailed.php
│   │   └── NotificationVerified.php
│   └── Database/
│       └── migrations/
│           ├── create_notification_logs_table.php
│           ├── create_notification_settings_table.php
│           └── create_patron_preferences_table.php
├── config/
│   └── notifications.php                     (Default config)
└── composer.json
```

**Key Interfaces**:

```php
<?php

namespace Dcplibrary\Notices\Contracts;

interface NotificationChannel
{
    public function getName(): string;
    public function getIdentifier(): string;
    public function isEnabled(): bool;

    public function send(NotificationLog $notification): bool;
    public function verify(NotificationLog $notification): VerificationResult;
    public function getStatistics(Carbon $start, Carbon $end): array;

    public function getSettingsSchema(): array;
    public function getApiEndpoints(): array;
    public function getDashboardRoutes(): array;
}

interface NotificationVerifier
{
    public function verify(NotificationLog $notification): VerificationResult;
    public function getTimeline(NotificationLog $notification): array;
    public function findDiscrepancies(Carbon $date): array;
}

interface SettingsRepository
{
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
    public function getAll(): array;
    public function getScopedSettings(string $scope, string $scopeId): array;
}
```

**Installation**:
```bash
composer require dcplibrary/notices-core
```

---

## 2. Shoutbomb Package (`dcplibrary/notices-shoutbomb`)

**Purpose**: Shoutbomb voice/text channel implementation

**Includes**:
```
notifications-shoutbomb/
├── src/
│   ├── ShoutbombChannel.php                  (Implements NotificationChannel)
│   ├── Models/
│   │   ├── ShoutbombSubmission.php
│   │   ├── PolarisPhoneNotice.php
│   │   └── ShoutbombDelivery.php
│   ├── Services/
│   │   ├── ShoutbombFTPService.php
│   │   ├── ShoutbombSubmissionImporter.php
│   │   ├── PolarisPhoneNoticeImporter.php
│   │   └── ShoutbombVerifier.php             (Implements NotificationVerifier)
│   ├── Commands/
│   │   ├── ImportShoutbombSubmissions.php
│   │   ├── ImportPolarisPhoneNotices.php
│   │   └── ImportShoutbombReports.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── ShoutbombController.php       (API endpoints)
│   └── Database/
│       └── migrations/
│           ├── create_shoutbomb_submissions_table.php
│           ├── create_polaris_phone_notices_table.php
│           └── create_shoutbomb_deliveries_table.php
├── config/
│   └── shoutbomb.php
└── composer.json
    {
      "require": {
        "dcplibrary/notices-core": "^1.0"
      }
    }
```

**Registration**:
```php
// In ShoutbombServiceProvider
public function register()
{
    $this->app->singleton(ShoutbombChannel::class);

    // Register channel with core
    $this->app->make(NotificationManager::class)
        ->registerChannel(new ShoutbombChannel());
}
```

**Installation**:
```bash
composer require dcplibrary/notices-shoutbomb
```

---

## 3. Email Package (`dcplibrary/notices-email`)

**Purpose**: Email notification channel

**Includes**:
```
notifications-email/
├── src/
│   ├── EmailChannel.php                      (Implements NotificationChannel)
│   ├── Models/
│   │   └── EmailDelivery.php
│   ├── Services/
│   │   ├── EmailImporter.php
│   │   └── EmailVerifier.php
│   ├── Commands/
│   │   └── ImportEmailReports.php
│   └── Database/
│       └── migrations/
│           └── create_email_deliveries_table.php
├── config/
│   └── email-notifications.php
└── composer.json
    {
      "require": {
        "dcplibrary/notices-core": "^1.0"
      }
    }
```

---

## 4. Dashboard Package (`dcplibrary/notices-dashboard`)

**Purpose**: Web UI for viewing and managing notifications

**Includes**:
```
notifications-dashboard/
├── src/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── VerificationController.php
│   │   │   ├── TroubleshootingController.php
│   │   │   └── SettingsController.php       (NEW - Manage DB settings)
│   │   └── Middleware/
│   │       └── CheckNotificationAccess.php
│   └── Views/
│       ├── dashboard/
│       │   ├── overview.blade.php
│       │   ├── verification.blade.php
│       │   ├── troubleshooting.blade.php
│       │   └── settings.blade.php           (NEW - Settings UI)
│       ├── components/
│       │   ├── timeline.blade.php
│       │   ├── stats-card.blade.php
│       │   └── channel-widget.blade.php
│       └── layouts/
│           └── app.blade.php
├── routes/
│   └── web.php
├── public/
│   ├── css/
│   └── js/
└── composer.json
    {
      "require": {
        "dcplibrary/notices-core": "^1.0"
      },
      "suggest": {
        "dcplibrary/notices-shoutbomb": "For Shoutbomb widgets",
        "dcplibrary/notices-email": "For Email widgets"
      }
    }
```

**Dynamic Channel Widgets**:
```blade
{{-- In overview.blade.php --}}
@foreach($channels as $channel)
    @include($channel->getDashboardWidget())
@endforeach
```

---

## 5. API Package (`dcplibrary/notices-api`)

**Purpose**: RESTful API for programmatic access

**Includes**:
```
notifications-api/
├── src/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── NotificationController.php
│   │   │   ├── VerificationController.php
│   │   │   ├── PatronController.php
│   │   │   └── SettingsController.php       (NEW - API for settings)
│   │   ├── Resources/
│   │   │   ├── NotificationResource.php
│   │   │   ├── VerificationResource.php
│   │   │   └── TimelineResource.php
│   │   └── Middleware/
│   │       └── ApiRateLimiter.php
│   └── Documentation/
│       └── openapi.yaml                      (OpenAPI spec)
├── routes/
│   └── api.php
└── composer.json
    {
      "require": {
        "dcplibrary/notices-core": "^1.0"
      }
    }
```

**Dynamic Channel Endpoints**:
```php
// In NotificationsApiServiceProvider
public function boot()
{
    $manager = $this->app->make(NotificationManager::class);

    foreach ($manager->getChannels() as $channel) {
        foreach ($channel->getApiEndpoints() as $endpoint) {
            Route::get($endpoint['path'], $endpoint['handler']);
        }
    }
}
```

---

## 6. Meta Package (`dcplibrary/notices`)

**Purpose**: Convenience package that installs common components

```json
{
  "name": "dcplibrary/notices",
  "description": "Complete notification system for libraries",
  "require": {
    "dcplibrary/notices-core": "^1.0",
    "dcplibrary/notices-shoutbomb": "^1.0",
    "dcplibrary/notices-email": "^1.0",
    "dcplibrary/notices-dashboard": "^1.0",
    "dcplibrary/notices-api": "^1.0"
  }
}
```

**Installation** (All-in-one):
```bash
composer require dcplibrary/notices
```

---

## Database-Stored Settings

### Settings Schema

**Migration: `create_notification_settings_table.php`**
```php
Schema::create('notification_settings', function (Blueprint $table) {
    $table->id();

    // Scoping (for multi-tenant or per-branch settings)
    $table->string('scope')->nullable()->index();      // 'global', 'branch', 'channel'
    $table->string('scope_id')->nullable()->index();   // Branch ID, Channel ID, etc.

    // Setting
    $table->string('key')->index();                    // 'shoutbomb.ftp.host'
    $table->text('value');                             // JSON-encoded value
    $table->string('type')->default('string');         // string, int, bool, json, encrypted

    // Metadata
    $table->text('description')->nullable();
    $table->boolean('is_public')->default(false);      // Can be exposed to API
    $table->boolean('is_editable')->default(true);     // Can be changed via UI

    $table->timestamps();

    // Unique constraint
    $table->unique(['scope', 'scope_id', 'key']);
});
```

**Migration: `create_patron_preferences_table.php`**
```php
Schema::create('patron_preferences', function (Blueprint $table) {
    $table->id();

    // Patron identification
    $table->string('patron_barcode', 20)->index();
    $table->integer('patron_id')->nullable()->index();

    // Preferences
    $table->json('channel_preferences')->nullable();   // Preferred channels
    $table->boolean('opt_out_voice')->default(false);
    $table->boolean('opt_out_text')->default(false);
    $table->boolean('opt_out_email')->default(false);
    $table->boolean('opt_out_all')->default(false);

    // Contact info (may differ from ILS)
    $table->string('phone_number', 20)->nullable();
    $table->string('email')->nullable();

    // Notification preferences
    $table->json('notification_type_preferences')->nullable();  // Which types to receive
    $table->time('quiet_hours_start')->nullable();              // Don't call before
    $table->time('quiet_hours_end')->nullable();                // Don't call after

    $table->timestamps();

    $table->unique('patron_barcode');
});
```

### Settings Manager

**Core Service**:
```php
<?php

namespace Dcplibrary\Notices\Services;

class SettingsManager implements SettingsRepository
{
    /**
     * Get setting with fallback to config files.
     */
    public function get(string $key, $default = null)
    {
        // Try database first
        $setting = NotificationSetting::whereNull('scope')
            ->where('key', $key)
            ->first();

        if ($setting) {
            return $this->castValue($setting->value, $setting->type);
        }

        // Fallback to config file
        return config("notifications.{$key}", $default);
    }

    /**
     * Get scoped settings (e.g., per-branch).
     */
    public function getScopedSettings(string $scope, string $scopeId): array
    {
        return NotificationSetting::where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Get channel-specific settings.
     */
    public function getChannelSettings(string $channelId): array
    {
        return $this->getScopedSettings('channel', $channelId);
    }

    /**
     * Set setting (database only).
     */
    public function set(string $key, $value, ?string $scope = null, ?string $scopeId = null): void
    {
        NotificationSetting::updateOrCreate(
            [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $this->detectType($value),
            ]
        );
    }

    /**
     * Get patron preferences.
     */
    public function getPatronPreferences(string $patronBarcode): ?PatronPreference
    {
        return PatronPreference::where('patron_barcode', $patronBarcode)->first();
    }
}
```

### Settings UI (Dashboard Package)

**Controller**:
```php
<?php

namespace Dcplibrary\NoticesDashboard\Http\Controllers;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = NotificationSetting::whereNull('scope')
            ->where('is_editable', true)
            ->get()
            ->groupBy(function($setting) {
                return explode('.', $setting->key)[0];  // Group by prefix
            });

        return view('notifications::settings.index', compact('settings'));
    }

    public function update(Request $request, $id)
    {
        $setting = NotificationSetting::findOrFail($id);

        if (!$setting->is_editable) {
            abort(403, 'This setting cannot be modified via UI');
        }

        $setting->update([
            'value' => $request->input('value'),
        ]);

        event(new SettingChanged($setting));

        return redirect()->back()->with('success', 'Setting updated');
    }
}
```

**View (settings/index.blade.php)**:
```blade
@foreach($settings as $group => $groupSettings)
    <div class="settings-group">
        <h3>{{ ucfirst($group) }} Settings</h3>

        @foreach($groupSettings as $setting)
            <div class="setting-row">
                <label>{{ $setting->key }}</label>
                <small>{{ $setting->description }}</small>

                @if($setting->type === 'boolean')
                    <input type="checkbox"
                           value="1"
                           {{ $setting->value ? 'checked' : '' }}
                           onchange="updateSetting({{ $setting->id }}, this.checked)">
                @elseif($setting->type === 'encrypted')
                    <input type="password"
                           value="{{ decrypt($setting->value) }}"
                           onchange="updateSetting({{ $setting->id }}, this.value)">
                @else
                    <input type="text"
                           value="{{ $setting->value }}"
                           onchange="updateSetting({{ $setting->id }}, this.value)">
                @endif
            </div>
        @endforeach
    </div>
@endforeach
```

---

## Settings Examples

### Global Settings (Database)
```php
// Store in database
SettingsManager::set('shoutbomb.ftp.host', 'ftp.shoutbomb.com');
SettingsManager::set('shoutbomb.ftp.username', 'user123');
SettingsManager::set('shoutbomb.ftp.password', 'secret', type: 'encrypted');

// Retrieve
$host = SettingsManager::get('shoutbomb.ftp.host');
```

### Branch-Specific Settings
```php
// Different branches use different Shoutbomb accounts
SettingsManager::set(
    'shoutbomb.account_id',
    'BRANCH_123',
    scope: 'branch',
    scopeId: '3'  // Branch ID
);

// Retrieve
$accountId = SettingsManager::getScopedSettings('branch', '3')['shoutbomb.account_id'];
```

### Patron Preferences
```php
// Patron opts out of text
PatronPreference::updateOrCreate(
    ['patron_barcode' => '23307013757366'],
    ['opt_out_text' => true]
);

// Check before sending
$prefs = SettingsManager::getPatronPreferences('23307013757366');
if ($prefs && $prefs->opt_out_text) {
    // Use different channel
}
```

---

## Installation Scenarios

### Scenario 1: Full Installation
```bash
composer require dcplibrary/notices
```
Gets: Core, Shoutbomb, Email, Dashboard, API

### Scenario 2: Core + Shoutbomb Only
```bash
composer require dcplibrary/notices-core
composer require dcplibrary/notices-shoutbomb
```
No dashboard, no API - just data collection and verification

### Scenario 3: Add Dashboard Later
```bash
composer require dcplibrary/notices-dashboard
```
Dashboard automatically discovers installed channels

### Scenario 4: Custom Channel
```bash
composer require dcplibrary/notices-core
composer require yourcompany/notifications-twilio  # Your custom package
```

---

## Benefits

### Component Architecture
✅ **Modularity**: Install only what you need
✅ **Maintainability**: Each package has clear boundaries
✅ **Testing**: Test components in isolation
✅ **Versioning**: Update packages independently
✅ **Flexibility**: Swap implementations easily
✅ **Team Collaboration**: Work on different packages without conflicts

### Database Settings
✅ **Runtime Changes**: No code deployment for config changes
✅ **Per-Branch Config**: Multi-tenant support
✅ **UI Management**: Non-developers can adjust settings
✅ **Audit Trail**: Track who changed what and when
✅ **Patron Control**: Let patrons manage their preferences
✅ **Feature Flags**: Enable/disable features dynamically

---

## Migration Path

### Current State → Component Architecture

1. **Extract Core** (Week 1)
   - Move base classes to notifications-core
   - Define interfaces
   - Create settings manager

2. **Extract Shoutbomb** (Week 2)
   - Move Shoutbomb code to separate package
   - Implement NotificationChannel interface
   - Test as independent package

3. **Extract Dashboard** (Week 3)
   - Move views and controllers
   - Dynamic channel discovery
   - Settings UI

4. **Extract API** (Week 4)
   - Move API controllers
   - Dynamic endpoint registration
   - OpenAPI documentation

5. **Create Meta Package** (Week 5)
   - Combine for easy installation
   - Update documentation
   - Migration guide

---

## Recommendation

**Phase 1**: Start with database settings NOW
- Easiest to implement
- Immediate benefit
- No architecture changes needed

**Phase 2**: Plan component split for v2.0
- Gives time to stabilize current code
- Clear breaking change marker
- Can migrate gradually

This gives you flexibility now and sets up for clean architecture later.
