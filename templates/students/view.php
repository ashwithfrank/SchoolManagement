<?php
use App\Core\{Dates, Marks, Money, Ui};
use App\Services\{FeeService, TransportService};
$addr = array_filter([$s['addr_house'], $s['addr_street'], $s['addr_locality'], $s['addr_village'], $s['addr_post_office'] ? 'PO ' . $s['addr_post_office'] : null,
    $s['addr_taluk'] ? 'Taluk ' . $s['addr_taluk'] : null, $s['addr_district'], $s['addr_state'], $s['addr_pin']]);
$self = fn (string $t) => url('students', 'view', ['id' => $s['id'], 'tab' => $t]);
?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <?php if ($s['photo_file']): ?><img class="avatar" style="width:56px;height:56px" alt="" src="<?= e(base_path() . '/photo.php?id=' . $s['id'] . '&v=' . urlencode($s['photo_file'])) ?>"><?php else: ?><span class="avatar" style="width:56px;height:56px"><i class="fa-solid fa-user fa-lg"></i></span><?php endif; ?>
    <div><h1><?= e($s['full_name']) ?> <?= Ui::status((bool) $s['is_active']) ?></h1>
      <div class="sub"><?= e($s['student_code']) ?> · Adm <?= e($s['admission_no']) ?><?= $current ? ' · ' . e($current['class_name'] . '-' . $current['section_name']) . ' (' . e($year['name']) . ')' : '' ?></div></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('students', 'form', ['id' => $s['id']])) ?>"><i class="fa-solid fa-pen me-1"></i>Edit</a>
    <?php if (can('fees.view') && $year && $current): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('fees', 'statement', ['student' => $s['id']])) ?>"><i class="fa-solid fa-file-invoice me-1"></i>Fee statement</a><?php endif; ?>
    <?php if (can('students.manage')): ?>
      <?= Ui::postForm(url('students', 'toggle'), ['id' => $s['id'], 'active' => $s['is_active'] ? 0 : 1], $s['is_active'] ? 'Mark inactive' : 'Mark active', 'btn btn-sm btn-outline-secondary', $s['is_active'] ? 'Mark this student inactive? History is kept.' : null) ?>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $k === $tab ? ' active' : '' ?>" href="<?= e($self($k)) ?>"><?= e($l) ?></a></li><?php endforeach; ?>
</ul>

