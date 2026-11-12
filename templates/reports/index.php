<?php $titles = ['reports.students' => 'Students and transport', 'reports.finance' => 'Fees and collection', 'reports.academic' => 'Exams and results', 'reports.staff' => 'Staff']; ?>
<div class="page-head"><div><h1>Reports</h1><div class="sub">Every report can be searched, sorted, filtered, exported to CSV and printed (use "Print / Save as PDF").</div></div></div>
<?php if (!$groups): ?><div class="alert alert-info">Your role has no reports.</div><?php endif; ?>
<?php foreach ($groups as $perm => $items): ?><h5 class="mt-3"><?= e($titles[$perm] ?? $perm) ?></h5><div class="row g-3">
  <?php foreach ($items as $key => $d): ?><div class="col-md-6 col-xl-4"><a class="card h-100 text-decoration-none text-reset report-card" href="<?= e(url('reports', 'run', ['r' => $key])) ?>"><div class="card-body"><div class="fw-semibold text-primary"><?= e($d['title']) ?></div><div class="small text-muted"><?= e($d['desc']) ?></div></div></a></div><?php endforeach; ?></div><?php endforeach; ?>
