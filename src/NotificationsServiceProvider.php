<?php

namespace Dcplibrary\Notifications;

// Ensure the original provider class is loaded even if its namespace isn't mapped by Composer
require_once __DIR__ . '/PolarisNotificationsServiceProvider.php';

class NotificationsServiceProvider extends \Dcplibrary\PolarisNotifications\PolarisNotificationsServiceProvider
{
    public function boot(): void
    {
        // Preload command classes to satisfy Laravel's container without relying on Composer PSR-4 for the Polaris namespace
        foreach ([
            'ImportPolarisNotifications',
            'ImportShoutbombReports',
            'AggregateNotifications',
            'TestConnections',
            'SeedDemoDataCommand',
        ] as $file) {
            $path = __DIR__ . '/Commands/' . $file . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        parent::boot();
    }
}
