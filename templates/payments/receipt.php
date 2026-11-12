<?php use App\Core\{Dates, Money, Ui}; use App\Services\FeeService; $rev = $p['status'] === 'reversed'; ?>
<div class="page-head"><div><h1>Receipt <?= e($p['receipt_no']) ?> <?= $rev ? Ui::badge('Reversed', 'danger') : Ui::badge('Valid', 'success') ?></h1>
  <div class="sub"><a href="<?= e(url('fees', 'statement', ['student' => $p['student_id']])) ?>"><?= e($p['student_name']) ?></a> · <?= e($p['year_name']) ?></div></div>
  <div class="d-flex gap-2"><a class="btn btn-primary btn-sm" target="_blank" href="<?= e(url('payments', 'receipt', ['id' => $p['id'], 'print' => 1])) ?>"><i class="fa-solid fa-print me-1"></i>Print receipt</a>
  <?php if (!$rev && can('payments.reverse')): ?><button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#reverseModal"><i class="fa-solid fa-rotate-left me-1"></i>Reverse</button><?php endif; ?></div></div>
<div class="card" style="max-width:720px"><div class="card-body"><dl class="kv mb-0">
  <dt>Amount</dt><dd class="fs-5 fw-bold"><?= e(money(Money::fromDb($p['amount']))) ?></dd><dt>For</dt><dd><?= e(FeeService::CATEGORIES[$p['category']]) ?></dd>
  <dt>Date</dt><dd><?= e(dmy($p['payment_date'])) ?></dd><dt>Method</dt><dd><?= e(FeeService::METHODS[$p['method']]) ?><?= $p['reference_no'] ? ' · ' . e($p['reference_no']) : '' ?></dd>
  <?php if ($p['notes']): ?><dt>Notes</dt><dd><?= e($p['notes']) ?></dd><?php endif; ?>
  <dt>Recorded by</dt><dd><?= e($p['recorded_by_name']) ?> on <?= e(Dates::displayTime($p['created_at'])) ?></dd>
  <?php if ($rev): ?><dt>Reversed</dt><dd class="text-danger"><?= e(Dates::displayTime($p['reversed_at'])) ?> by <?= e($p['reversed_by_name']) ?><br><?= e($p['reversal_reason']) ?><div class="small text-muted">The original receipt is kept for the record and is not counted in any total.</div></dd><?php endif; ?></dl></div></div>
<?php if (!$rev && can('payments.reverse')): ?>
<div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="revTitle" aria-hidden="true"><div class="modal-dialog"><form method="post" action="<?= e(url('payments', 'reverse')) ?>" class="modal-content"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id']) ?>">
  <div class="modal-header"><h5 class="modal-title" id="revTitle">Reverse receipt <?= e($p['receipt_no']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><p class="small">The receipt is marked reversed and the amount is added back to the student's balance. It is never deleted. If the amount or category was wrong, record a new correct payment afterwards.</p>
    <label class="form-label required" for="rreason">Reason</label><input class="form-control" id="rreason" name="reason" required minlength="5" maxlength="300"></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reverse payment</button></div></form></div></div>
<?php endif; ?>
