<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\LookupTableOverride;
use Dcplibrary\Notices\Models\StatusMessage;
use Dcplibrary\Notices\Models\UiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Settings display/messages controller.
 *
 * Handles lookup label overrides and UI message configuration.
 */
class SettingsDisplayController extends Controller
{
    /**
     * Display lookup table overrides management (Display Settings).
     *
     * @return \Illuminate\View\View
     */
    public function display(Request $request)
    {
        // Get available lookup tables with all records
        $availableTables = $this->getAvailableLookupTables();

        // Get all overrides as a keyed collection for easy lookup
        $overrides = LookupTableOverride::all()
            ->keyBy(function ($item) {
                return $item->table_name . '_' . $item->record_id;
            })
        ;

        // Merge overrides with all records
        $tables = [];
        foreach ($availableTables as $tableName => $tableData) {
            $tables[$tableName] = [
                'label'   => $tableData['label'],
                'records' => collect($tableData['records'])->map(function ($record) use ($tableName, $overrides) {
                    $key      = $tableName . '_' . $record['id'];
                    $override = $overrides->get($key);

                    return [
                        'id'             => $record['id'],
                        'original_label' => $record['label'],
                        'custom_label'   => $override?->custom_label ?? null,
                        'display_label'  => $override?->custom_label ?? $record['label'],
                        'is_enabled'     => $override?->is_enabled   ?? true,
                        'notes'          => $override?->notes        ?? null,
                        'has_override'   => null !== $override,
                        'override_id'    => $override?->id ?? null,
                    ];
                })->toArray(),
            ];
        }

        // Get active table from query param, default to first table
        $activeTable = $request->get('table', array_key_first($tables));

        return view('notices::settings.display', [
            'tables'       => $tables,
            'active_table' => $activeTable,
        ]);
    }

    /**
     * UI Messages settings (system-wide status/help text).
     */
    public function messages()
    {
        // Get all status messages grouped by status
        $formatMessage = function ($msg) {
            return [
                'id'               => $msg->id,
                'code'             => $msg->code,
                'status'           => $msg->status,
                'label'            => $msg->label,
                'message'          => $msg->message,
                'action'           => $msg->action,
                'source'           => $msg->source,
                'is_contact_issue' => $msg->is_contact_issue,
                'is_enabled'       => $msg->is_enabled,
            ];
        };

        $deliveredMessages = StatusMessage::getDelivered()->map($formatMessage)->values();
        $pendingMessages   = StatusMessage::getPending()->map($formatMessage)->values();
        $failedMessages    = StatusMessage::getFailed()->map($formatMessage)->values();

        return view('notices::settings.messages', [
            'delivered_messages' => $deliveredMessages,
            'pending_messages'   => $pendingMessages,
            'failed_messages'    => $failedMessages,
        ]);
    }

    /**
     * Update a UI message via AJAX.
     */
    public function updateUiMessage(Request $request)
    {
        $data = $request->validate([
            'key'     => 'required|string|max:100',
            'message' => 'required|string',
            'label'   => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
        ]);

        $row = UiMessage::updateOrCreate(
            ['key' => $data['key']],
            [
                'message' => $data['message'],
                'label'   => $data['label'] ?? ucwords(str_replace('_', ' ', $data['key'])),
                'notes'   => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $row,
        ]);
    }

    /**
     * Update a status message via AJAX.
     */
    public function updateStatusMessage(Request $request)
    {
        $data = $request->validate([
            'id'         => 'required|integer|exists:status_messages,id',
            'label'      => 'nullable|string|max:255',
            'message'    => 'required|string',
            'action'     => 'nullable|string',
            'is_enabled' => 'boolean',
        ]);

        $msg = StatusMessage::findOrFail($data['id']);
        $msg->update([
            'label'      => $data['label'] ?? $msg->label,
            'message'    => $data['message'],
            'action'     => $data['action']     ?? $msg->action,
            'is_enabled' => $data['is_enabled'] ?? $msg->is_enabled,
        ]);

        return response()->json([
            'success'        => true,
            'status_message' => $msg->fresh(),
        ]);
    }

    /**
     * Store a new lookup table override.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeLookupOverride(Request $request)
    {
        $request->validate([
            'table_name'   => 'required|string|max:100',
            'record_id'    => 'required|integer',
            'custom_label' => 'nullable|string|max:255',
            'is_enabled'   => 'required|boolean',
            'notes'        => 'nullable|string',
        ]);

        LookupTableOverride::updateOrCreate(
            [
                'table_name' => $request->table_name,
                'record_id'  => $request->record_id,
            ],
            [
                'custom_label' => $request->custom_label,
                'is_enabled'   => $request->is_enabled,
                'notes'        => $request->notes,
            ]
        );

        return redirect()
            ->route('settings.display')
            ->with('success', 'Display setting saved successfully.')
        ;
    }

    /**
     * Delete a lookup table override.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteLookupOverride(int $id)
    {
        $override = LookupTableOverride::findOrFail($id);
        $override->delete();

        return redirect()
            ->route('settings.display')
            ->with('success', 'Display setting deleted successfully.')
        ;
    }

    /**
     * Update custom label via AJAX.
     *
     * @return JsonResponse
     */
    public function updateLabel(Request $request)
    {
        $request->validate([
            'table_name'   => 'required|string|max:100',
            'record_id'    => 'required|integer',
            'custom_label' => 'nullable|string|max:255',
        ]);

        $override = LookupTableOverride::updateOrCreate(
            [
                'table_name' => $request->table_name,
                'record_id'  => $request->record_id,
            ],
            [
                'custom_label' => $request->custom_label ?: null,
            ]
        );

        return response()->json([
            'success'  => true,
            'override' => $override,
        ]);
    }

