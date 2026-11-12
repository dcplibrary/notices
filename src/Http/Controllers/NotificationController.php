<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Http\Requests\Api\DashboardStatsRequest;
use Dcplibrary\Notices\Http\Requests\Api\ListNotificationsRequest;
use Dcplibrary\Notices\Support\NotificationActivityRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Backward-compatibility facade for the pre-modular NotificationController.
 *
 * Routes now target focused controllers directly. This class remains to support
 * custom host-app routes that may still reference legacy method names.
 */
class NotificationController extends Controller
{
    public function dashboard(Request $request)
    {
        return $this->forward(DashboardController::class, __FUNCTION__, [$request]);
    }

    public function index(Request $request)
    {
        return $this->forward(NotificationsController::class, __FUNCTION__, [$request]);
    }

    public function patron(string $barcode)
    {
        return $this->forward(NotificationsController::class, __FUNCTION__, [$barcode]);
    }

    public function issues(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function emailSystemErrorClusters(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function orphaned(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function affectedPatrons(int $id)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$id]);
    }

    public function showFailedContact(string $id)
    {
        return $this->forward(FailedContactsController::class, __FUNCTION__, [$id]);
    }

    public function globalSearch(Request $request)
    {
        return $this->forward(SearchController::class, __FUNCTION__, [$request]);
    }

    public function resolveIssue(Request $request, int $id)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request, $id]);
    }

    public function resolveIssueApi(Request $request, int $id): JsonResponse
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request, $id]);
    }

    public function resolveFailedContact(Request $request, string $id)
    {
        return $this->forward(FailedContactsController::class, __FUNCTION__, [$request, $id]);
    }

    public function resolveFailedContactApi(Request $request, string $id): JsonResponse
    {
        return $this->forward(FailedContactsController::class, __FUNCTION__, [$request, $id]);
    }

    public function bulkResolveForm(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function bulkResolve(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function bulkResolveApi(Request $request): JsonResponse
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function showIssue(int $id)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$id]);
    }

    public function show(int $id, NotificationActivityRepository $activityRepository)
    {
        return $this->forward(NotificationsController::class, __FUNCTION__, [$id, $activityRepository]);
    }

    public function dashboardApi(Request $request)
    {
        return $this->forward(DashboardController::class, __FUNCTION__, [$request]);
    }

    public function dashboardStatsApi(DashboardStatsRequest $request)
    {
        return $this->forward(DashboardController::class, __FUNCTION__, [$request]);
    }

    public function importStatusApi()
    {
        return $this->forward(DashboardController::class, __FUNCTION__);
    }

    public function notificationsApi(ListNotificationsRequest $request)
    {
        return $this->forward(NotificationsController::class, __FUNCTION__, [$request]);
    }

    public function patronApi(string $barcode)
    {
        return $this->forward(NotificationsController::class, __FUNCTION__, [$barcode]);
    }

    public function issuesApi(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    public function issueStatisticsApi()
    {
        return $this->forward(IssuesController::class, __FUNCTION__);
    }

    public function failedContactsApi(Request $request)
    {
        return $this->forward(FailedContactsController::class, __FUNCTION__, [$request]);
    }

    public function failedContactCountsApi()
    {
        return $this->forward(FailedContactsController::class, __FUNCTION__);
    }

    public function orphanedNotificationsApi(Request $request)
    {
        return $this->forward(IssuesController::class, __FUNCTION__, [$request]);
    }

    protected function forward(string $controllerClass, string $method, array $parameters = [])
    {
        return app($controllerClass)->{$method}(...$parameters);
    }
}