<?php if ($tab === 'profile'): ?>
<div class="row g-3">
  <div class="col-lg-3">
    <div class="card"><div class="card-body text-center">
      <?php if ($s['photo_file']): ?><img class="photo-lg mx-auto mb-3" alt="Photo of <?= e($s['full_name']) ?>" src="<?= e(base_path() . '/photo.php?id=' . $s['id'] . '&v=' . urlencode($s['photo_file'])) ?>">
      <?php else: ?><div class="photo-lg mx-auto mb-3"><i class="fa-solid fa-user"></i></div><?php endif; ?>
      <?php if (can('students.manage')): ?>
      <form method="post" action="<?= e(url('students', 'photo')) ?>" enctype="multipart/form-data" class="text-start">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>"><input type="hidden" name="op" value="upload">
        <label class="form-label small" for="photo"><?= $s['photo_file'] ? 'Replace photo' : 'Upload photo' ?></label>
        <input class="form-control form-control-sm mb-2" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn btn-sm btn-primary w-100">Upload</button><div class="form-text">JPEG, PNG or WebP, up to <?= e(\App\Core\Settings::get('photo_max_mb', '5')) ?> MB.</div>
      </form>
      <?php if ($s['photo_file']): ?><div class="mt-2"><?= Ui::postForm(url('students', 'photo'), ['id' => $s['id'], 'op' => 'remove'], 'Remove photo', 'btn btn-sm btn-outline-danger w-100', 'Remove this photo?') ?></div><?php endif; ?>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-9">
    <div class="card mb-3"><div class="card-header">Student</div><div class="card-body"><dl class="kv mb-0">
      <dt>Student ID</dt><dd><?= e($s['student_code']) ?></dd><dt>Admission number</dt><dd><?= e($s['admission_no']) ?></dd>
      <dt>SATS number</dt><dd><?= e($s['sats_no'] ?: '-') ?></dd><dt>Gender</dt><dd><?= e(ucfirst($s['gender'])) ?></dd>
      <dt>Date of birth</dt><dd><?= e(dmy($s['date_of_birth'])) ?></dd><dt>Date of admission</dt><dd><?= e(dmy($s['admission_date'])) ?></dd>
      <?php if ($s['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($s['notes'])) ?></dd><?php endif; ?>
    </dl></div></div>
    <div class="card mb-3"><div class="card-header">Family and contact</div><div class="card-body"><dl class="kv mb-0">
      <dt>Father</dt><dd><?= e($s['father_name'] ?: '-') ?></dd><dt>Mother</dt><dd><?= e($s['mother_name'] ?: '-') ?></dd><dt>Guardian</dt><dd><?= e($s['guardian_name'] ?: '-') ?></dd>
      <dt>Primary phone</dt><dd><?= e($s['phone_primary']) ?></dd><dt>Secondary phone</dt><dd><?= e($s['phone_secondary'] ?: '-') ?></dd><dt>Email</dt><dd><?= e($s['email'] ?: '-') ?></dd>
      <dt>Address</dt><dd><?= $addr ? e(implode(', ', $addr)) : '-' ?></dd>
    </dl></div></div>
  </div>
</div>

<?php elseif ($tab === 'enrollment'): ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header">Enrollment history</div>
    <?php if (!$enrollments): ?><?= Ui::empty('school', 'Not enrolled in any year yet') ?>
    <?php else: ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Year</th><th>Class</th><th>Section</th><th>Roll</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($enrollments as $e): ?><tr><td><?= e($e['year_name']) ?></td><td><?= e($e['class_name']) ?></td><td><?= e($e['section_name']) ?></td><td><?= e($e['roll_no']) ?></td>
        <td><?= Ui::badge(ucfirst($e['status']), ['active' => 'success', 'promoted' => 'primary', 'repeated' => 'warning', 'completed' => 'info', 'left' => 'secondary'][$e['status']] ?? 'secondary') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <div class="card-footer bg-white small text-muted">Admitted on <?= e(dmy($s['admission_date'])) ?>. Earlier years are kept untouched when students are promoted.</div>
  </div></div>
  <?php if (can('students.manage') && $year): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header"><?= $current ? 'Change class / section for ' . e($year['name']) : 'Enrol in ' . e($year['name']) ?></div><div class="card-body">
    <form method="post" action="<?= e(url('students', 'enrol')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="student_id" value="<?= e($s['id']) ?>"><input type="hidden" name="op" value="<?= $current ? 'change' : 'enrol' ?>"><input type="hidden" name="year_id" value="<?= e($year['id']) ?>">
      <div class="mb-3"><label class="form-label required" for="class_id">Class</label><select class="form-select" id="class_id" name="class_id" required data-sections-target="#section_id"><?= Ui::options($classes, 'id', 'name', $current['class_id'] ?? null, 'Select class') ?></select></div>
      <div class="mb-3"><label class="form-label required" for="section_id">Section</label><select class="form-select" id="section_id" name="section_id" required><?= Ui::sectionOptions($sections, $current['section_id'] ?? null, 'Select section') ?></select></div>
      <div class="mb-3"><label class="form-label" for="roll_no">Roll number (optional)</label><input class="form-control" id="roll_no" name="roll_no" value="<?= e($current['roll_no'] ?? '') ?>" inputmode="numeric" maxlength="5"></div>
      <button class="btn btn-primary"><?= $current ? 'Save change' : 'Enrol student' ?></button>
    </form>
  </div></div></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'fees'): ?>
  <?php if (!$year || !$current): ?><div class="card"><?= Ui::empty('indian-rupee-sign', 'Not enrolled in ' . ($year['name'] ?? 'the selected year'), 'Fees are assessed per academic year after enrollment.') ?></div>
  <?php else: $st = FeeService::statusLabel($summary['status']); ?>
  <div class="card"><div class="card-header d-flex justify-content-between">Fees for <?= e($year['name']) ?> <span><?= Ui::badge($st[0], $st[1]) ?></span></div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th></th><th class="text-end">Assessed</th><th class="text-end">Discount</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead><tbody>
      <?php foreach (['academic' => 'Academic fee', 'transport' => 'Transport fee', 'total' => 'Total'] as $k => $l): $r = $summary[$k]; ?>
        <tr class="<?= $k === 'total' ? 'fw-bold table-light' : '' ?>"><td><?= e($l) ?></td><td class="money"><?= e(money($r['assessed'])) ?></td><td class="money"><?= e(money($r['discount'])) ?></td><td class="money"><?= e(money($r['paid'])) ?></td><td class="money"><?= e(money($r['balance'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <div class="card-footer bg-white"><a href="<?= e(url('fees', 'statement', ['student' => $s['id']])) ?>">Open full fee statement</a></div></div>
  <?php endif; ?>

<?php elseif ($tab === 'payments'): ?>
<div class="card"><?php if (!$payments): ?><?= Ui::empty('receipt', 'No payments recorded', 'New students start with zero payments.') ?>
  <?php else: ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Receipt</th><th>Year</th><th>Category</th><th>Method</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr class="<?= $p['status'] === 'reversed' ? 'reversed' : '' ?>"><td><?= e(dmy($p['payment_date'])) ?></td>
      <td><a href="<?= e(url('payments', 'receipt', ['id' => $p['id']])) ?>"><?= e($p['receipt_no']) ?></a></td><td><?= e($p['year_name']) ?></td><td><?= e(FeeService::CATEGORIES[$p['category']]) ?></td>
      <td><?= e(FeeService::METHODS[$p['method']]) ?></td><td class="money"><?= e(money(Money::fromDb($p['amount']))) ?></td><td><?= $p['status'] === 'valid' ? Ui::badge('Valid', 'success') : Ui::badge('Reversed', 'danger') ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div>

<?php elseif ($tab === 'transport'): ?>
<div class="card"><?php if (!$transport): ?><?= Ui::empty('bus', 'No transport history', 'This student has never been assigned to a bus.') ?>
  <?php else: ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Year</th><th>Bus</th><th>Pickup / drop</th><th>Distance</th><th>From</th><th>To</th><th class="text-end">Fee</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($transport as $t): ?><tr class="<?= $t['status'] === 'cancelled' ? 'reversed' : '' ?>"><td><?= e($t['year_name']) ?></td><td><?= e($t['bus_code'] . ' (' . $t['vehicle_no'] . ')') ?></td><td><?= e($t['pickup_location']) ?></td>
      <td><?= $t['distance_km'] !== null ? e(Marks::display($t['distance_km'])) . ' km' : '' ?></td><td><?= e(dmy($t['start_date'])) ?></td><td><?= e(dmy($t['end_date'])) ?></td>
      <td class="money"><?= e(money(Money::fromDb($t['fee']))) ?></td><td><?= Ui::badge(ucfirst($t['status']), ['active' => 'success', 'ended' => 'secondary', 'cancelled' => 'danger'][$t['status']]) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div>

<?php elseif ($tab === 'results'): ?>
  <?php if (!$results): ?><div class="card"><?= Ui::empty('pen-to-square', 'No exam results yet', 'Results appear once exams and papers exist for the student\'s classes.') ?></div>
  <?php else: foreach ($results as $g): $o = $g['overall']; ?>
  <div class="card mb-3"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><span><?= e($g['exam_name']) ?> <span class="text-muted fw-normal">· <?= e($g['year_name']) ?> · Class <?= e($g['class_name']) ?></span></span>
    <span><?= $o['percentage'] !== null ? number_format($o['percentage'], 2) . '% · ' : '' ?><?= Ui::badge($o['result'], ['Pass' => 'success', 'Fail' => 'danger'][$o['result']] ?? 'secondary') ?></span></div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Subject</th><th class="text-end">Marks</th><th class="text-end">Out of</th><th class="text-end">Pass mark</th><th class="text-end">%</th><th>Result</th></tr></thead><tbody>
      <?php foreach ($g['lines'] as $l): $e = $l['eval']; ?><tr><td><?= e($l['subject']) ?></td>
        <td class="money"><?= $e['state'] === 'absent' ? 'Absent' : ($e['state'] === 'missing' ? '<span class="text-muted">Not entered</span>' : e(Marks::display($l['scored']))) ?></td>
        <td class="money"><?= e(Marks::display($l['max'])) ?></td><td class="money"><?= e(Marks::display($l['min'])) ?></td><td class="money"><?= $e['percentage'] !== null ? e(number_format($e['percentage'], 2)) : '' ?></td>
        <td><?= Ui::badge($e['result'], ['Pass' => 'success', 'Fail' => 'danger', 'Absent' => 'warning'][$e['result']] ?? 'secondary') ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <?php endforeach; endif; ?>
<?php endif; ?>
