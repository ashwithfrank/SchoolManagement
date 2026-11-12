<?php use App\Core\Ui;
$secByClass = [];
foreach ($sections as $s) { $secByClass[(int) $s['class_id']][] = $s; }
?>
<div class="page-head"><div><h1>Promote students</h1><div class="sub">Preview first. Promotion adds a new enrollment in the target year and keeps every earlier record unchanged.</div></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('academics', 'years')) ?>">Academic years</a></div>

<?php if ($results): ?>
<div class="card mb-3"><div class="card-header">Promotion summary</div>
  <?php $ok = count(array_filter($results, fn ($r) => $r['ok'])); ?>
  <div class="card-body pb-0"><span class="badge text-bg-success"><?= $ok ?> succeeded</span> <span class="badge text-bg-danger"><?= count($results) - $ok ?> failed</span></div>
  <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Student</th><th>Outcome</th></tr></thead><tbody>
    <?php foreach ($results as $r): ?><tr><td><?= e($r['name']) ?></td><td class="<?= $r['ok'] ? '' : 'text-danger' ?>"><?= e($r['msg']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>">
  <input type="hidden" name="m" value="academics"><input type="hidden" name="a" value="promotion">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label required" for="from">Promote from year</label><select class="form-select" id="from" name="from" required><?= Ui::options($years, 'id', 'name', $from, 'Select') ?></select></div>
    <div class="col-md-3"><label class="form-label required" for="to">Into year</label><select class="form-select" id="to" name="to" required><?= Ui::options($years, 'id', 'name', $to, 'Select') ?></select></div>
    <div class="col-md-3"><label class="form-label" for="class">Only class</label><select class="form-select" id="class" name="class"><?= Ui::options($classes, 'id', 'name', $class, 'All classes') ?></select></div>
    <div class="col-md-3 d-grid"><button class="btn btn-primary">Preview</button></div>
  </div>
</form>

<?php if ($rows !== null): ?>
  <?php if (!$rows): ?><div class="card"><?= Ui::empty('turn-up', 'No active students to promote', 'Only active enrollments of active students in the source year are listed.') ?></div>
  <?php else: ?>
  <form method="post" data-confirm="Apply these promotions? Each student is processed separately and earlier records are kept."><?= csrf_field() ?>
    <input type="hidden" name="step" value="confirm"><input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
    <div class="alert alert-info py-2 small">Class 10 students have no next class in this system, so they default to <b>Mark completed</b> (or choose Repeat). Transport assignments are not copied; assign buses again for the new year. Fees for the new year are assessed from its fee structure.</div>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Student</th><th>Now</th><th>Action</th><th>Section in new year</th><th>Note</th></tr></thead><tbody>
      <?php foreach ($rows as $r): $eid = (int) $r['enrollment_id']; $targetClass = $r['next_class_id'] ?: (int) $r['class_id']; ?>
        <tr class="<?= $r['flag'] ? 'table-warning' : '' ?>">
          <td><?= e($r['full_name']) ?><div class="small text-muted">Adm <?= e($r['admission_no']) ?></div></td>
          <td><?= e($r['class_name'] . '-' . $r['section_name']) ?></td>
          <td><select class="form-select form-select-sm" name="row[<?= $eid ?>][action]" aria-label="Action for <?= e($r['full_name']) ?>">
            <?php foreach ([['promote', 'Promote to ' . ($r['next_class_name'] ?? '-')], ['repeat', 'Repeat ' . $r['class_name']], ['complete', 'Mark completed (finished Class 10)'], ['skip', 'Skip']] as [$k, $l]):
              if ($k === 'promote' && !$r['next_class_id']) { continue; } if ($k === 'complete' && $r['next_class_id']) { continue; } ?>
              <option value="<?= e($k) ?>"<?= selected($k, $r['action']) ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
          <td><select class="form-select form-select-sm" name="row[<?= $eid ?>][section]" aria-label="Section for <?= e($r['full_name']) ?>">
            <?php foreach ($secByClass[$targetClass] ?? [] as $s): ?><option value="<?= e($s['id']) ?>"<?= selected($s['id'], $r['target_section_id']) ?>><?= e($s['class_name'] . '-' . $s['name']) ?></option><?php endforeach; ?></select>
            <div class="form-text">Used when promoting. Repeaters stay in their current section.</div></td>
          <td class="small"><?= e($r['flag']) ?></td>
        </tr>
      <?php endforeach; ?></tbody></table></div>
      <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa-solid fa-turn-up me-1"></i>Apply promotion (<?= count($rows) ?> students listed)</button></div></div>
  </form>
  <?php endif; ?>
<?php endif; ?>
