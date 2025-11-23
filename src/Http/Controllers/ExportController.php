<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DeliveryMethod;
use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Dcplibrary\Notices\Database\Seeders\NotificationStatus;
use Dcplibrary\Notices\Database\Seeders\NotificationType;

class ExportController extends Controller
{
    public function __construct()
    {
        // Admin only
        $this->middleware(function ($request, $next) {
            if (!Auth::check() || !Auth::user()->inGroup('Computer Services')) {
                abort(403, 'Unauthorized');
            }
            return $next($request);
        });
    }

    /**
     * Export reference data configuration as JSON
     */
    public function exportReferenceData(): Response
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => Auth::user()->email ?? Auth::user()->name ?? 'Unknown',
            'notification_types' => NotificationType::orderBy('notification_type_id')->get()->map(function ($type) {
                return [
                    'id' => $type->notification_type_id,
                    'description' => $type->description,
                    'label' => $type->label,
                    'enabled' => $type->enabled,
                    'display_order' => $type->display_order,
                ];
            }),
            'delivery_methods' => DeliveryMethod::orderBy('delivery_option_id')->get()->map(function ($method) {
                return [
                    'id' => $method->delivery_option_id,
                    'delivery_option' => $method->delivery_option,
                    'description' => $method->description,
                    'label' => $method->label,
                    'enabled' => $method->enabled,
                    'display_order' => $method->display_order,
                ];
            }),
            'notification_statuses' => NotificationStatus::orderBy('notification_status_id')->get()->map(function ($status) {
                return [
                    'id' => $status->notification_status_id,
                    'description' => $status->description,
                    'label' => $status->label,
                    'category' => $status->category,
                    'enabled' => $status->enabled,
                    'display_order' => $status->display_order,
                ];
            }),
        ];

        $filename = 'notices-reference-data-' . now()->format('Y-m-d-His') . '.json';

        return response()
            ->json($data, 200, [], JSON_PRETTY_PRINT)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export reference data configuration as SQL
     */
    public function exportReferenceDataSql(): Response
    {
        $sql = "-- Notices Package Reference Data Export\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- By: " . (Auth::user()->email ?? Auth::user()->name ?? 'Unknown') . "\n\n";

        // Notification Types
        $sql .= "-- Notification Types\n";
        $types = NotificationType::orderBy('notification_type_id')->get();
        foreach ($types as $type) {
            $sql .= sprintf(
                "UPDATE notification_types SET enabled = %d, display_order = %d, label = %s WHERE notification_type_id = %d;\n",
                $type->enabled ? 1 : 0,
                $type->display_order,
                $type->label ? "'" . addslashes($type->label) . "'" : 'NULL',
                $type->notification_type_id
            );
        }

        // Delivery Methods
        $sql .= "\n-- Delivery Methods\n";
        $methods = DeliveryMethod::orderBy('delivery_option_id')->get();
        foreach ($methods as $method) {
            $sql .= sprintf(
                "UPDATE delivery_methods SET enabled = %d, display_order = %d, label = %s WHERE delivery_option_id = %d;\n",
                $method->enabled ? 1 : 0,
                $method->display_order,
                $method->label ? "'" . addslashes($method->label) . "'" : 'NULL',
                $method->delivery_option_id
            );
        }

        // Notification Statuses
        $sql .= "\n-- Notification Statuses\n";
        $statuses = NotificationStatus::orderBy('notification_status_id')->get();
        foreach ($statuses as $status) {
            $sql .= sprintf(
                "UPDATE notification_statuses SET enabled = %d, display_order = %d, label = %s, category = '%s' WHERE notification_status_id = %d;\n",
                $status->enabled ? 1 : 0,
                $status->display_order,
                $status->label ? "'" . addslashes($status->label) . "'" : 'NULL',
                $status->category,
                $status->notification_status_id
            );
        }

        $filename = 'notices-reference-data-' . now()->format('Y-m-d-His') . '.sql';

        return response($sql)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export notification logs as CSV by date range
     */
    public function exportNotificationData(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,json',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $notifications = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
            ->orderBy('notification_date')
            ->get();

        if ($validated['format'] === 'json') {
            return $this->exportAsJson($notifications, $startDate, $endDate);
        }

        return $this->exportAsCsv($notifications, $startDate, $endDate);
    }

    /**
     * Export as CSV
     */
    private function exportAsCsv($notifications, $startDate, $endDate): Response
    {
        $filename = sprintf(
            'notices-data-%s-to-%s.csv',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $handle = fopen('php://memory', 'r+');

        // Header row
        fputcsv($handle, [
            'ID',
            'Date',
            'Patron Barcode',
            'Patron ID',
            'Type',
            'Delivery Method',
            'Delivery String',
            'Status',
            'Detailed Status',
            'Total Items',
            'Holds',
            'Overdues',
            'Created At',
        ]);

        // Data rows
        foreach ($notifications as $notification) {
            fputcsv($handle, [
                $notification->id,
                $notification->notification_date->format('Y-m-d H:i:s'),
                $notification->patron_barcode,
                $notification->patron_id,
                $notification->notification_type_name,
                $notification->delivery_method_name,
                $notification->delivery_string,
                $notification->status,
                $notification->notification_status_name,
                $notification->total_items,
                $notification->holds_count,
                $notification->overdues_count,
                $notification->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export as JSON
     */
    private function exportAsJson($notifications, $startDate, $endDate): Response
    {
        $filename = sprintf(
            'notices-data-%s-to-%s.json',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $data = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => Auth::user()->email ?? Auth::user()->name ?? 'Unknown',
            'date_range' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
            'total_records' => $notifications->count(),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'date' => $notification->notification_date->toIso8601String(),
                    'patron_barcode' => $notification->patron_barcode,
                    'patron_id' => $notification->patron_id,
                    'type' => [
                        'id' => $notification->notification_type_id,
                        'name' => $notification->notification_type_name,
                    ],
                    'delivery' => [
                        'method_id' => $notification->delivery_option_id,
                        'method_name' => $notification->delivery_method_name,
                        'string' => $notification->delivery_string,
                    ],
                    'status' => $notification->status,
                    'detailed_status' => [
                        'id' => $notification->notification_status_id,
                        'name' => $notification->notification_status_name,
                    ],
                    'items' => [
                        'total' => $notification->total_items,
                        'holds' => $notification->holds_count,
                        'overdues' => $notification->overdues_count,
                        'overdues_2nd' => $notification->overdues_2nd_count,
                        'overdues_3rd' => $notification->overdues_3rd_count,
                    ],
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            }),
        ];

        return response()
            ->json($data, 200, [], JSON_PRETTY_PRINT)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Generate database backup SQL dump
     */
    public function exportDatabaseBackup(Request $request): Response
    {
        $validated = $request->validate([
            'tables' => 'required|array',
            'tables.*' => 'required|string|in:notification_logs,daily_notification_summaries,sync_logs,notification_types,delivery_methods,notification_statuses',
        ]);

        $sql = "-- Notices Package Database Backup\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- By: " . (Auth::user()->email ?? Auth::user()->name ?? 'Unknown') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($validated['tables'] as $table) {
            $sql .= $this->generateTableBackup($table);
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        $filename = 'notices-backup-' . now()->format('Y-m-d-His') . '.sql';

        return response($sql)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Generate backup SQL for a specific table
     */
    private function generateTableBackup(string $table): string
    {
        $sql = "-- Table: {$table}\n";
        $sql .= "TRUNCATE TABLE `{$table}`;\n\n";

        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            $sql .= "-- No data in table\n\n";
            return $sql;
        }

        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $values[] = "'" . addslashes($value) . "'";
                }
            }

            $columns = array_keys((array)$row);
            $sql .= sprintf(
                "INSERT INTO `%s` (`%s`) VALUES (%s);\n",
                $table,
                implode('`, `', $columns),
                implode(', ', $values)
            );
        }

        $sql .= "\n";

        return $sql;
    }
}