    /**
     * Toggle enabled status via AJAX.
     *
     * @return JsonResponse
     */
    public function toggleEnabled(Request $request)
    {
        $request->validate([
            'table_name' => 'required|string|max:100',
            'record_id'  => 'required|integer',
            'is_enabled' => 'required|boolean',
        ]);

        $override = LookupTableOverride::updateOrCreate(
            [
                'table_name' => $request->table_name,
                'record_id'  => $request->record_id,
            ],
            [
                'is_enabled' => $request->is_enabled,
            ]
        );

        return response()->json([
            'success'  => true,
            'override' => $override,
        ]);
    }

    /**
     * Update notes via AJAX.
     *
     * @return JsonResponse
     */
    public function updateNotes(Request $request)
    {
        $request->validate([
            'table_name' => 'required|string|max:100',
            'record_id'  => 'required|integer',
            'notes'      => 'nullable|string',
        ]);

        $override = LookupTableOverride::updateOrCreate(
            [
                'table_name' => $request->table_name,
                'record_id'  => $request->record_id,
            ],
            [
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'success'  => true,
            'override' => $override,
        ]);
    }

    /**
     * API endpoint (admin): List lookup table overrides.
     *
     * Query params:
     * - table_name (optional): notification_types | delivery_options
     */
    public function lookupOverridesApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_name' => 'nullable|string|in:notification_types,delivery_options',
        ]);

        $query = LookupTableOverride::query()->orderBy('table_name')->orderBy('record_id');
        if (!empty($data['table_name'])) {
            $query->where('table_name', $data['table_name']);
        }

        $overrides = $query->get()->map(fn (LookupTableOverride $o) => [
            'id'           => $o->id,
            'table_name'   => $o->table_name,
            'record_id'    => $o->record_id,
            'custom_label' => $o->custom_label,
            'is_enabled'   => (bool) $o->is_enabled,
            'notes'        => $o->notes,
            'created_at'   => $o->created_at?->toIso8601String(),
            'updated_at'   => $o->updated_at?->toIso8601String(),
        ])->values();

        return response()->json(['data' => $overrides]);
    }

    /**
     * API endpoint (admin): Create/update a lookup table override.
     *
     * Upserts on (table_name, record_id).
     */
    public function upsertLookupOverrideApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_name'   => 'required|string|in:notification_types,delivery_options',
            'record_id'    => 'required|integer|min:1',
            'custom_label' => 'nullable|string|max:255',
            'is_enabled'   => 'required|boolean',
            'notes'        => 'nullable|string',
        ]);

        $override = LookupTableOverride::updateOrCreate(
            [
                'table_name' => $data['table_name'],
                'record_id'  => $data['record_id'],
            ],
            [
                'custom_label' => $data['custom_label'] ?? null,
                'is_enabled'   => (bool) $data['is_enabled'],
                'notes'        => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $override->id,
                'table_name'   => $override->table_name,
                'record_id'    => $override->record_id,
                'custom_label' => $override->custom_label,
                'is_enabled'   => (bool) $override->is_enabled,
                'notes'        => $override->notes,
                'created_at'   => $override->created_at?->toIso8601String(),
                'updated_at'   => $override->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * API endpoint (admin): Delete a lookup table override by id.
     */
    public function deleteLookupOverrideApi(int $id): JsonResponse
    {
        $override = LookupTableOverride::query()->findOrFail($id);
        $override->delete();

        return response()->json([
            'success'    => true,
            'deleted_id' => $id,
        ]);
    }

    /**
     * API endpoint (admin): List UI messages (merged defaults + overrides).
     */
    public function uiMessagesApi(): JsonResponse
    {
        $defaults  = config('notices.ui_messages', []);
        $overrides = UiMessage::query()->get()->keyBy('key');

        $data = collect($defaults)->map(function ($message, $key) use ($overrides) {
            $override = $overrides->get($key);

            return [
                'key'             => $key,
                'label'           => $override?->label ?? ucwords(str_replace('_', ' ', $key)),
                'default_message' => $message,
                'custom_message'  => $override?->message,
                'notes'           => $override?->notes,
                'override_id'     => $override?->id,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * API endpoint (admin): Upsert a UI message override by key.
     */
    public function upsertUiMessageApi(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'label'   => 'nullable|string|max:255',
            'notes'   => 'nullable|string',
        ]);

        $row = UiMessage::updateOrCreate(
            ['key' => $key],
            [
                'message' => $data['message'],
                'label'   => $data['label'] ?? ucwords(str_replace('_', ' ', $key)),
                'notes'   => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'      => $row->id,
                'key'     => $row->key,
                'label'   => $row->label,
                'message' => $row->message,
                'notes'   => $row->notes,
            ],
        ]);
    }

    /**
     * API endpoint (admin): Delete a UI message override (revert to default).
     */
    public function deleteUiMessageApi(string $key): JsonResponse
    {
        UiMessage::query()->where('key', $key)->delete();

        return response()->json([
            'success'     => true,
            'deleted_key' => $key,
        ]);
    }

    /**
     * Get list of available lookup tables.
     */
    protected function getAvailableLookupTables(): array
    {
        return [
            'notification_types' => [
                'label'   => 'Notification Types',
                'records' => DB::table('notification_types')
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($r) => ['id' => $r->id, 'label' => $r->description])
                    ->toArray(),
            ],
            'delivery_options' => [
                'label'   => 'Delivery Options',
                'records' => DB::table('delivery_options')
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($r) => ['id' => $r->id, 'label' => $r->delivery_option])
                    ->toArray(),
            ],
        ];
    }
}
