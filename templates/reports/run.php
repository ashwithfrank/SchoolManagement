<?php use App\Core\Ui; use App\Services\FeeService;
$h = fn($k) => isset($needs[$k]);
$cell = function ($v, $type) {
    if ($v === null || $v === '') { return ''; }
    if ($type === 'money') { return money((int) $v); }
    if ($type === 'date') { return dmy($v); }
    return (string) $v;
};
$exportQ = fn($mode) => url('reports', 'run', array_merge(array_filter($_GET, fn($k) => !in_array($k, ['m', 'a', 'page', 'export'], true), ARRAY_FILTER_USE_KEY), ['r' => $key, 'export' => $mode])); ?>
<div class="page-head"><div><h1><?= e($def['title']) ?></h1><div class="sub"><?= e($def['desc']) ?></div></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('reports', 'index')) ?>">All reports</a></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="reports"><input type="hidden" name="a" value="run"><input type="hidden" name="r" value="<?= e($key) ?>">
  <div class="row g-2 align-items-end">
  <?php if ($h('year')): ?><div class="col-6 col-md-2"><label class="form-label" for="year">Year</label><select class="form-select" id="year" name="year"><?= Ui::options($years, 'id', 'name', $yearSel) ?></select></div><?php endif; ?>
  <?php if ($h('from')): ?><div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div><div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div><?php endif; ?>
  <?php if ($h('exam')): ?><div class="col-6 col-md-2"><label class="form-label" for="exam">Exam</label><select class="form-select" id="exam" name="exam"><?= Ui::options($exams, 'id', 'name', $f['exam'], 'Choose') ?></select></div><?php endif; ?>
  <?php if ($h('class')): ?><div class="col-6 col-md-2"><label class="form-label" for="class">Class</label><select class="form-select" id="class" name="class" data-sections-target="#section"><?= Ui::options($classes, 'id', 'name', $f['class'], 'All') ?></select></div><?php endif; ?>
  <?php if ($h('section')): ?><div class="col-6 col-md-2"><label class="form-label" for="section">Section</label><select class="form-select" id="section" name="section"><?= Ui::sectionOptions($sections, $f['section'], 'All') ?></select></div><?php endif; ?>
  <?php if ($h('subject')): ?><div class="col-6 col-md-2"><label class="form-label" for="subject">Subject</label><select class="form-select" id="subject" name="subject"><?= Ui::options($subjects, 'id', 'name', $f['subject'], 'Choose') ?></select></div><?php endif; ?>
  <?php if ($h('bus')): ?><div class="col-6 col-md-2"><label class="form-label" for="bus">Bus</label><select class="form-select" id="bus" name="bus"><?= Ui::options($buses, 'id', 'bus_code', $f['bus'], 'All') ?></select></div><?php endif; ?>
  <?php if ($h('category')): ?><div class="col-6 col-md-2"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category"><option value="">All</option><?php foreach (FeeService::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['category']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if ($h('method')): ?><div class="col-6 col-md-2"><label class="form-label" for="method">Method</label><select class="form-select" id="method" name="method"><option value="">All</option><?php foreach (FeeService::METHODS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['method']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if ($h('student_status')): ?><div class="col-6 col-md-2"><label class="form-label" for="student_status">Status</label><select class="form-select" id="student_status" name="student_status"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $f['student_status']) ?>><?= $l ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if ($h('staff_status')): ?><div class="col-6 col-md-2"><label class="form-label" for="staff_status">Status</label><select class="form-select" id="staff_status" name="staff_status"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $f['staff_status']) ?>><?= $l ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if ($h('fee_status')): ?><div class="col-6 col-md-2"><label class="form-label" for="fee_status">Fee status</label><select class="form-select" id="fee_status" name="fee_status"><?php foreach (['pending' => 'Pending', 'paid' => 'Paid', 'unassessed' => 'No fee set', '' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['fee_status']) ?>><?= $l ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <?php if ($h('q')): ?><div class="col-md-3"><label class="form-label" for="q">Search students</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>"></div><?php endif; ?>
  <div class="col-md-3"><label class="form-label" for="find">Find in results</label><input class="form-control" id="find" name="find" value="<?= e($find) ?>" placeholder="Any column"></div>
  <div class="col-6 col-md-2 d-grid"><button class="btn btn-primary">Run</button></div></div></form>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php elseif ($report): ?>
<?php if ($report['summary']): ?><div class="row g-2 mb-3"><?php foreach ($report['summary'] as $l => $v): ?><div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted"><?= e($l) ?></div><div class="fw-semibold"><?= e($v) ?></div></div></div></div><?php endforeach; ?></div><?php endif; ?>
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span><?= number_format($pager->total) ?> row(s)</span>
  <span><a class="btn btn-sm btn-outline-secondary" data-allow-resubmit href="<?= e($exportQ('csv')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a>
  <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= e($exportQ('print')) ?>"><i class="fa-solid fa-print me-1"></i>Print / PDF</a></span></div>
<?php if (!$page): ?><?= Ui::empty('table', 'No rows match these filters') ?><?php else: ?>
<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
  <?php foreach ($cols as $i => $c): $nd = ($sort === $i && $dir === 'asc') ? 'desc' : 'asc'; ?><th class="<?= in_array($c[1], ['num', 'money'], true) ? 'text-end' : '' ?>"><a class="text-reset text-decoration-none" href="<?= e(url('reports', 'run', array_merge(array_filter($_GET, fn($k) => !in_array($k, ['m', 'a', 'page'], true), ARRAY_FILTER_USE_KEY), ['r' => $key, 'sort' => $i, 'dir' => $nd]))) ?>"><?= e($c[0]) ?><?= $sort === $i ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' ?></a></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($page as $r): ?><tr><?php foreach ($r as $i => $v): $t = $cols[$i][1] ?? 'text'; ?><td class="<?= in_array($t, ['num', 'money'], true) ? 'text-end' : '' ?>"><?= e($cell($v, $t)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<div class="card-footer bg-white d-flex justify-content-end"><?= Ui::pagination($pager) ?></div><?php endif; ?></div><?php endif; ?>
