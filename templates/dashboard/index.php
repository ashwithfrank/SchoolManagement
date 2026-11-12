<?php
use App\Core\{Dates, Money};
use App\Services\FeeService;
/** @var array $d */
$year = $d['year'];
$cards = [];
$cards[] = ['fa-user-graduate', 'bg-ao-blue', number_format($d['students']), 'Active students' . ($year ? ' (' . $year['name'] . ')' : '')];
$cards[] = ['fa-id-badge', 'bg-ao-purple', number_format($d['staff']), 'Active staff'];
$cards[] = ['fa-calendar-check', 'bg-ao-slate', $d['active_year']['name'] ?? 'Not set', 'Active academic year'];
if (isset($d['collected'])) {
    $cards[] = ['fa-indian-rupee-sign', 'bg-ao-green', money($d['collected']), 'Fees collected' . ($year ? ' (' . $year['name'] . ')' : '')];
    $cards[] = ['fa-hourglass-half', 'bg-ao-red', money($d['outstanding']), 'Outstanding fees'];
}
if (isset($d['transport_students'])) {
    $cards[] = ['fa-bus', 'bg-ao-orange', number_format($d['transport_students']), 'Students using school transport'];
}
?>
<div class="page-head">
  <div><h1>Dashboard</h1><div class="sub">Live figures from the database<?= $year ? ' for ' . e($year['name']) : '' ?>.</div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('students.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('students', 'form')) ?>"><i class="fa-solid fa-user-plus me-1"></i>Admit student</a><?php endif; ?>
    <?php if (can('payments.record')): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('payments', 'record')) ?>"><i class="fa-solid fa-receipt me-1"></i>Record payment</a><?php endif; ?>
    <?php if (can('marks.enter')): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('exams', 'marks')) ?>"><i class="fa-solid fa-pen-to-square me-1"></i>Enter marks</a><?php endif; ?>
    <?php if (can('backups.manage')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('backups')) ?>"><i class="fa-solid fa-database me-1"></i>Backup</a><?php endif; ?>
  </div>
</div>

<?php if ($d['sections_assigned'] === 0): ?>
  <div class="alert alert-info">You are not assigned to any class or section for this year yet. Ask the administrator to assign your subjects (Employees) and link your login to your employee record (Users).</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <?php foreach ($cards as [$icon, $bg, $val, $label]): ?>
    <div class="col-12 col-sm-6 col-xl-4"><div class="card"><div class="stat-card">
      <div class="stat-icon <?= e($bg) ?>"><i class="fa-solid <?= e($icon) ?>"></i></div>
      <div><div class="stat-value"><?= e($val) ?></div><div class="stat-label"><?= e($label) ?></div></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<?php if (!empty($d['bus_alerts'])): ?>
<div class="card mb-3 border-warning"><div class="card-header"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Bus document alerts</div>
  <div class="list-group list-group-flush">
    <?php foreach ($d['bus_alerts'] as $a): ?>
      <a class="list-group-item list-group-item-action d-flex justify-content-between" href="<?= e(url('transport', 'bus_form', ['id' => $a['bus']['id']])) ?>">
        <span><b><?= e($a['bus']['bus_code']) ?></b> (<?= e($a['bus']['vehicle_no']) ?>) - <?= e($a['doc']) ?> <?= $a['state'] === 'expired' ? 'expired' : 'expires' ?> on <?= e(dmy($a['date'])) ?></span>
        <?= $a['state'] === 'expired' ? '<span class="badge text-bg-danger">Expired ' . abs($a['days']) . ' day(s) ago</span>' : '<span class="badge text-bg-warning">In ' . (int) $a['days'] . ' day(s)</span>' ?>
      </a>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>

<div class="row g-3">
  <?php if (isset($d['recent_admissions'])): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Recent admissions</div>
    <?php if (!$d['recent_admissions']): ?><?= \App\Core\Ui::empty('user-plus', 'No students yet', 'Admitted students will appear here.') ?>
    <?php else: ?><div class="table-responsive"><table class="table table-hover mb-0"><tbody>
      <?php foreach ($d['recent_admissions'] as $s): ?>
        <tr><td><a href="<?= e(url('students', 'view', ['id' => $s['id']])) ?>"><?= e($s['full_name']) ?></a><div class="small text-muted">Adm <?= e($s['admission_no']) ?></div></td>
            <td><?= e($s['class_name'] ? $s['class_name'] . '-' . $s['section_name'] : 'Not enrolled') ?></td><td class="text-end text-muted small"><?= e(dmy($s['admission_date'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div>
  <?php endif; ?>

  <?php if (isset($d['recent_payments'])): ?>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Recent payments</div>
    <?php if (!$d['recent_payments']): ?><?= \App\Core\Ui::empty('receipt', 'No payments recorded yet', 'Receipts will appear here.') ?>
    <?php else: ?><div class="table-responsive"><table class="table table-hover mb-0"><tbody>
      <?php foreach ($d['recent_payments'] as $p): ?>
        <tr class="<?= $p['status'] === 'reversed' ? 'reversed' : '' ?>"><td><a href="<?= e(url('payments', 'receipt', ['id' => $p['id']])) ?>"><?= e($p['receipt_no']) ?></a><div class="small text-muted"><?= e($p['student_name']) ?></div></td>
            <td class="small"><?= e(FeeService::CATEGORIES[$p['category']] ?? '') ?></td><td class="money"><?= e(money(Money::fromDb($p['amount']))) ?></td><td class="text-muted small"><?= e(dmy($p['payment_date'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div>
  <?php endif; ?>

  <?php if (isset($d['recent_activity'])): ?>
  <div class="col-12"><div class="card"><div class="card-header d-flex justify-content-between">Recent activity <a class="small fw-normal" href="<?= e(url('audit')) ?>">View audit log</a></div>
    <?php if (!$d['recent_activity']): ?><?= \App\Core\Ui::empty('clipboard-list', 'No activity yet') ?>
    <?php else: ?><div class="table-responsive"><table class="table mb-0"><tbody>
      <?php foreach ($d['recent_activity'] as $a): ?>
        <tr><td class="text-muted small text-nowrap"><?= e(Dates::displayTime($a['created_at'])) ?></td><td><?= e($a['username'] ?? '-') ?></td><td><code><?= e($a['action']) ?></code></td><td class="small text-muted"><?= e($a['entity_type'] ? $a['entity_type'] . ' #' . $a['entity_id'] : '') ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>
