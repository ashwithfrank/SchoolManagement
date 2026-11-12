<?php use App\Core\{Money, Settings}; use App\Services\FeeService; $rev = $p['status'] === 'reversed'; ?>
<div class="receipt">
  <div class="text-center mb-3"><h2><?= e(Settings::get('school_name')) ?></h2><div class="small"><?= e(Settings::get('school_address')) ?><?= Settings::get('school_phone') ? ' · Ph: ' . e(Settings::get('school_phone')) : '' ?></div>
    <div class="rcpt-title mt-2">Fee receipt</div></div>
  <div class="d-flex justify-content-between mb-2"><div><b>Receipt no:</b> <?= e($p['receipt_no']) ?></div><div><b>Date:</b> <?= e(dmy($p['payment_date'])) ?></div></div>
  <table class="table table-sm table-borderless mb-2"><tbody>
    <tr><td style="width:30%">Student</td><td><b><?= e($p['student_name']) ?></b> (Adm <?= e($p['admission_no']) ?>)</td></tr>
    <tr><td>Class</td><td><?= e($p['class_name'] ? $p['class_name'] . '-' . $p['section_name'] : '-') ?> · <?= e($p['year_name']) ?></td></tr>
    <tr><td>Parent / guardian</td><td><?= e($p['father_name'] ?: $p['guardian_name']) ?></td></tr>
    <tr><td>Fee category</td><td><?= e(FeeService::CATEGORIES[$p['category']]) ?></td></tr>
    <tr><td>Payment method</td><td><?= e(FeeService::METHODS[$p['method']]) ?><?= $p['reference_no'] ? ' · Ref: ' . e($p['reference_no']) : '' ?></td></tr>
    <?php if ($p['notes']): ?><tr><td>Notes</td><td><?= e($p['notes']) ?></td></tr><?php endif; ?></tbody></table>
  <div class="border border-dark p-2 d-flex justify-content-between align-items-center"><span class="fw-bold">Amount received</span><span class="fs-4 fw-bold"><?= e(money(Money::fromDb($p['amount']))) ?></span></div>
  <?php if ($rev): ?><div class="text-center my-3"><span class="stamp-reversed">REVERSED</span><div class="small mt-2">Reversed on <?= e(\App\Core\Dates::displayTime($p['reversed_at'])) ?> - <?= e($p['reversal_reason']) ?></div></div><?php endif; ?>
  <div class="d-flex justify-content-between mt-4 small"><div>Received by: <?= e($p['recorded_by_name']) ?></div><div class="text-end">Authorised signature<br><br>__________________</div></div>
  <div class="text-center small text-muted mt-3"><?= e(Settings::get('receipt_footer')) ?></div>
</div>
