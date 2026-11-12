<?php use App\Core\Ui; use App\Services\FeeService; ?>
<div class="page-head"><div><h1>Fees &amp; dues</h1><div class="sub">Balance = academic + transport - discounts - valid payments (<?= e($year['name'] ?? '-') ?>)</div></div>
  <div class="d-flex gap-2"><?php if (can('payments.record')): ?><a class="btn btn-primary" href="<?= e(url('payments', 'record')) ?>"><i class="fa-solid fa-receipt me-1"></i>Record payment</a><?php endif; ?>
  <?php if (can('reports.finance')): ?><a class="btn btn-outline-secondary" href="<?= e(url('reports', 'run', ['r' => 'pending', 'class' => $f['class_id'], 'section' => $f['section_id'], 'fee_status' => $f['status'] === 'all' ? '' : $f['status']])) ?>">Report / export</a><?php endif; ?></div></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="fees"><input type="hidden" name="a" value="dues">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Name or admission no"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="class">Class</label><select class="form-select" id="class" name="class" data-sections-target="#section"><?= Ui::options($classes, 'id', 'name', $f['class_id'], 'All') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="section">Section</label><select class="form-select" id="section" name="section"><?= Ui::sectionOptions($sections, $f['section_id'], 'All') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="status">Show</label><select class="form-select" id="status" name="status"><?php foreach (['pending' => 'With balance due', 'paid' => 'Fully paid', 'unassessed' => 'Nothing assessed', 'all' => 'Everyone'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2 d-grid"><button class="btn btn-primary">Filter</button></div></div></form>
<?php if ($totals): ?><div class="row g-3 mb-3">
  <?php foreach ([['Students listed', number_format($totals['n']), 'bg-ao-slate', 'users'], ['Net assessed', money($totals['net']), 'bg-ao-blue', 'file-invoice'], ['Paid', money($totals['paid']), 'bg-ao-green', 'circle-check'], ['Outstanding', money(max(0, $totals['balance'])), 'bg-ao-red', 'hourglass-half']] as [$l, $v, $bg, $ic]): ?>
    <div class="col-6 col-xl-3"><div class="card"><div class="stat-card"><div class="stat-icon <?= $bg ?>"><i class="fa-solid fa-<?= $ic ?>"></i></div><div><div class="stat-value fs-5"><?= e($v) ?></div><div class="stat-label"><?= e($l) ?></div></div></div></div></div>
  <?php endforeach; ?></div><?php endif; ?>
<div class="card"><?php if (!$rows): ?><?= Ui::empty('indian-rupee-sign', 'No students match', 'Students appear here once they are enrolled in the selected year.') ?><?php else: ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Class</th><th class="text-end">Academic</th><th class="text-end">Transport</th><th class="text-end">Discount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $s = FeeService::statusLabel($r['status']); ?><tr>
    <td><a class="fw-semibold" href="<?= e(url('fees', 'statement', ['student' => $r['student_id']])) ?>"><?= e($r['full_name']) ?></a><div class="small text-muted">Adm <?= e($r['admission_no']) ?> · <?= e($r['phone_primary']) ?></div></td>
    <td><?= e($r['class_name'] . '-' . $r['section_name']) ?></td><td class="money"><?= e(money($r['academic'])) ?></td><td class="money"><?= e(money($r['transport'])) ?></td><td class="money"><?= e(money($r['discount'])) ?></td>
    <td class="money"><?= e(money($r['paid'])) ?></td><td class="money fw-semibold"><?= e(money($r['balance'])) ?></td><td><?= Ui::badge($s[0], $s[1]) ?></td>
    <td class="text-end text-nowrap"><?php if (can('payments.record') && $r['balance'] > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('payments', 'record', ['student' => $r['student_id']])) ?>">Pay</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><span class="small text-muted">Page <?= (int) $pager->page ?> of <?= (int) $pager->pages() ?></span><?= Ui::pagination($pager) ?></div><?php endif; ?></div>
