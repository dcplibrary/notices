<?php

namespace Dcplibrary\Notifications\Http\Controllers;

use Dcplibrary\Notifications\Models\NotificationSetting;
use Dcplibrary\Notifications\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    protected SettingsManager $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
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

        return view('notifications::settings.index', compact('settings'));
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

        return view('notifications::settings.show', compact('setting'));
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

        return view('notifications::settings.edit', compact('setting'));
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
            ->route('notifications.settings.index')
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
            ->route('notifications.settings.index')
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

        return view('notifications::settings.scoped', compact('settings', 'scope', 'scopeId'));
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
            ->route('notifications.settings.index')
            ->with('success', "Setting '{$setting->full_key}' created successfully.");
    }

    /**
     * Check if user can view sensitive settings.
     */
    protected function canViewSensitive(): bool
    {
        // Implement your authorization logic here
        // For example: return Auth::user()->can('view-sensitive-settings');
        return Auth::check();
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
}
