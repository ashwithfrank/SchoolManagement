<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Section coordinators</h1><div class="sub">Assignments are per academic year (<?= e($year['name']) ?>).</div></div></div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><?php if (!$rows): ?><?= Ui::empty('user-tie', 'No coordinators assigned for this year') ?><?php else: ?>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Class</th><th>Section</th><th>Coordinator</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= e($r['class_name']) ?></td><td><?= e($r['section_name']) ?></td><td><?= e($r['employee_name']) ?> <span class="text-muted small"><?= e($r['employee_code']) ?></span></td>
      <td class="text-end"><?php if (can('academics.manage')): ?><?= Ui::postForm(url('academics', 'coordinators'), ['op' => 'remove', 'id' => $r['id']], 'Remove', 'btn btn-sm btn-outline-danger', 'Remove this coordinator?') ?><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php if (can('academics.manage')): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header">Assign / change coordinator</div><div class="card-body">
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label required" for="section_id">Section</label><select class="form-select" id="section_id" name="section_id" required><?= Ui::sectionOptions($sections, null, 'Select section') ?></select></div>
      <div class="mb-3"><label class="form-label required" for="employee_id">Employee</label><select class="form-select" id="employee_id" name="employee_id" required><option value="">Select employee</option>
        <?php foreach ($employees as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['full_name'] . ' (' . $e['employee_code'] . ')') ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary">Save</button>
    </form></div></div></div>
  <?php endif; ?>
</div>
