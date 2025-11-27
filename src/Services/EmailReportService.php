<?php

namespace Dcplibrary\Notices\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class EmailReportService
{
    protected $connection;

    protected $config;

    public function __construct()
    {
        $this->config = config('notices.email_reports');
    }

    /**
     * Connect to the email server via IMAP.
     */
    public function connect(): bool
    {
        if (!$this->config['enabled']) {
            Log::info('Email reports are disabled in configuration');

            return false;
        }

        try {
            $host = $this->config['connection']['host'];
            $port = $this->config['connection']['port'];
            $encryption = $this->config['connection']['encryption'];
            $mailbox = $this->config['mailbox'];

            // Build IMAP connection string
            $connectionString = sprintf(
                '{%s:%d/imap/%s}%s',
                $host,
                $port,
                $encryption,
                $mailbox
            );

            $this->connection = imap_open(
                $connectionString,
                $this->config['connection']['username'],
                $this->config['connection']['password']
            );

            if (!$this->connection) {
                throw new Exception('Failed to connect to email server: ' . imap_last_error());
            }

            Log::info('Successfully connected to email server', [
                'host' => $host,
                'mailbox' => $mailbox,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Email connection failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disconnect from email server.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Fetch unread emails matching Shoutbomb report criteria.
     */
    public function fetchShoutbombReports(): array
    {
        if (!$this->connection) {
            throw new Exception('Not connected to email server. Call connect() first.');
        }

        $reports = [];

        try {
            // Search for unseen emails from Shoutbomb
            $criteria = 'UNSEEN FROM "' . $this->config['from_address'] . '"';
            $emailIds = imap_search($this->connection, $criteria);

            if (!$emailIds) {
                Log::info('No unread Shoutbomb emails found');

                return $reports;
            }

            Log::info('Found ' . count($emailIds) . ' unread Shoutbomb emails');

            foreach ($emailIds as $emailId) {
                $report = $this->fetchEmail($emailId);
                if ($report) {
                    $reports[] = $report;
                }
            }
        } catch (Exception $e) {
            Log::error('Error fetching emails', [
                'error' => $e->getMessage(),
            ]);
        }

        return $reports;
    }

    /**
     * Fetch a specific email by ID.
     */
    protected function fetchEmail(int $emailId): ?array
    {
        try {
            $header = imap_headerinfo($this->connection, $emailId);
            $body = imap_body($this->connection, $emailId);

            // Determine report type from subject
            $subject = $header->subject ?? '';
            $reportType = $this->determineReportType($subject);

            if (!$reportType) {
                Log::debug('Skipping email - not a recognized Shoutbomb report', [
                    'subject' => $subject,
                ]);

                return null;
            }

            $report = [
                'email_id' => $emailId,
                'subject' => $subject,
                'date' => date('Y-m-d H:i:s', strtotime($header->date)),
                'from' => $header->fromaddress ?? '',
                'body' => $this->cleanEmailBody($body),
                'report_type' => $reportType,
            ];

            Log::info('Fetched email report', [
                'type' => $reportType,
                'subject' => $subject,
                'date' => $report['date'],
            ]);

            return $report;
        } catch (Exception $e) {
            Log::error('Error fetching email ID ' . $emailId, [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determine report type from subject line.
     */
    protected function determineReportType(string $subject): ?string
    {
        // Invalid patron phone number report (opt-outs and invalid)
        if (stripos($subject, 'Invalid patron phone number') !== false) {
            return 'email_invalid_optout';
        }

        // Undelivered voice notices
        if (stripos($subject, 'Voice notices that were not delivered') !== false) {
            return 'email_undelivered_voice';
        }

        return null;
    }

    /**
     * Clean email body (remove HTML, extra whitespace, etc.).
     */
    protected function cleanEmailBody(string $body): string
    {
        // Decode quoted-printable if needed
        if (strpos($body, '=') !== false && strpos($body, 'Content-Transfer-Encoding: quoted-printable') !== false) {
            $body = quoted_printable_decode($body);
        }

        // Strip HTML tags
        $body = strip_tags($body);

        // Normalize line endings
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        // Remove excessive whitespace
        $body = preg_replace('/\n{3,}/', "\n\n", $body);

        return trim($body);
    }

    /**
     * Mark an email as read.
     */
    public function markAsRead(int $emailId): bool
    {
        if (!$this->connection) {
            return false;
        }

        try {
            imap_setflag_full($this->connection, (string) $emailId, '\\Seen');
            Log::info('Marked email as read', ['email_id' => $emailId]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to mark email as read', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Move an email to a different folder.
     */
    public function moveToFolder(int $emailId, string $folder): bool
    {
        if (!$this->connection) {
            return false;
        }

        try {
            imap_mail_move($this->connection, (string) $emailId, $folder);
            imap_expunge($this->connection);
            Log::info('Moved email to folder', [
                'email_id' => $emailId,
                'folder' => $folder,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to move email', [
                'email_id' => $emailId,
                'folder' => $folder,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test connection to email server.
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'details' => [],
        ];

        try {
            if (!extension_loaded('imap')) {
                throw new Exception('PHP IMAP extension is not installed');
            }

            if ($this->connect()) {
                $result['success'] = true;
                $result['message'] = 'Successfully connected to email server';
                $result['details'] = [
                    'host' => $this->config['connection']['host'],
                    'port' => $this->config['connection']['port'],
                    'mailbox' => $this->config['mailbox'],
                    'encryption' => $this->config['connection']['encryption'],
                ];

                // Count total messages
                $check = imap_check($this->connection);
                $result['details']['total_messages'] = $check->Nmsgs ?? 0;

                $this->disconnect();
            } else {
                throw new Exception('Connection failed: ' . imap_last_error());
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get email statistics.
     */
    public function getStats(): array
    {
        if (!$this->connection) {
            throw new Exception('Not connected to email server');
        }

        $check = imap_check($this->connection);

        return [
            'total_messages' => $check->Nmsgs ?? 0,
            'recent' => $check->Recent ?? 0,
            'unread' => $check->Unseen ?? 0,
        ];
    }
}
