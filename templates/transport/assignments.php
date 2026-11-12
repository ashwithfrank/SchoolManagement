<?php use App\Core\{Money, Ui}; ?>
<div class="page-head"><div><h1>Student transport</h1><div class="sub">Each student has their own bus fee for the year (<?= e($year['name'] ?? '-') ?>). Two students on one bus can pay different amounts.</div></div>
  <?php if (can('transport.assign')): ?><a class="btn btn-primary" href="<?= e(url('transport', 'assign')) ?>"><i class="fa-solid fa-plus me-1"></i>Assign student to bus</a><?php endif; ?></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="transport"><input type="hidden" name="a" value="assignments">
  <div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Student, admission no, pickup point"></div>
    <div class="col-md-3"><label class="form-label" for="bus">Bus</label><select class="form-select" id="bus" name="bus"><?= Ui::options($buses, 'id', 'bus_code', $f['bus_id'], 'All buses') ?></select></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><?php foreach (['active' => 'Active', 'ended' => 'Ended', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Filter</button></div></div></form>
<div class="card"><?php if (!$rows): ?><?= Ui::empty('route', 'No transport assignments found') ?><?php else: ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Class</th><th>Bus</th><th>Pickup / drop</th><th>Km</th><th class="text-end">Fee</th><th>From</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr class="<?= $r['status'] === 'cancelled' ? 'reversed' : '' ?>">
    <td><a href="<?= e(url('students', 'view', ['id' => $r['student_id'], 'tab' => 'transport'])) ?>"><?= e($r['student_name']) ?></a><div class="small text-muted">Adm <?= e($r['admission_no']) ?></div></td>
    <td><?= e($r['class_name'] ? $r['class_name'] . '-' . $r['section_name'] : '') ?></td><td><?= e($r['bus_code']) ?></td><td><?= e($r['pickup_location']) ?></td><td><?= e(\App\Core\Marks::display($r['distance_km'])) ?></td>
    <td class="money"><?= e(money(Money::fromDb($r['fee']))) ?></td><td><?= e(dmy($r['start_date'])) ?><?= $r['end_date'] ? '<div class="small text-muted">to ' . e(dmy($r['end_date'])) . '</div>' : '' ?></td>
    <td><?= Ui::badge(ucfirst($r['status']), ['active' => 'success', 'ended' => 'secondary', 'cancelled' => 'danger'][$r['status']]) ?></td>
    <td class="text-end"><?php if (can('transport.assign') && $r['status'] !== 'cancelled'): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('transport', 'assignment', ['id' => $r['id']])) ?>">Manage</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><span class="small text-muted"><?= number_format($pager->total) ?> records</span><?= Ui::pagination($pager) ?></div><?php endif; ?></div>
