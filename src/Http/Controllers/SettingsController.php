<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\DeliveryMethod;
use Dcplibrary\Notices\Models\NotificationSetting;
use Dcplibrary\Notices\Models\SyncLog;
use Dcplibrary\Notices\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Dcplibrary\Notices\Models\NotificationStatus;
use Dcplibrary\Notices\Models\NotificationType;

class SettingsController extends Controller
{
    protected SettingsManager $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
        
        // Only allow users in Computer Services group to access settings when
        // explicitly enabled. In test environments and by default this check is
        // disabled so routes remain accessible.
        if (config('notices.dashboard.require_settings_auth', false)) {
            $this->middleware(function ($request, $next) {
                if (!Auth::check() || !method_exists(Auth::user(), 'inGroup') || !Auth::user()->inGroup('Computer Services')) {
                    abort(403, 'Access denied. Settings are only available to Computer Services staff.');
                }
                return $next($request);
            });
        }
    }

    /**
     * Display all editable settings grouped by group.
     */
    public function index()
    {
        $settings = NotificationSetting::global()
            ->editable()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return view('notices::settings.index', compact('settings'));
    }

    /**
     * Show a specific setting.
     */
    public function show($id)
    {
        $setting = NotificationSetting::findOrFail($id);

        if (!$setting->is_public && !$this->canViewSensitive()) {
            abort(403, 'You do not have permission to view this setting.');
        }

        return view('notices::settings.show', compact('setting'));
    }

    /**
     * Show the edit form for a setting.
     */
    public function edit($id)
    {
        $setting = NotificationSetting::findOrFail($id);

        if (!$setting->is_editable) {
            abort(403, 'This setting cannot be edited.');
        }

        return view('notices::settings.edit', compact('setting'));
    }

    /**
     * Update a setting value.
     */
    public function update(Request $request, $id)
    {
        $setting = NotificationSetting::findOrFail($id);

        if (!$setting->is_editable) {
            return back()->withErrors(['error' => 'This setting cannot be edited.']);
        }

        // Build validation rules
        $rules = ['value' => 'required'];

        if (!empty($setting->validation_rules)) {
            $rules['value'] = array_merge(
                (array) $rules['value'],
                (array) $setting->validation_rules
            );
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Update the setting using SettingsManager
        $this->settingsManager->set(
            $setting->full_key,
            $request->input('value'),
            $setting->scope,
            $setting->scope_id,
            $this->getCurrentUser()
        );

        return redirect()
            ->route('notices.settings.index')
            ->with('success', "Setting '{$setting->full_key}' updated successfully.");
    }

    /**
     * Delete a setting (revert to config default).
     */
    public function destroy($id)
    {
        $setting = NotificationSetting::findOrFail($id);

        if (!$setting->is_editable) {
            return back()->withErrors(['error' => 'This setting cannot be deleted.']);
        }

        $key = $setting->full_key;

        $this->settingsManager->delete(
            $key,
            $setting->scope,
            $setting->scope_id
        );

        return redirect()
            ->route('notices.settings.index')
            ->with('success', "Setting '{$key}' deleted. Will use config default.");
    }

    /**
     * Display scoped settings (e.g., branch-specific).
     */
    public function scoped(Request $request)
    {
        $scope = $request->input('scope');
        $scopeId = $request->input('scope_id');

        if (!$scope || !$scopeId) {
            abort(400, 'Scope and scope_id are required.');
        }

        $settings = NotificationSetting::forScope($scope, $scopeId)
            ->editable()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return view('notices::settings.scoped', compact('settings', 'scope', 'scopeId'));
    }

    /**
     * Create a new scoped setting.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scope' => 'nullable|string',
            'scope_id' => 'nullable|string',
            'key' => 'required|string',
            'value' => 'required',
            'type' => 'required|in:string,int,integer,bool,boolean,float,decimal,json,array,encrypted',
            'description' => 'nullable|string',
            'is_editable' => 'boolean',
            'is_sensitive' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $setting = new NotificationSetting();
        $setting->scope = $request->input('scope');
        $setting->scope_id = $request->input('scope_id');

        [$group, $key] = $this->parseKey($request->input('key'));
        $setting->group = $group;
        $setting->key = $key;
        $setting->type = $request->input('type');
        $setting->description = $request->input('description');
        $setting->is_editable = $request->input('is_editable', true);
        $setting->is_sensitive = $request->input('is_sensitive', false);
        $setting->updated_by = $this->getCurrentUser();

        $setting->setTypedValue($request->input('value'));
        $setting->save();

        return redirect()
            ->route('notices.settings.index')
            ->with('success', "Setting '{$setting->full_key}' created successfully.");
    }

    /**
     * Check if user can view sensitive settings.
     */
    protected function canViewSensitive(): bool
    {
        // Package default: settings are viewable wherever the routes are
        // exposed. Applications can override this controller if stricter
        // authorization is needed.
        return true;
    }

    /**
     * Get current user identifier.
     */
    protected function getCurrentUser(): string
    {
        if (Auth::check()) {
            return Auth::user()->email ?? Auth::user()->name ?? 'user_' . Auth::id();
        }

        return 'system';
    }

    /**
     * Parse a key into group and setting name.
     */
    protected function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) === 1) {
            return ['general', $parts[0]];
        }

        return $parts;
    }

    /**
     * Display reference data management page.
     */
    public function referenceData()
    {
        $notificationTypes = NotificationType::ordered()->get();
        $deliveryMethods = DeliveryMethod::ordered()->get();
        $notificationStatuses = NotificationStatus::ordered()->get();

        return view('notices::settings.reference-data', compact(
            'notificationTypes',
            'deliveryMethods',
            'notificationStatuses'
        ));
    }

    /**
     * Update notification type settings.
     */
    public function updateNotificationType(Request $request, $id)
    {
        $type = NotificationType::findOrFail($id);
        
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'display_order' => 'required|integer|min:0',
            'label' => 'nullable|string|max:255',
        ]);

        $type->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Update delivery method settings.
     */
    public function updateDeliveryMethod(Request $request, $id)
    {
        $method = DeliveryMethod::findOrFail($id);
        
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'display_order' => 'required|integer|min:0',
            'label' => 'nullable|string|max:255',
        ]);

        $method->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Update notification status settings.
     */
    public function updateNotificationStatus(Request $request, $id)
    {
        $status = NotificationStatus::findOrFail($id);
        
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'display_order' => 'required|integer|min:0',
            'label' => 'nullable|string|max:255',
        ]);

        $status->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Display sync/import management page.
     */
    public function sync()
    {
        // Get last sync for each operation type
        $lastSyncAll = SyncLog::latestFor('sync_all');
        $lastPolaris = SyncLog::latestFor('import_polaris');
        $lastShoutbomb = SyncLog::latestFor('import_shoutbomb');
        $lastShoutbombSubmissions = SyncLog::latestFor('import_shoutbomb_submissions');
        $lastShoutbombReports = SyncLog::latestFor('import_shoutbomb_reports');
        $lastAggregate = SyncLog::latestFor('aggregate');

        // Get recent sync history
        $recentSyncs = SyncLog::recent(10);

        // Compute integration availability
        $integrationEnabled = (bool) ($this->settingsManager->get('integrations.shoutbomb_reports.enabled')
            ?? config('notices.integrations.shoutbomb_reports.enabled', false));

        return view('notices::settings.sync', compact(
            'lastSyncAll',
            'lastPolaris',
            'lastShoutbomb',
            'lastShoutbombSubmissions',
            'lastShoutbombReports',
            'lastAggregate',
            'recentSyncs',
            'integrationEnabled'
        ));
    }

    /**
     * Display Export & Backup page.
     */
    public function export()
    {
        return view('notices::settings.export');
    }

    /**
     * Run phone normalization on existing data via Artisan command.
     */
    public function normalizePhones(Request $request)
    {
        $dry = $request->boolean('dry_run', false);
        $fast = $request->boolean('fast_sql', true);

        $options = [];
        if ($dry) $options['--dry-run'] = true;
        if ($fast) $options['--fast-sql'] = true;

        try {
            Artisan::call('notices:normalize-phones', $options);
            $output = Artisan::output();
            return redirect()->route('notices.settings.index')
                ->with('success', 'Phone normalization ' . ($dry ? '(dry run) ' : '') . 'completed.')
                ->with('details', $output);
        } catch (\Throwable $e) {
            return redirect()->route('notices.settings.index')
                ->withErrors(['error' => 'Normalization failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle the Shoutbomb Reports integration (DB-backed setting).
     */
    public function toggleShoutbombReports(Request $request)
    {
        $enabled = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);

        // Ensure the setting exists with proper type on first toggle
        $existing = NotificationSetting::global()
            ->where('group', 'integrations')
            ->where('key', 'shoutbomb_reports.enabled')
            ->first();

        if (!$existing) {
            $setting = new NotificationSetting();
            $setting->group = 'integrations';
            $setting->key = 'shoutbomb_reports.enabled';
            $setting->type = 'boolean';
            $setting->description = 'Enable using dcplibrary/shoutbomb-reports data to infer delivery failures (absence implies success when submitted).';
            $setting->is_public = true;
            $setting->is_editable = true;
            $setting->is_sensitive = false;
            $setting->updated_by = $this->getCurrentUser();
            $setting->setTypedValue($enabled);
            $setting->save();
        } else {
            $this->settingsManager->set(
                'integrations.shoutbomb_reports.enabled',
                $enabled,
                null,
                null,
                $this->getCurrentUser()
            );
        }

        return redirect()
            ->route('notices.settings.index')
            ->with('success', 'Shoutbomb Reports integration ' . ($enabled ? 'enabled' : 'disabled') . '.');
    }
}
