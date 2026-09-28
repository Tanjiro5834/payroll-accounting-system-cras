<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\LocationLogService;
use InvalidArgumentException;

class LocationLogController {
    private LocationLogService $service;

    public function __construct(?LocationLogService $service = null) {
        $this->service = $service ?? new LocationLogService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $date = trim((string) ($_GET['date'] ?? ''));
        if ($date === '') {
            Response::error('date is required.', 422);
        }

        try {
            Response::json($this->service->getByDate($date));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location logs.', 500);
        }
    }

    public function byEmployee($employeeId): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) $employeeId;
        $start      = trim((string) ($_GET['start'] ?? ''));
        $end        = trim((string) ($_GET['end']   ?? ''));

        if ($employeeId < 1 || $start === '' || $end === '') {
            Response::error('employee_id, start, and end are required.', 422);
        }

        try {
            Response::json($this->service->getByEmployee($employeeId, $start, $end));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location logs.', 500);
        }
    }

    public function byDate($date): void {
        AuthMiddleware::requireLogin();

        try {
            Response::json($this->service->getByDate((string) $date));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location logs.', 500);
        }
    }

    public function byDateRange($start, $end): void {
        AuthMiddleware::requireLogin();

        try {
            Response::json($this->service->getByDateRange((string) $start, (string) $end));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location logs.', 500);
        }
    }

    public function show($punchId): void {
        AuthMiddleware::requireLogin();

        $punchId = (int) $punchId;
        if ($punchId < 1) {
            Response::error('Invalid punch ID.', 422);
        }

        try {
            $coords = $this->service->getCoordinatesForPunch($punchId);
            if ($coords === null) {
                Response::error('No location recorded for this punch.', 404);
            }

            $coords['google_maps_link'] = $this->service->getGoogleMapsLink(
                $coords['gps_lat'],
                $coords['gps_lng']
            );
            $coords['formatted'] = $this->service->formatCoordinates(
                $coords['gps_lat'],
                $coords['gps_lng']
            );

            Response::json($coords);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location.', 500);
        }
    }

    public function map($employeeId, $date): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) $employeeId;

        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        try {
            $history = $this->service->getMovementHistory($employeeId, (string) $date);

            $markers = array_map(function (array $p) {
                return [
                    'punch_id'    => $p['punch_id'],
                    'punch_type'  => $p['punch_type'],
                    'punch_time'  => $p['punch_time'],
                    'lat'         => $p['gps_lat'],
                    'lng'         => $p['gps_lng'],
                    'accuracy'    => $p['accuracy'],
                    'link'        => $this->service->getGoogleMapsLink($p['gps_lat'], $p['gps_lng']),
                ];
            }, $history['points']);

            $history['markers'] = $markers;
            $history['distance_traveled'] = $this->service->getDistanceTraveled($employeeId, (string) $date);

            Response::json($history);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load map data.', 500);
        }
    }

    public function movementHistory($employeeId, $date): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) $employeeId;
        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        try {
            Response::json($this->service->getMovementHistory($employeeId, (string) $date));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load movement history.', 500);
        }
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date       = trim((string) ($_GET['date'] ?? ''));

        try {
            if ($employeeId > 0 && $date !== '') {
                $rows = $this->service->getMovementHistory($employeeId, $date)['points'];
            } elseif ($employeeId > 0) {
                $start = trim((string) ($_GET['start'] ?? ''));
                $end   = trim((string) ($_GET['end']   ?? ''));
                if ($start === '' || $end === '') {
                    Response::error('start and end are required when date is not provided.', 422);
                    return;
                }
                $rows = $this->service->getByEmployee($employeeId, $start, $end);
            } else {
                Response::error('employee_id is required.', 422);
                return;
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="location-log.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Punch ID', 'Work Date', 'Punch Type', 'Punch Time', 'Latitude', 'Longitude', 'Accuracy (m)', 'Maps Link']);
        foreach ($rows as $row) {
            $lat = $row['gps_lat'] ?? null;
            $lng = $row['gps_lng'] ?? null;

            fputcsv($out, [
                $row['punch_id'] ?? $row['id'] ?? '',
                $row['work_date'] ?? '',
                $row['punch_type'] ?? '',
                $row['punch_time'] ?? '',
                $lat ?? '',
                $lng ?? '',
                $row['accuracy'] ?? '',
                ($lat !== null && $lng !== null)
                    ? $this->service->getGoogleMapsLink($lat, $lng)
                    : '',
            ]);
        }

        fclose($out);
        exit;
    }
}