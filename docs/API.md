# API Documentation

The Polaris Notifications package provides a RESTful API for accessing notification data. The API can be used to build custom dashboards or integrate with other applications.

## Configuration

API routes are enabled by default but can be disabled in config:

```php
// config/notices.php
'api' => [
    'enabled' => true,  // Set to false to disable API
    'route_prefix' => 'api/notifications',
    'middleware' => ['api', 'auth:sanctum'],
    'rate_limit' => 60,
    'per_page' => 20,
    'max_per_page' => 100,
],
```

## Authentication

API routes use Laravel Sanctum authentication by default. Ensure you have Sanctum installed and configured.

## Base URL

All API endpoints are prefixed with `/api/notices` by default.

---

## Notification Endpoints

### List Notifications

```
GET /api/notices/logs
```

Retrieve a paginated list of notification logs.

**Query Parameters:**
- `per_page` (int): Number of results per page (default: 20, max: 100)
- `start_date` (date): Filter by start date (Y-m-d format)
- `end_date` (date): Filter by end date (Y-m-d format)
- `days` (int): Filter by recent days (alternative to date range)
- `type_id` (int): Filter by notification type ID
- `delivery_id` (int): Filter by delivery method ID
- `status_id` (int): Filter by status ID
- `patron_id` (int): Filter by patron ID
- `successful` (boolean): Filter successful notifications only
- `failed` (boolean): Filter failed notifications only
- `sort_by` (string): Sort field (default: notification_date)
- `sort_dir` (string): Sort direction (asc/desc, default: desc)

**Example Request:**
```bash
curl -X GET "https://yourapp.com/api/notices/logs?days=7&successful=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "polaris_log_id": 12345,
      "patron_id": 100,
      "patron_barcode": "21234567890",
      "notification_date": "2025-11-08T10:30:00Z",
      "notification_type": {
        "id": 4,
        "name": "Holds"
      },
      "delivery_method": {
        "id": 2,
        "name": "Email"
      },
      "status": {
        "id": 12,
        "name": "Success"
      },
      "items": {
        "holds": 2,
        "overdues": 0,
        "total": 2
      }
    }
  ],
  "links": {},
  "meta": {
    "current_page": 1,
    "total": 100
  }
}
```

![API Response Example](images/api-response.png)
*Example API request and response in Postman*

### Get Single Notification

```
GET /api/notices/logs/{id}
```

Retrieve a specific notification by ID.

### Get Notification Statistics

```
GET /api/notices/logs/stats
```

Get aggregated statistics for notifications.

**Query Parameters:**
- `start_date` (date): Filter by start date
- `end_date` (date): Filter by end date
- `days` (int): Filter by recent days

**Example Response:**
```json
{
  "total": 1000,
  "successful": 950,
  "failed": 50,
  "success_rate": 95.0,
  "failure_rate": 5.0
}
```

---

## Summary Endpoints

### List Summaries

```
GET /api/notices/summaries
```

Retrieve daily notification summaries.

**Query Parameters:**
- `per_page` (int): Number of results per page
- `start_date` (date): Filter by start date
- `end_date` (date): Filter by end date
- `type_id` (int): Filter by notification type
- `delivery_id` (int): Filter by delivery method

### Get Summary Totals

```
GET /api/notices/summaries/totals
```

Get aggregated totals for a date range.

**Example Response:**
```json
{
  "total_sent": 5000,
  "total_success": 4750,
  "total_failed": 250,
  "total_holds": 3000,
  "total_overdues": 2000,
  "avg_success_rate": 95.0
}
```

### Get Breakdown by Type

```
GET /api/notices/summaries/by-type
```

Get summary breakdown by notification type.

**Example Response:**
```json
[
  {
    "notification_type_id": 4,
    "total_sent": 3000,
    "total_success": 2900,
    "total_failed": 100
  },
  {
    "notification_type_id": 5,
    "total_sent": 2000,
    "total_success": 1850,
    "total_failed": 150
  }
]
```

### Get Breakdown by Delivery Method

```
GET /api/notices/summaries/by-delivery
```

Get summary breakdown by delivery method.

---

## Analytics Endpoints

### Get Dashboard Overview

