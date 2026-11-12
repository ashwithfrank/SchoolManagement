<?php
use App\Core\{Audit, Dates, Flash, Http, Pager, UserError, View};
use App\Services\{AcademicService, ExamService, ReportService, TransportService};

$key = Http::strParam('r');
$defs = ReportService::definitions();
if (!isset($defs[$key])) {
    Flash::error('Unknown report.');
    redirect(url('reports', 'index'));
}
$def = $defs[$key];
if (!\App\Core\Auth::can($def['perm'])) {
    throw new \App\Core\Forbidden();
}
$f = [];
foreach (['year', 'class', 'section', 'exam', 'subject', 'bus'] as $k) {
    $f[$k] = Http::intParam($k);
}
foreach (['from', 'to'] as $k) {
    $v = Http::strParam($k);
    $f[$k] = Dates::isValid($v) ? $v : null;
}
foreach (['q', 'category', 'method'] as $k) {
    $f[$k] = Http::strParam($k);
}
$f['student_status'] = in_array(Http::strParam('student_status'), ['active', 'inactive', 'all'], true) ? Http::strParam('student_status') : 'active';
$f['staff_status'] = in_array(Http::strParam('staff_status'), ['active', 'inactive', 'all'], true) ? Http::strParam('staff_status') : 'active';
$f['fee_status'] = in_array(Http::strParam('fee_status', 'pending'), ['pending', 'paid', 'unassessed', ''], true) ? Http::strParam('fee_status', 'pending') : 'pending';

$report = null;
$error = null;
try {
    $report = ReportService::run($key, $f);
} catch (UserError $e) {
    $error = $e->getMessage();
}

$sort = Http::intParam('sort');
$dir = Http::strParam('dir') === 'desc' ? 'desc' : 'asc';
$search = trim(Http::strParam('find'));
if ($report) {
    $cols = $report['columns'];
    $rows = $report['rows'];
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $rows = array_values(array_filter($rows, function ($r) use ($needle) {
            foreach ($r as $v) {
                if ($v !== null && mb_strpos(mb_strtolower((string) $v), $needle) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }
    if ($sort !== null && isset($cols[$sort])) {
        $num = in_array($cols[$sort][1], ['num', 'money'], true);
        usort($rows, function ($a, $b) use ($sort, $num, $dir) {
            $x = $a[$sort];
            $y = $b[$sort];
            $c = $num ? ((float) $x <=> (float) $y) : strcasecmp((string) $x, (string) $y);
            return $dir === 'desc' ? -$c : $c;
        });
    }
    $mode = Http::strParam('export');
    $fmtCsv = function ($v, $type) {
        if ($type === 'money' && $v !== null && $v !== '') {
            $p = (int) $v;
            return ($p < 0 ? '-' : '') . intdiv(abs($p), 100) . '.' . str_pad((string) (abs($p) % 100), 2, '0', STR_PAD_LEFT);
        }
        return $v;
    };
    if ($mode === 'csv') {
        $out = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($r as $i => $v) {
                $line[] = $fmtCsv($v, $cols[$i][1] ?? 'text');
            }
            $out[] = $line;
        }
        Audit::log('export.report', 'report', null, ['report' => $key, 'rows' => count($out)]);
        Http::csv($key . '-' . date('Ymd-His') . '.csv', array_column($cols, 0), $out);
    }
    if ($mode === 'print') {
        Audit::log('print.report', 'report', null, ['report' => $key, 'rows' => count($rows)]);
        View::print('reports/print', ['def' => $def, 'report' => ['columns' => $cols, 'summary' => $report['summary']], 'rows' => $rows], $def['title']);
        exit;
    }
    $pager = new Pager(count($rows), 50);
    $page = array_slice($rows, $pager->offset(), $pager->perPage);
} else {
    $pager = null;
    $page = [];
    $cols = [];
}
$year = $f['year'] ?: ((AcademicService::currentYear()['id'] ?? null));
$needs = array_flip($def['filters']);
View::page('reports/run', [
    'key' => $key, 'def' => $def, 'f' => $f, 'report' => $report, 'error' => $error, 'cols' => $cols, 'page' => $page, 'pager' => $pager, 'sort' => $sort, 'dir' => $dir, 'find' => $search,
    'needs' => $needs, 'years' => AcademicService::years(), 'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(),
    'exams' => isset($needs['exam']) && $year ? ExamService::exams((int) $year) : [],
    'subjects' => isset($needs['subject']) ? AcademicService::subjects(true) : [],
    'buses' => isset($needs['bus']) && \App\Core\Auth::can('transport.view') ? TransportService::buses() : [],
    'yearSel' => $year,
], $def['title']);
