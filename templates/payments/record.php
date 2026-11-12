<?php use App\Core\{Money, View}; use App\Services\FeeService; ?>
<div class="page-head"><div><h1>Record payment</h1><div class="sub">Academic year <?= e($year['name']) ?>. Partial payments are allowed; a payment cannot exceed the balance.</div></div><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('payments', 'list')) ?>">Back</a></div>
<?php if (!$stu): ?>
<form class="card" method="get" action="<?= e(base_path() . '/index.php') ?>" style="max-width:640px"><div class="card-body"><input type="hidden" name="m" value="payments"><input type="hidden" name="a" value="record">
  <?= View::partial('components/student_picker', ['name' => 'student', 'label' => 'Find the student', 'selected' => null, 'required' => true]) ?></div>
  <div class="card-footer bg-white"><button class="btn btn-primary">Continue</button></div></form>
<?php else: ?>
<div class="row g-3">
  <div class="col-lg-5"><div class="card"><div class="card-header"><?= e($stu['full_name']) ?> <span class="text-muted fw-normal">Adm <?= e($stu['admission_no']) ?></span></div>
    <?php if (!$enrol): ?><div class="card-body"><div class="alert alert-warning mb-0">Not enrolled in <?= e($year['name']) ?>, so no fees apply. Switch the year (top bar) or enrol the student.</div></div>
    <?php else: ?><div class="card-body small text-muted pb-0">Class <?= e($enrol['class_name'] . '-' . $enrol['section_name']) ?></div>
    <table class="table mb-0"><tbody>
      <?php foreach (['academic', 'transport'] as $c): $r = $summary[$c]; ?><tr><td><?= e(FeeService::CATEGORIES[$c]) ?><div class="small text-muted">Net <?= e(money($r['net'])) ?> · paid <?= e(money($r['paid'])) ?></div></td><td class="money fw-semibold"><?= e(money($r['balance'])) ?></td></tr><?php endforeach; ?>
      <tr class="table-light fw-bold"><td>Total balance</td><td class="money"><?= e(money($summary['total']['balance'])) ?></td></tr></tbody></table><?php endif; ?></div></div>
  <?php if ($enrol): ?><div class="col-lg-7"><form method="post" class="card"><div class="card-body"><?= csrf_field() ?><?= idem_field() ?><div class="row g-3">
    <div class="col-md-6"><label class="form-label required" for="category">Fee category</label><select class="form-select" id="category" name="category" required>
      <?php foreach (FeeService::CATEGORIES as $k => $l): if ($summary[$k]['net'] <= 0) { continue; } ?><option value="<?= e($k) ?>"<?= selected($k, $d['category'] ?? '') ?>><?= e($l) ?> (due <?= e(money(max(0, $summary[$k]['balance']))) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label required" for="amount">Amount (₹)</label><input class="form-control" id="amount" name="amount" inputmode="decimal" required value="<?= e($d['amount'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label required" for="payment_date">Payment date</label><input class="form-control" type="date" id="payment_date" name="payment_date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($d['payment_date'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label required" for="method">Method</label><select class="form-select" id="method" name="method" required><?php foreach (FeeService::METHODS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $d['method'] ?? 'cash') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12" data-hide-when-source="#method" data-hide-when-value="cash"><label class="form-label" for="reference_no">Reference number <span class="text-muted">(required for non-cash)</span></label><input class="form-control" id="reference_no" name="reference_no" maxlength="60" value="<?= e($d['reference_no'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="notes">Notes (optional)</label><input class="form-control" id="notes" name="notes" maxlength="300" value="<?= e($d['notes'] ?? '') ?>"></div>
  </div></div><div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Record payment</button><span class="form-text ms-2">A receipt is created once; refreshing or double-clicking cannot repeat it.</span></div></form></div><?php endif; ?>
</div>
<?php endif; ?>
