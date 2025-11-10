<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Service for exporting notice data to various formats.
 */
class NoticeExportService
{
    protected NoticeVerificationService $verificationService;

    public function __construct(NoticeVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Export verification results to CSV.
     *
     * @param Collection<NotificationLog> $notices
     * @return string CSV content
     */
    public function exportVerificationToCSV(Collection $notices): string
    {
        $csv = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($csv, [
            'Notice ID',
            'Date',
            'Patron Barcode',
            'Patron Name',
            'Notice Type',
            'Delivery Method',
            'Contact',
            'Item Barcode',
            'Title',
            'Verification Status',
            'Created',
            'Submitted',
            'Verified',
            'Delivered',
            'Failure Reason',
        ]);

        // Write data rows
        foreach ($notices as $notice) {
            $verification = $this->verificationService->verify($notice);

            fputcsv($csv, [
                $notice->id,
                $notice->notification_date->format('Y-m-d H:i:s'),
                $notice->patron_barcode,
                $notice->patron_name ?? '',
                $this->getNoticeTypeName($notice->notification_type_id),
                $this->getDeliveryMethodName($notice->delivery_option_id),
                $this->getContactValue($notice),
                $notice->item_barcode ?? '',
                $notice->title ?? '',
                ucfirst($verification->overall_status),
                $verification->created ? 'Yes' : 'No',
                $verification->submitted ? 'Yes' : 'No',
                $verification->verified ? 'Yes' : 'No',
                $verification->delivered ? 'Yes' : 'No',
                $verification->failure_reason ?? '',
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * Export failures to CSV.
     *
     * @param array $failures
     * @return string CSV content
     */
    public function exportFailuresToCSV(array $failures): string
    {
        $csv = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($csv, [
            'ID',
            'Date',
            'Patron Barcode',
            'Phone',
            'Status',
            'Failure Reason',
            'Message Type',
            'Carrier',
        ]);

        // Write data rows
        foreach ($failures as $failure) {
            fputcsv($csv, [
                $failure['id'] ?? '',
                isset($failure['sent_date']) ? Carbon::parse($failure['sent_date'])->format('Y-m-d H:i:s') : '',
                $failure['patron_barcode'] ?? '',
                $failure['phone_number'] ?? '',
                $failure['status'] ?? '',
                $failure['failure_reason'] ?? '',
                $failure['message_type'] ?? '',
                $failure['carrier'] ?? '',
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * Export patron history to CSV.
     *
     * @param array $results Verification results
     * @return string CSV content
     */
    public function exportPatronHistoryToCSV(array $results): string
    {
        $csv = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($csv, [
            'Notice ID',
            'Date',
            'Notice Type',
            'Delivery Method',
            'Item Barcode',
            'Title',
            'Status',
            'Created',
            'Submitted',
            'Verified',
            'Delivered',
            'Failure Reason',
        ]);

        // Write data rows
        foreach ($results as $result) {
            $notice = $result['notice'];
            $verification = $result['verification'];

            fputcsv($csv, [
                $notice->id,
                $notice->notification_date->format('Y-m-d H:i:s'),
                $this->getNoticeTypeName($notice->notification_type_id),
                $this->getDeliveryMethodName($notice->delivery_option_id),
                $notice->item_barcode ?? '',
                $notice->title ?? '',
                ucfirst($verification->overall_status),
                $verification->created ? 'Yes' : 'No',
                $verification->submitted ? 'Yes' : 'No',
                $verification->verified ? 'Yes' : 'No',
                $verification->delivered ? 'Yes' : 'No',
                $verification->failure_reason ?? '',
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * Get notice type name.
     */
    protected function getNoticeTypeName(int $id): string
    {
        $types = config('notices.notification_types', []);
        return $types[$id] ?? "Unknown ($id)";
    }

    /**
     * Get delivery method name.
     */
    protected function getDeliveryMethodName(int $id): string
    {
        $methods = config('notices.delivery_options', []);
        return $methods[$id] ?? "Unknown ($id)";
    }

    /**
     * Get contact value (phone or email).
     */
    protected function getContactValue(NotificationLog $notice): string
    {
        return match($notice->delivery_option_id) {
            3, 8 => $notice->phone ?? '',     // Voice or SMS
            2 => $notice->email ?? '',         // Email
            default => '',
        };
    }
}
