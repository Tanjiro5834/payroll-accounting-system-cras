<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\FlaggingService;

class FlaggedPunchController extends BaseController {
    private FlaggingService $service;

    public function __construct(?FlaggingService $service = null) {
        $this->service = $service ?? new FlaggingService();
    }

    // GET ?page=flagged-punches&action=index&status=open&date_from=&date_to=&employee_id=
    public function index(): void {
        AuthMiddleware::requireLogin();

        $this->guard(fn() => Response::json($this->service->search(
            $this->queryTrim('status', 'open'),
            $this->queryTrim('date_from'),
            $this->queryTrim('date_to'),
            $this->queryInt('employee_id')
        )), 'Failed to load flagged punches.');
    }

    // POST ?page=flagged-punches&action=review&id=51
    public function review(int $id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');
        $reviewer = $this->requireSessionUser();

        $this->guardWithStatus(function () use ($id, $reviewer) {
            $this->service->markFlagReviewed($id, $reviewer);
            Response::json(['success' => true]);
        }, 409, 'Failed to review punch.');
    }

    // POST ?page=flagged-punches&action=reviewBulk   body: {"ids": [51, 52]}
    public function reviewBulk(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');
        $reviewer = $this->requireSessionUser();

        $this->guard(function () use ($reviewer) {
            $ids = $this->input()['ids'] ?? [];
            Response::json(['reviewed' => $this->service->markFlagsReviewedBulk(is_array($ids) ? $ids : [], $reviewer)]);
        }, 'Failed to review punches.');
    }
}