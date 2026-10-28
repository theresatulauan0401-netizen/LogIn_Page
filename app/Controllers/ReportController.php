<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\ActivityLogService;
use App\Services\ReportService;

final class ReportController extends BaseController
{
    public function __construct(
        Request $request, Auth $auth, Session $session, Csrf $csrf,
        private ReportService $reports,
        private ActivityLogService $activity
    ) {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('reports.view');
        [$type, $f] = $this->params();
        $report = $this->reports->run($type, $f);
        return $this->render('reports/index', [
            'title' => 'Reports', 'subtitle' => 'Live figures from the database.', 'breadcrumbs' => [['Reports', null]],
            'type' => $type, 'f' => $f, 'report' => $report, 'types' => ReportService::TYPES, 'groups' => ReportService::GROUPS,
        ]);
    }

    /** Read-only download of exactly what the screen shows (same filters). */
    public function export(): void
    {
        $this->auth->authorize('reports.view');
        [$type, $f] = $this->params();
        $report = $this->reports->run($type, $f);

        $this->activity->log('export_report', 'reports', "Exported $type report {$f['from']} to {$f['to']}");
        $name = sprintf('report-%s-%s-to-%s.csv', $type, $f['from'], $f['to']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows the peso sign correctly
        fputcsv($out, array_column($report['columns'], 'label'));
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_map(fn ($col) => $this->cell($row[$col['key']] ?? '', $col['type']), $report['columns']));
        }
        if ($report['totals'] !== null) {
            fputcsv($out, array_map(fn ($col) => $this->cell($report['totals'][$col['key']] ?? '', $col['type']), $report['columns']));
        }
        fclose($out);
        exit;
    }

    /** CSV cell: numbers stay numeric; text is protected against spreadsheet formula injection. */
    private function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'money') {
            return number_format((float) $value, 2, '.', '');
        }
        if ($type === 'int') {
            return (string) (int) $value;
        }
        if ($type === 'percent') {
            return number_format((float) $value, 1, '.', '');
        }
        $text = (string) $value;
        return in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }

    /** @return array{0:string,1:array{from:string,to:string,group:string,status:string}} */
    private function params(): array
    {
        $type = (string) $this->request->query('type', 'bookings');
        if (!isset(ReportService::TYPES[$type])) {
            $type = 'bookings';
        }
        $valid = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false && date('Y-m-d', strtotime($d)) === $d;
        $from = (string) $this->request->query('from', '');
        $to = (string) $this->request->query('to', '');
        if (!$valid($from)) { $from = date('Y-m-d', strtotime('-29 days')); }
        if (!$valid($to)) { $to = date('Y-m-d'); }
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        if (strtotime($to) - strtotime($from) > 366 * 86400) { $from = date('Y-m-d', strtotime($to . ' -366 days')); }

        $group = (string) $this->request->query('group', 'day');
        $status = (string) $this->request->query('status', '');
        if (!isset(ReportService::GROUPS[$group])) { $group = 'day'; }
        if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'], true)) { $status = ''; }

        return [$type, ['from' => $from, 'to' => $to, 'group' => $group, 'status' => $status]];
    }
}
