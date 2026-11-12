<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Models\LookupTableOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Backward-compatibility facade for the pre-modular SettingsController.
 *
 * Routes now target focused settings controllers directly. This facade remains
 * to support custom host-app routes that may still reference legacy methods.
 */
class SettingsController extends Controller
{
    /**
     * Display settings dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get counts for various settings
        $lookupOverrideCount = LookupTableOverride::count();
        $disabledLookupCount = LookupTableOverride::where('is_enabled', false)->count();

        // Get API token count if Sanctum is available and the table exists
        $apiTokenCount = 0;
        $user          = auth()->user();

        if (
            $user
            && class_exists(PersonalAccessToken::class)
            && Schema::hasTable('personal_access_tokens')
        ) {
            if (method_exists($user, 'tokens')) {
                $apiTokenCount = $user->tokens()->count();
            } else {
                $apiTokenCount = PersonalAccessToken::where('tokenable_type', get_class($user))
                    ->where('tokenable_id', $user->id)
                    ->count()
                ;
            }
        }

        // Job runs (queued/running) count for Settings dashboard
        $activeJobRunCount = JobRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->count()
        ;

        return view('notices::settings.index', [
            'lookup_override_count' => $lookupOverrideCount,
            'disabled_lookup_count' => $disabledLookupCount,
            'api_token_count'       => $apiTokenCount,
            'active_job_run_count'  => $activeJobRunCount,
        ]);
    }

    public function jobs()
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__);
    }

    public function maintenance()
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__);
    }

    public function maintenanceCleanup()
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__);
    }

    public function runCleanupImportLogs(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function configBackup()
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__);
    }

    public function createConfigBackup(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreConfigFromUpload(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreConfigFromLocal(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function dataBackup()
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__);
    }

    public function createDataBackup(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreDataFromUpload(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreDataFromLocal(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreCombinedFromUpload(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function restoreCombinedFromLocal(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function createBackup(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function createCombinedBackup(Request $request)
    {
        return $this->forward(SettingsMaintenanceBackupController::class, __FUNCTION__, [$request]);
    }

    public function display(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function messages()
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__);
    }

    public function updateUiMessage(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function storeLookupOverride(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function deleteLookupOverride(int $id)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$id]);
    }

    public function updateLabel(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function toggleEnabled(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function updateNotes(Request $request)
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function apiTokens()
    {
        return $this->forward(SettingsApiTokensController::class, __FUNCTION__);
    }

    public function createApiToken(Request $request)
    {
        return $this->forward(SettingsApiTokensController::class, __FUNCTION__, [$request]);
    }

    public function revokeApiToken(int $id)
    {
        return $this->forward(SettingsApiTokensController::class, __FUNCTION__, [$id]);
    }

    public function runImportApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function runImportConfirmationsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function runProcessOrphanedApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function runIntegrityCheckApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function runCleanupImportLogsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function jobRunsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request]);
    }

    public function jobRunApi(int $id): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$id]);
    }

    public function jobRunEventsApi(Request $request, int $id): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$request, $id]);
    }

    public function cancelJobRunApi(int $id): JsonResponse
    {
        return $this->forward(SettingsJobsController::class, __FUNCTION__, [$id]);
    }

    public function lookupOverridesApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function upsertLookupOverrideApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request]);
    }

    public function deleteLookupOverrideApi(int $id): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$id]);
    }

    public function uiMessagesApi(): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__);
    }

    public function upsertUiMessageApi(Request $request, string $key): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$request, $key]);
    }

    public function deleteUiMessageApi(string $key): JsonResponse
    {
        return $this->forward(SettingsDisplayController::class, __FUNCTION__, [$key]);
    }

    public function notificationActivityLogsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsActivityController::class, __FUNCTION__, [$request]);
    }

    public function userActivityLogsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsActivityController::class, __FUNCTION__, [$request]);
    }

    public function systemActivityLogsApi(Request $request): JsonResponse
    {
        return $this->forward(SettingsActivityController::class, __FUNCTION__, [$request]);
    }

    protected function forward(string $controllerClass, string $method, array $parameters = [])
    {
        return app($controllerClass)->{$method}(...$parameters);
    }
}
