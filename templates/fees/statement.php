<?php use App\Core\{Money, Ui}; use App\Services\FeeService;
$st = FeeService::statusLabel($summary['status']);
$assessAmt = $summary['academic']['assessed'];
?>
<div class="page-head"><div><h1>Fee statement</h1><div class="sub"><a href="<?= e(url('students', 'view', ['id' => $stu['id']])) ?>"><?= e($stu['full_name']) ?></a> · Adm <?= e($stu['admission_no']) ?> · <?= $enrol ? 'Class ' . e($enrol['class_name'] . '-' . $enrol['section_name']) : '<span class="text-danger">not enrolled</span>' ?> · <?= e($year['name']) ?></div></div>
  <div class="d-flex gap-2"><?php if (can('payments.record') && $summary['total']['balance'] > 0): ?><a class="btn btn-primary btn-sm" href="<?= e(url('payments', 'record', ['student' => $stu['id']])) ?>"><i class="fa-solid fa-receipt me-1"></i>Record payment</a><?php endif; ?>
  <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= e(url('fees', 'statement', ['student' => $stu['id'], 'print' => 1])) ?>"><i class="fa-solid fa-print me-1"></i>Print</a></div></div>
<?php if (!$enrol): ?><div class="alert alert-warning">This student is not enrolled in <?= e($year['name']) ?>, so no fees are assessed for this year. Change the year in the top bar or enrol the student first.</div><?php endif; ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between">Summary <?= Ui::badge($st[0], $st[1]) ?></div>
  <div class="table-responsive"><table class="table mb-0"><thead><tr><th></th><th class="text-end">Assessed</th><th class="text-end">Discount</th><th class="text-end">Net</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead><tbody>
  <?php foreach (['academic' => 'Academic fee', 'transport' => 'Transport fee', 'total' => 'Total'] as $k => $l): $r = $summary[$k]; ?><tr class="<?= $k === 'total' ? 'fw-bold table-light' : '' ?>"><td><?= e($l) ?></td>
    <td class="money"><?= e(money($r['assessed'])) ?></td><td class="money"><?= e(money($r['discount'])) ?></td><td class="money"><?= e(money($r['net'])) ?></td><td class="money"><?= e(money($r['paid'])) ?></td><td class="money"><?= e(money($r['balance'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header">Payments (<?= e($year['name']) ?>)</div>
      <?php if (!$payments): ?><?= Ui::empty('receipt', 'No payments yet') ?><?php else: ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Receipt</th><th>For</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
        <?php foreach ($payments as $p): ?><tr class="<?= $p['status'] === 'reversed' ? 'reversed' : '' ?>"><td><?= e(dmy($p['payment_date'])) ?></td><td><a href="<?= e(url('payments', 'receipt', ['id' => $p['id']])) ?>"><?= e($p['receipt_no']) ?></a></td>
          <td><?= e(FeeService::CATEGORIES[$p['category']]) ?></td><td class="money"><?= e(money(Money::fromDb($p['amount']))) ?></td><td><?= $p['status'] === 'reversed' ? Ui::badge('Reversed', 'danger') : '' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <?php if ($transport): ?><div class="card"><div class="card-header">Transport this year</div><ul class="list-group list-group-flush">
      <?php foreach ($transport as $t): ?><li class="list-group-item d-flex justify-content-between"><span>Bus <?= e($t['bus_code']) ?> · <?= e($t['pickup_location']) ?> <?= Ui::badge(ucfirst($t['status']), ['active' => 'success', 'ended' => 'secondary', 'cancelled' => 'danger'][$t['status']]) ?></span><span class="money"><?= e(money(Money::fromDb($t['fee']))) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
  </div>
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header">Discounts / concessions</div>
      <?php if ($adjustments): ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>For</th><th class="text-end">Amount</th><th>Reason</th><th></th></tr></thead><tbody>
        <?php foreach ($adjustments as $a): ?><tr class="<?= $a['status'] === 'void' ? 'reversed' : '' ?>"><td><?= e(dmy($a['created_at'])) ?></td><td><?= e(FeeService::CATEGORIES[$a['category']]) ?></td><td class="money"><?= e(money(Money::fromDb($a['amount']))) ?></td>
          <td class="small"><?= e($a['reason']) ?><?= $a['status'] === 'void' ? '<div>Voided: ' . e($a['void_reason']) . '</div>' : '' ?></td>
          <td class="text-end"><?php if ($a['status'] === 'active' && can('fees.assess')): ?><button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidModal" data-fill-modal="voidModal" data-adj="<?= e($a['id']) ?>" type="button">Void</button><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
      <?php else: ?><div class="card-body text-muted">No discounts.</div><?php endif; ?>
      <?php if (can('fees.assess') && $enrol): ?><div class="card-footer bg-white"><form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="op" value="discount">
        <div class="col-6"><label class="form-label small mb-0" for="dcat">Applies to</label><select class="form-select form-select-sm" id="dcat" name="category"><?php foreach (FeeService::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small mb-0" for="damt">Amount (₹)</label><input class="form-control form-control-sm" id="damt" name="amount" inputmode="decimal" required></div>
        <div class="col-12"><label class="form-label small mb-0" for="dreason">Reason</label><input class="form-control form-control-sm" id="dreason" name="reason" required maxlength="200" placeholder="Sibling concession, scholarship..."></div>
        <div class="col-12"><button class="btn btn-sm btn-primary">Add discount</button></div></form></div><?php endif; ?></div>
    <?php if (can('fees.assess') && $enrol): ?><div class="card"><div class="card-header">Adjust academic fee assessment</div><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="op" value="assessment">
      <div class="col-5"><label class="form-label small mb-0" for="aamt">Academic fee (₹)</label><input class="form-control form-control-sm" id="aamt" name="amount" inputmode="decimal" required value="<?= e(Money::toDb($assessAmt)) ?>"></div>
      <div class="col-7"><label class="form-label small mb-0" for="areason">Reason</label><input class="form-control form-control-sm" id="areason" name="reason" required maxlength="200"></div>
      <div class="col-12"><button class="btn btn-sm btn-outline-primary">Update assessment</button><span class="form-text ms-2">Recorded in the audit log with the old and new amount.</span></div></form></div></div><?php endif; ?>
  </div>
</div>
<div class="modal fade" id="voidModal" tabindex="-1" aria-labelledby="voidTitle" aria-hidden="true"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="op" value="void"><input type="hidden" name="adj_id" data-target="adj">
  <div class="modal-header"><h5 class="modal-title" id="voidTitle">Void discount</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><label class="form-label required" for="vreason">Reason</label><input class="form-control" id="vreason" name="reason" required maxlength="200"><div class="form-text">The discount stays in the history but no longer reduces the balance.</div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Void discount</button></div></form></div></div>
