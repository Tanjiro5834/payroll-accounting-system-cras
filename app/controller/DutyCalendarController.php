<?php
namespace App\Controller;

use App\Helper\Response;
use App\Service\AuditService;
use App\Service\HolidayService;
use App\Service\PayrollService;
use App\Service\SundayDutyService;
use App\Service\SystemSettingService;
use InvalidArgumentException;

// Sundays & Holidays page: the Sunday duty roster, the holiday calendar payroll reads,
// and the unworked-regular-holiday pay setting.
class DutyCalendarController extends BaseController {
    private SundayDutyService $duties;
    private HolidayService $holidays;
    private SystemSettingService $settings;

    public function __construct() {
        $this->duties   = new SundayDutyService();
        $this->holidays = new HolidayService();
        $this->settings = new SystemSettingService();
    }

    // GET ?page=duty-calendar&action=roster&start=2026-10-01&end=2026-12-31
    public function roster(): void {
        $this->guard(fn() => Response::json($this->duties->getRange(
            $this->queryTrim('start', date('Y-m-01')),
            $this->queryTrim('end', date('Y-m-d', strtotime('+3 months')))
        )), 'Failed to load the duty roster.');
    }

    // POST ?page=duty-calendar&action=saveDuty[&id=3]   body: {duty_date, lead_employee_id, member_ids: [], notes}
    public function saveDuty(?int $id = null): void {
        $this->requireMethod('POST');
        $actor = $this->requireSessionUser();
        $this->guard(
            fn() => Response::json($this->duties->save($this->input(), $actor, $id), $id ? 200 : 201),
            'Failed to save the duty team.'
        );
    }

    // POST ?page=duty-calendar&action=deleteDuty&id=3
    public function deleteDuty(int $id): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $this->duties->delete($id);
            Response::json(['ok' => true]);
        }, 'Failed to remove the duty team.');
    }

    // GET ?page=duty-calendar&action=holidays&year=2026
    public function holidays(): void {
        $year = $this->queryInt('year', (int) date('Y'));
        $this->guard(fn() => Response::json(array_map(
            fn($h) => $h->toArray(),
            $this->holidays->getByYear($year)
        )), 'Failed to load holidays.');
    }

    // POST ?page=duty-calendar&action=saveHoliday[&id=7]   body: {holiday_date, name, type}
    public function saveHoliday(?int $id = null): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $data = $this->input();
            if ($id) {
                $this->holidays->update($id, $data);
            } else {
                $id = $this->holidays->create($data);
            }
            (new AuditService())->record('HOLIDAY_SAVE', null, ['holiday_id' => $id] + array_intersect_key($data, array_flip(['holiday_date', 'name', 'type'])));
            Response::json(['ok' => true, 'id' => $id]);
        }, 'Failed to save the holiday.');
    }

    // POST ?page=duty-calendar&action=deleteHoliday&id=7
    public function deleteHoliday(int $id): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $holiday = $this->holidays->getById($id);
            $this->holidays->delete($id);
            (new AuditService())->record('HOLIDAY_DELETE', null, ['holiday_id' => $id, 'holiday_date' => $holiday?->getHolidayDate()]);
            Response::json(['ok' => true]);
        }, 'Failed to delete the holiday.');
    }

    // POST ?page=duty-calendar&action=seedHolidays   body: {year}
    // Adds the fixed-date PH holidays; movable ones (Holy Week, Eid, Chinese New Year) are added by hand.
    public function seedHolidays(): void {
        $this->requireMethod('POST');
        $this->guard(function () {
            $year = (int) ($this->input()['year'] ?? date('Y'));
            Response::json(['added' => $this->holidays->seedPhilippineHolidays($year)]);
        }, 'Failed to add holidays.');
    }

    // GET ?page=duty-calendar&action=settings
    public function settings(): void {
        $this->guard(fn() => Response::json([
            'pay_unworked_regular_holiday' => $this->settings->getValue(PayrollService::SETTING_PAY_UNWORKED_REGULAR, '0') === '1',
        ]), 'Failed to load settings.');
    }

    // POST ?page=duty-calendar&action=saveSettings   body: {pay_unworked_regular_holiday: true|false}
    public function saveSettings(): void {
        $this->requireMethod('POST');
        $this->guard(function () {
            $input = $this->input();
            if (!array_key_exists('pay_unworked_regular_holiday', $input)) {
                throw new InvalidArgumentException('pay_unworked_regular_holiday is required.');
            }
            $value = filter_var($input['pay_unworked_regular_holiday'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            $this->settings->setValue(PayrollService::SETTING_PAY_UNWORKED_REGULAR, $value);
            (new AuditService())->record('SETTING_UPDATE', null, ['key' => PayrollService::SETTING_PAY_UNWORKED_REGULAR, 'value' => $value]);
            Response::json(['ok' => true, 'pay_unworked_regular_holiday' => $value === '1']);
        }, 'Failed to save settings.');
    }
}
