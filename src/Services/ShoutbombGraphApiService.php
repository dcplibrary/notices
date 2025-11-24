<?php

namespace Dcplibrary\Notices\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShoutbombGraphApiService
{
    protected Client $client;
    protected array $config;
    protected string $baseUrl;

    public function __construct(array $graphConfig)
    {
        // Expect the "graph" portion of the notices integrations config
        $this->config = $graphConfig;
        $this->client = new Client();
        $this->baseUrl = "https://graph.microsoft.com/{$graphConfig['api_version']}";
    }

    /**
     * Get access token using client credentials flow
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'outlook_failure_reports_token';

        return Cache::remember($cacheKey, 3000, function () {
            $response = $this->client->post(
                "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token",
                [
                    'form_params' => [
                        'client_id' => $this->config['client_id'],
                        'client_secret' => $this->config['client_secret'],
                        'scope' => 'https://graph.microsoft.com/.default',
                        'grant_type' => 'client_credentials',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['access_token'];
        });
    }

    /**
     * Get messages from user's mailbox with filters
     */
    public function getMessages(?array $filters = []): array
    {
        // Ensure $filters is an array even if null is passed
        $filters = $filters ?? [];

        $token = $this->getAccessToken();
        $userEmail = $this->config['user_email'];

        // Build filter query
        $filterParts = [];

        if (!empty($filters['unread_only']) && $filters['unread_only']) {
            $filterParts[] = 'isRead eq false';
        }

        // Only add subject filter if value is provided and not empty/null
        if (isset($filters['subject_contains']) && $filters['subject_contains'] !== null && $filters['subject_contains'] !== '') {
            $filterParts[] = "contains(subject, '{$filters['subject_contains']}')";
        }

        $queryParams = [
            '$top' => $filters['max_emails'] ?? 50,
            '$orderby' => 'receivedDateTime DESC',
        ];

        if (!empty($filterParts)) {
            $queryParams['$filter'] = implode(' and ', $filterParts);
        }

        // Determine the endpoint
        $endpoint = "/users/{$userEmail}/messages";

        // If folder specified, get folder ID first and use it
        if (!empty($filters['folder'])) {
            $folderId = $this->getFolderId($userEmail, $filters['folder']);
            if ($folderId) {
                $endpoint = "/users/{$userEmail}/mailFolders/{$folderId}/messages";
            }
        }

        $url = $this->baseUrl . $endpoint . '?' . http_build_query($queryParams);

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['value'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch messages from Graph API', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);
            throw $e;
        }
    }

    /**
     * Get message by ID
     */
    public function getMessage(string $messageId): ?array
    {
        $token = $this->getAccessToken();
        $userEmail = $this->config['user_email'];

        $url = "{$this->baseUrl}/users/{$userEmail}/messages/{$messageId}";

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to fetch message from Graph API', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            return null;
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        $token = $this->getAccessToken();
        $userEmail = $this->config['user_email'];

        $url = "{$this->baseUrl}/users/{$userEmail}/messages/{$messageId}";

        try {
            $this->client->patch($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'isRead' => true,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark message as read', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            return false;
        }
    }

    /**
     * Move message to folder
     */
    public function moveMessage(string $messageId, string $folderName): bool
    {
        $token = $this->getAccessToken();
        $userEmail = $this->config['user_email'];

        $folderId = $this->getFolderId($userEmail, $folderName);
        if (!$folderId) {
            Log::warning("Folder '{$folderName}' not found");
            return false;
        }

        $url = "{$this->baseUrl}/users/{$userEmail}/messages/{$messageId}/move";

        try {
            $this->client->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'destinationId' => $folderId,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to move message', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'folder' => $folderName,
            ]);
            return false;
        }
    }

    /**
     * Get folder ID by name
     */
    protected function getFolderId(string $userEmail, string $folderName): ?string
    {
        $cacheKey = "outlook_folder_id_{$folderName}";

        return Cache::remember($cacheKey, 3600, function () use ($userEmail, $folderName) {
            $token = $this->getAccessToken();
            $url = "{$this->baseUrl}/users/{$userEmail}/mailFolders?\$filter=displayName eq '{$folderName}'";

            try {
                Log::debug("Searching for folder: {$folderName}", ['url' => $url]);

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::debug("Folder search results", [
                    'folder_name' => $folderName,
                    'found_count' => count($data['value'] ?? []),
                    'folders' => array_map(fn($f) => $f['displayName'] ?? 'unknown', $data['value'] ?? []),
                ]);

                $folderId = $data['value'][0]['id'] ?? null;

                if ($folderId) {
                    Log::info("Found folder ID for '{$folderName}': {$folderId}");
                } else {
                    Log::warning("Folder '{$folderName}' not found in mailbox");
                }

                return $folderId;
            } catch (\Exception $e) {
                Log::error('Failed to get folder ID', [
                    'error' => $e->getMessage(),
                    'folder' => $folderName,
                ]);
                return null;
            }
        });
    }

    /**
     * Get message body content (text or HTML)
     */
    public function getMessageBody(array $message, string $preferredType = 'text'): ?string
    {
        if (!isset($message['body'])) {
            return null;
        }

        $body = $message['body'];

        // If preferred type matches, return it
        if (($body['contentType'] ?? null) === $preferredType) {
            return $body['content'];
        }

        // If HTML is available and text preferred, we still get HTML
        // (you can use strip_tags to convert if needed)
        return $body['content'] ?? null;
    }
}
