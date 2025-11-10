# Dashboard Documentation

The Polaris Notifications package includes a built-in dashboard for visualizing notification data. The dashboard can be used out-of-the-box or customized to fit your needs.

## Quick Start

After installing the package, the dashboard is available at:

```
https://yourapp.com/notices
```

**Note:** The dashboard requires authentication by default. Ensure users are logged in before accessing.

![Dashboard Overview](images/dashboard-overview.png)
*Screenshot: Dashboard overview showing metrics, trends, and charts*

> **Note:** Screenshot placeholder - See [SCREENSHOTS.md](SCREENSHOTS.md) for instructions on adding images.

## Configuration

Dashboard settings can be configured in `config/notices.php`:

```php
'dashboard' => [
    // Enable or disable the default dashboard
    'enabled' => true,

    // Route prefix for dashboard URLs
    'route_prefix' => 'notices',

    // Middleware applied to dashboard routes
    'middleware' => ['web', 'auth'],

    // Default date range for dashboard (days)
    'default_date_range' => 30,

    // Notification types to display (null = all)
    'visible_notification_types' => null,

    // Delivery methods to display (null = all)
    'visible_delivery_methods' => null,

    // Enable real-time refresh
    'enable_realtime' => false,

    // Refresh interval in seconds
    'refresh_interval' => 300,
],
```

## Dashboard Pages

### 1. Overview Page

**URL:** `/notifications`

![Dashboard Overview](images/dashboard-overview.png)

The main dashboard showing:
- **Key Metrics**: Total sent, successful, failed, total holds
- **Trend Chart**: Daily notification volume over time
- **Type Distribution**: Breakdown by notification type (Holds, Overdues, etc.)
- **Delivery Distribution**: Breakdown by delivery method (Email, SMS, Voice, Mail)
- **Shoutbomb Stats**: Current subscriber counts

**Use Cases:**
- Quick health check of notification system
- Identify trends and anomalies
- Monitor success rates
- Track subscriber growth

### 2. Notifications Page

**URL:** `/notifications/notifications`

![Notifications List](images/notifications-list.png)

A filterable list of individual notification records.

**Features:**
- Paginated table of notifications
- Filter by:
  - Notification type
  - Delivery method
  - Status
  - Date range
- View patron information
- See item counts

**Use Cases:**
- Investigate specific notifications
- Troubleshoot failed deliveries
- Audit notification history
- Search by patron

### 3. Analytics Page

**URL:** `/notifications/analytics`

![Analytics Page](images/analytics-page.png)

Detailed analytics and visualizations.

**Features:**
- Success rate trend over time
- Notification type distribution
- Delivery method distribution
- Detailed breakdowns

**Use Cases:**
- Performance analysis
- Identify delivery method effectiveness
- Track success rate improvements
- Generate reports

### 4. Shoutbomb Page

**URL:** `/notifications/shoutbomb`

![Shoutbomb Statistics](images/shoutbomb-page.png)

Shoutbomb SMS/Voice subscriber statistics.

**Features:**
- Current subscriber counts (Text vs Voice)
- Subscriber growth trends
- Registration history
- Percentage breakdowns

**Use Cases:**
- Monitor subscriber base
- Track registration trends
- Analyze Text vs Voice preferences
- Plan capacity

## Technology Stack

The default dashboard uses:
- **Blade Templates**: Laravel's templating engine
- **Tailwind CSS**: Utility-first CSS framework (via CDN)
- **Alpine.js**: Lightweight JavaScript framework for interactivity
- **Chart.js**: JavaScript charting library

**Why this stack?**
- ✅ No build step required
- ✅ Works immediately after installation
- ✅ Easy to customize
- ✅ Familiar to Laravel developers

## Customization

### Option 1: Publish and Modify Views

Publish the views to your application:

```bash
php artisan vendor:publish --tag=notices-views
```

Views will be copied to:
```
resources/views/vendor/notifications/
```

You can now modify:
- Layout (`layouts/app.blade.php`)
- Dashboard pages (`dashboard/*.blade.php`)
- Add custom charts
- Change styling

### Option 2: Extend the Layout

Create your own layout that extends the package layout:

```blade
{{-- resources/views/my-notifications/layout.blade.php --}}
@extends('notifications::layouts.app')

@section('navigation')
    {{-- Your custom navigation --}}
@endsection
```

### Option 3: Override Middleware

