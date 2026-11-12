<?php

namespace Dcplibrary\Notices\Tests\Feature;

use Dcplibrary\Notices\Http\Controllers\SettingsActivityController;
use Dcplibrary\Notices\Http\Controllers\SettingsApiTokensController;
use Dcplibrary\Notices\Http\Controllers\SettingsController;
use Dcplibrary\Notices\Http\Controllers\SettingsDisplayController;
use Dcplibrary\Notices\Http\Controllers\SettingsJobsController;
use Dcplibrary\Notices\Http\Controllers\SettingsMaintenanceBackupController;
use Dcplibrary\Notices\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SettingsRouteWiringTest extends TestCase
{
    #[Test]
    public function key_settings_web_routes_point_to_modular_controllers(): void
    {
        $this->assertRouteAction('notices.settings.index', SettingsController::class, 'index', ['GET']);
        $this->assertRouteAction('notices.settings.maintenance', SettingsMaintenanceBackupController::class, 'maintenance', ['GET']);
        $this->assertRouteAction('notices.settings.maintenance.config-backup', SettingsMaintenanceBackupController::class, 'configBackup', ['GET']);
        $this->assertRouteAction('notices.settings.display', SettingsDisplayController::class, 'display', ['GET']);
        $this->assertRouteAction('notices.settings.messages', SettingsDisplayController::class, 'messages', ['GET']);
        $this->assertRouteAction('notices.settings.api-tokens', SettingsApiTokensController::class, 'apiTokens', ['GET']);
        $this->assertRouteAction('notices.settings.jobs', SettingsJobsController::class, 'jobs', ['GET']);
    }

    #[Test]
    public function key_settings_admin_api_routes_point_to_modular_controllers(): void
    {
        $this->assertRouteAction('api.notices.admin.import.run', SettingsJobsController::class, 'runImportApi', ['POST']);
        $this->assertRouteAction('api.notices.admin.job-runs.index', SettingsJobsController::class, 'jobRunsApi', ['GET']);
        $this->assertRouteAction('api.notices.admin.lookup-overrides.index', SettingsDisplayController::class, 'lookupOverridesApi', ['GET']);
        $this->assertRouteAction('api.notices.admin.activity.system', SettingsActivityController::class, 'systemActivityLogsApi', ['GET']);
    }

    protected function assertRouteAction(string $name, string $controllerClass, string $method, array $httpMethods): void
    {
        $route = app('router')->getRoutes()->getByName($name);

        $this->assertNotNull($route, "Failed asserting route [{$name}] exists.");
        $this->assertSame($controllerClass . '@' . $method, $route->getActionName());

        foreach ($httpMethods as $httpMethod) {
            $this->assertContains($httpMethod, $route->methods(), "Failed asserting route [{$name}] allows [{$httpMethod}].");
        }
    }
}
