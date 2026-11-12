<?php use App\Core\Settings;
$cell = function ($v, $type) { if ($v === null || $v === '') { return ''; } if ($type === 'money') { return money((int) $v); } if ($type === 'date') { return dmy($v); } return (string) $v; }; ?>
<div class="text-center mb-3"><h2 class="mb-0"><?= e(Settings::get('school_name')) ?></h2><h4 class="mt-1"><?= e($def['title']) ?></h4></div>
<?php if ($report['summary']): ?><p class="small"><?php foreach ($report['summary'] as $l => $v): ?><b><?= e($l) ?>:</b> <?= e($v) ?> &nbsp; <?php endforeach; ?></p><?php endif; ?>
<table class="table table-bordered table-sm" style="font-size:11px"><thead><tr><?php foreach ($report['columns'] as $c): ?><th class="<?= in_array($c[1], ['num', 'money'], true) ? 'text-end' : '' ?>"><?= e($c[0]) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $i => $v): $t = $report['columns'][$i][1] ?? 'text'; ?><td class="<?= in_array($t, ['num', 'money'], true) ? 'text-end' : '' ?>"><?= e($cell($v, $t)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
<div class="small text-muted"><?= count($rows) ?> row(s). Printed on <?= e(date('d M Y, h:i A')) ?></div>