Change authentication or add custom middleware:

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth', 'role:admin'],  // Add role check
],
```

### Option 4: Change Route Prefix

Move the dashboard to a different URL:

```php
// config/notices.php
'dashboard' => [
    'route_prefix' => 'admin/notifications',
],
```

Now accessible at: `/admin/notifications`

### Option 5: Disable Default Dashboard

If you're building a completely custom UI using the API:

```php
// config/notices.php
'dashboard' => [
    'enabled' => false,  // Disable default dashboard
],
```

Then use the API to build your own frontend.

## Styling Customization

The dashboard uses Tailwind CSS via CDN. To customize styling:

1. **Publish the views**
2. **Modify the layout** to use your own CSS:

```blade
{{-- Remove CDN Tailwind --}}
{{-- <script src="https://cdn.tailwindcss.com"></script> --}}

{{-- Add your own CSS --}}
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
```

3. **Update component classes** in the dashboard views

## Adding Custom Charts

To add your own charts:

1. **Publish the views**
2. **Edit the dashboard view** (e.g., `dashboard/index.blade.php`)
3. **Add a new canvas element**:

```blade
<div class="bg-white shadow rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">My Custom Chart</h3>
    <canvas id="customChart" height="250"></canvas>
</div>
```

4. **Add Chart.js code** in the `@push('scripts')` section:

```blade
@push('scripts')
<script>
const customCtx = document.getElementById('customChart').getContext('2d');
new Chart(customCtx, {
    type: 'line',
    data: {
        labels: @json($yourLabels),
        datasets: [{
            label: 'My Data',
            data: @json($yourData),
            borderColor: 'rgb(99, 102, 241)',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
    }
});
</script>
@endpush
```

## Dashboard Controller Methods

To add custom pages, extend the `DashboardController`:

```php
namespace App\Http\Controllers;

use Dcplibrary\Notices\Http\Controllers\DashboardController as BaseDashboardController;

class CustomDashboardController extends BaseDashboardController
{
    public function customPage()
    {
        // Your custom logic
        return view('my-notifications.custom-page', $data);
    }
}
```

Then register your routes:

```php
Route::get('/notifications/custom', [CustomDashboardController::class, 'customPage'])
    ->name('notifications.custom');
```

## Real-time Updates

To enable auto-refresh (useful for monitoring):

```php
// config/notices.php
'dashboard' => [
    'enable_realtime' => true,
    'refresh_interval' => 300,  // 5 minutes
],
```

Add this to your layout:

```blade
@if(config('notices.dashboard.enable_realtime'))
<script>
    setInterval(() => {
        location.reload();
    }, {{ config('notices.dashboard.refresh_interval') * 1000 }});
</script>
@endif
```

## Permissions & Authorization

To restrict dashboard access to specific users:

### Option 1: Middleware

```php
// config/notices.php
'dashboard' => [
    'middleware' => ['web', 'auth', 'can:view-notifications'],
],
```

Define the ability in your `AuthServiceProvider`:

```php
Gate::define('view-notifications', function ($user) {
    return $user->is_admin;
});
```

### Option 2: Route-Level

Disable the package routes and define your own:

```php
// config/notices.php
'dashboard' => [
    'enabled' => false,
],
```

```php
// routes/web.php
Route::middleware(['auth', 'admin'])->prefix('notices')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    // ... other routes
});
```

## Troubleshooting

### Dashboard returns 404

- Ensure `dashboard.enabled` is `true` in config
- Run `php artisan config:clear`
- Check middleware authentication requirements

### Charts not rendering

- Check browser console for JavaScript errors
- Ensure Chart.js CDN is loading
- Verify data is being passed to the view

### Styling looks broken

- Ensure Tailwind CSS CDN is loading
- Check for CSS conflicts with your app's styles
- Consider publishing views and using your own CSS

### Authentication required

The dashboard requires authentication by default. Options:
1. Log in before accessing
2. Change middleware in config
3. Use `'middleware' => ['web']` to disable auth

## Performance Tips

1. **Use date range filters**: Don't load all historical data at once
2. **Enable caching**: Cache dashboard data for faster loads
3. **Limit data points**: Show 30-90 days max on charts
4. **Optimize queries**: Add indexes to database tables
5. **Use API for custom**: For complex custom dashboards, use the API

## Mobile Responsiveness

The dashboard is mobile-responsive using Tailwind's responsive classes. Test on mobile devices and adjust breakpoints as needed in published views.

![Mobile View](images/dashboard-mobile.png)
*Dashboard on mobile devices*

## Next Steps

- Customize the look and feel
- Add custom metrics and charts
- Integrate with your existing admin panel
- Build custom export functionality
- Add email reports

For API documentation, see [API.md](API.md).