```
GET /api/notices/analytics/overview
```

Get comprehensive dashboard overview data.

**Query Parameters:**
- `days` (int): Number of days to analyze (default: 30)

**Example Response:**
```json
{
  "period": {
    "start_date": "2025-10-09",
    "end_date": "2025-11-08",
    "days": 30
  },
  "totals": {
    "total_sent": 5000,
    "total_success": 4750,
    "total_failed": 250
  },
  "recent_activity": 342,
  "by_type": [...],
  "by_delivery": [...],
  "trend": [
    {
      "date": "2025-11-01",
      "sent": 150,
      "success": 145,
      "failed": 5
    }
  ]
}
```

![API Overview Response](images/api-overview.png)
*Analytics overview endpoint response*

### Get Time Series Data

```
GET /api/notices/analytics/time-series
```

Get time series data for charts.

**Query Parameters:**
- `start_date` (date): Start date
- `end_date` (date): End date
- `group_by` (string): Grouping period (day/week/month, default: day)

### Get Top Patrons

```
GET /api/notices/analytics/top-patrons
```

Get patrons with highest notification counts.

**Query Parameters:**
- `days` (int): Number of recent days (default: 30)
- `limit` (int): Number of results (default: 10, max: 100)

### Get Success Rate Trend

```
GET /api/notices/analytics/success-rate-trend
```

Get success rate trends over time.

---

## Shoutbomb Endpoints

### Get Deliveries

```
GET /api/notices/shoutbomb/deliveries
```

Get Shoutbomb delivery records (SMS/Voice).

**Query Parameters:**
- `per_page` (int): Results per page
- `start_date` (date): Filter by start date
- `end_date` (date): Filter by end date
- `type` (string): Filter by delivery type (SMS/Voice)
- `status` (string): Filter by status

### Get Delivery Statistics

```
GET /api/notices/shoutbomb/deliveries/stats
```

Get delivery statistics grouped by type and status.

### Get Keyword Usage

```
GET /api/notices/shoutbomb/keyword-usage
```

Get keyword usage records (HOLDS, RENEW, etc.).

### Get Keyword Summary

```
GET /api/notices/shoutbomb/keyword-usage/summary
```

Get aggregated keyword usage summary.

### Get Registrations

```
GET /api/notices/shoutbomb/registrations
```

Get subscriber registration snapshots.

### Get Latest Registration

```
GET /api/notices/shoutbomb/registrations/latest
```

Get the most recent subscriber statistics.

**Example Response:**
```json
{
  "data": {
    "id": 1,
    "snapshot_date": "2025-11-08",
    "subscribers": {
      "text": {
        "count": 13307,
        "percentage": 72.0
      },
      "voice": {
        "count": 5199,
        "percentage": 28.0
      },
      "total": 18506
    }
  }
}
```

---

## Response Format

All API responses follow Laravel's JSON Resource format:

### Success Response
```json
{
  "data": { ... },
  "links": { ... },
  "meta": { ... }
}
```

### Error Response
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Rate Limiting

API requests are rate-limited to 60 requests per minute by default. This can be configured in `config/notices.php`.

## Pagination

All list endpoints support pagination:
- Default page size: 20 items
- Maximum page size: 100 items
- Use `per_page` query parameter to customize

## Examples

### Using with JavaScript

```javascript
fetch('/api/notices/logs?days=7&successful=1', {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

### Using with PHP/Laravel

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken('YOUR_TOKEN')
    ->get('/api/notices/logs', [
        'days' => 7,
        'successful' => 1
    ]);

$notifications = $response->json('data');
```

### Using with cURL

```bash
curl -X GET \
  "https://yourapp.com/api/notices/analytics/overview?days=30" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Building Custom Dashboards

The API is designed to support custom dashboard implementations:

1. **Vue/React**: Fetch data using HTTP clients
2. **Mobile Apps**: Use the API for native apps
3. **External Systems**: Integrate with reporting tools
4. **Data Exports**: Build custom export workflows

## Next Steps

- Configure authentication (Laravel Sanctum)
- Set up rate limiting as needed
- Build your custom dashboard
- Monitor API usage

For questions about the default dashboard, see [DASHBOARD.md](DASHBOARD.md).
