# Notices Architecture and Interaction Diagram (Textual)

This document is the current, code-aligned architecture map for the package after controller, route, and provider modularization.

Use this as the first reference when tracing request flow, adding features, or debugging cross-component behavior.

---

## 1) Code structure map

```text
dcplibrary/notices
|
|-- src/
|   |-- NoticesServiceProvider.php
|   |    |-- registerCoreServiceBindings()
|   |    |-- registerUiServiceBindings()
|   |    `-- loadPackageRoutes()
|   |
|   |-- Http/Controllers/
|   |    |-- DashboardController
|   |    |-- NotificationsController
|   |    |-- IssuesController
|   |    |-- FailedContactsController
|   |    |-- SearchController
|   |    |-- DataController
|   |    |-- SettingsJobsController
|   |    |-- SettingsDisplayController
|   |    |-- SettingsApiTokensController
|   |    |-- SettingsActivityController
|   |    |-- SettingsMaintenanceBackupController
|   |    |-- NotificationController (compatibility facade)
|   |    `-- SettingsController (compatibility facade)
|   |
|   |-- Services/
|   |    |-- NotificationService (orchestrator/facade)
|   |    |-- NotificationQueryService
|   |    |-- IssueQueryService
|   |    |-- FailedContactQueryService
|   |    |-- IssueResolutionService
|   |    |-- OrphanedNotificationQueryService
|   |    |-- ImportStatusService
|   |    |-- ConfirmationQueryService
|   |    |-- NotificationMetricsQueryService
|   |    |-- NotificationDiagnosticsQueryService
|   |    |-- NotificationReconciler
|   |    `-- NotificationFailureDetector
|   |
|   `-- Console/
|        `-- ImportCommand, ImportEmailCommand, IntegrityCheckCommand, etc.
|
|-- routes/
|   |-- web.php  (manifest)
|   |    `-- web/{dashboard,notifications,issues,data,holds,settings,global-search,api-docs}.php
|   `-- api.php  (manifest)
|        `-- api/{dashboard,notifications,issues,failed-contacts,search,admin-settings}.php
|
`-- resources/views/
    |-- notifications/
    |-- data/
    |-- settings/
    `-- components/ (detail modals, cards, shared UI)
```

---

## 2) Web/API request interaction flow

### Web request flow

```text
Browser
  -> routes/web.php
      -> require routes/web/<feature>.php
          -> Feature Controller method
              -> NotificationService (or feature service)
                  -> Query services / models
                      -> Database
              -> Blade view response
```

### API request flow

```text
Client (Sanctum token)
  -> routes/api.php
      -> require routes/api/<feature>.php
          -> Feature Controller API method
              -> NotificationService (or feature service)
                  -> Query services / models
                      -> Database
              -> JsonResponse
```

---

## 3) Controller-to-service function map

```text
DashboardController
  - dashboard(), dashboardApi(), dashboardStatsApi(), importStatusApi()
  -> NotificationService:
     - getDateRangeStatistics()
     - getDateRangeConfirmations()
     - getOrphanedNotifications()
     - getImportStatus()
     - getFailedContactCountsByChannel()
     - getDashboardFailedContacts()

NotificationsController
  - index(), patron(), show(), notificationsApi(), patronApi()
  -> NotificationService:
     - searchNotifications() -> NotificationQueryService
     - getPatronNotificationHistory() -> NotificationDiagnosticsQueryService

IssuesController
  - issues(), issuesApi(), issueStatisticsApi(), resolveIssue(), bulkResolve(), etc.
  -> NotificationService:
     - searchIssues() -> IssueQueryService
     - getIssueStatistics() -> NotificationMetricsQueryService
     - resolveIssue()/bulkResolve*() -> IssueResolutionService
     - getOrphanedNotifications() -> OrphanedNotificationQueryService

FailedContactsController
  - failedContactsApi(), failedContactCountsApi(), showFailedContact(), resolveFailedContact*()
  -> NotificationService:
     - getFailedDeliveryStringsPaginatedForApi() -> FailedContactQueryService
     - getFailedContactCountsByChannel*() -> FailedContactQueryService
     - resolveFailedDeliveryString() -> IssueResolutionService

Settings* controllers
  - jobs, display, tokens, activity, maintenance/backup
  -> Service layer + models + Artisan entrypoints for queue/import operations
```

---

## 4) Import/reconciliation process diagram

```text
Scheduler / Admin action (SettingsJobsController::runImportApi)
  -> Artisan notices:import
      -> ImportCommand
          -> FTP import services + Graph email ingestion
              -> Raw source tables + ingested_emails + import batches
          -> NotificationReconciler::deduplicate()
              -> notifications table (logical records)
          -> NotificationReconciler::reconcile()
              -> delivered/failed/pending state transitions
          -> NotificationFailureDetector
              -> patron_delivery_issues + failed_delivery_strings
          -> Integrity check / cleanup commands (when configured)
```

Key process guarantees:
- Route and controller modularity for read/write paths.
- Service-layer orchestration with focused query/action services.
- Date-range filtering and stable pagination ordering in hot query paths.
- Compatibility facades retained for legacy host-app route bindings.

---

## 5) UI interactivity between components

```text
List pages (notifications/issues/failed contacts)
  -> row click dispatches Alpine events
      -> detail modal component opens
          -> modal fetches JSON endpoint
              -> controller + service query pipeline
                  -> returns normalized JSON payload
```

Cross-modal interaction pattern:
- Issue detail can open related notification detail via dispatched event.
- Modals preserve page filter/sort context by avoiding full-page navigation.

---

## 6) Compatibility layer (intentional)

```text
Legacy host app route -> NotificationController / SettingsController
  -> forward(<new feature controller>, <method>, <params>)
      -> executes modern modular controller method
```

This allows package internals to evolve without breaking host apps that still reference legacy controller methods.

---

## 7) Where to plug in new features

1. Add route in the correct feature module file under `routes/web/` or `routes/api/`.
2. Add/extend a focused controller method in `src/Http/Controllers/`.
3. Add query/action logic in a dedicated service class (or extend an existing focused service).
4. Keep `NotificationService` as orchestration facade when cross-feature composition is needed.
5. Add feature tests for route wiring + behavior + pagination/sorting invariants.
