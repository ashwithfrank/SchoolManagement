<?php use App\Core\Ui; $manage = can('employees.manage'); ?>
<div class="page-head"><div><h1><?= e($emp['full_name']) ?> <?= Ui::status((bool) $emp['is_active']) ?></h1><div class="sub"><?= e($emp['employee_code']) ?><?= $emp['designation'] ? ' · ' . e($emp['designation']) : '' ?></div></div>
  <?php if ($manage): ?><div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="<?= e(url('employees', 'form', ['id' => $emp['id']])) ?>"><i class="fa-solid fa-pen me-1"></i>Edit</a>
    <?= Ui::postForm(url('employees', 'toggle'), ['id' => $emp['id'], 'active' => $emp['is_active'] ? 0 : 1], $emp['is_active'] ? 'Mark inactive' : 'Mark active', 'btn btn-sm btn-outline-secondary', $emp['is_active'] ? 'Mark this employee inactive?' : null) ?></div><?php endif; ?></div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">Details</div><div class="card-body"><dl class="kv mb-0">
      <dt>Phone</dt><dd><?= e($emp['phone'] ?: '-') ?></dd><dt>Email</dt><dd><?= e($emp['email'] ?: '-') ?></dd><dt>Address</dt><dd><?= e($emp['address'] ?: '-') ?></dd><dt>Started</dt><dd><?= e(dmy($emp['start_date']) ?: '-') ?></dd>
      <dt>Login account</dt><dd><?= $user ? e($user['username']) . ' (' . e($user['role_name']) . ')' . ($user['is_active'] ? '' : ' - disabled') : '<span class="text-muted">None. An administrator can create one under Users.</span>' ?></dd></dl></div></div>
    <div class="card"><div class="card-header">Subjects this employee can teach</div><div class="card-body">
      <?php if ($manage): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="subjects">
        <?php if (!$subjects): ?><div class="text-muted small">No subjects exist yet.</div><?php endif; ?>
        <?php foreach ($subjects as $s): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="subjects[]" id="s<?= (int) $s['id'] ?>" value="<?= e($s['id']) ?>"<?= checked(in_array((int) $s['id'], $mine, true)) ?>><label class="form-check-label" for="s<?= (int) $s['id'] ?>"><?= e($s['name']) ?></label></div><?php endforeach; ?>
        <button class="btn btn-sm btn-primary mt-3">Save subjects</button></form>
      <?php else: ?><?php $names = array_map(fn ($s) => $s['name'], array_filter($subjects, fn ($s) => in_array((int) $s['id'], $mine, true))); ?><?= $names ? e(implode(', ', $names)) : '<span class="text-muted">None recorded</span>' ?><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Teaching assignments (class, section, subject)</div>
      <?php if (!$assignments): ?><?= Ui::empty('chalkboard-user', 'No assignments yet', 'Assignments decide which marks a teacher may enter and which students they can see.') ?><?php else: ?>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Year</th><th>Class</th><th>Section</th><th>Subject</th><th></th></tr></thead><tbody>
        <?php foreach ($assignments as $a): ?><tr><td><?= e($a['year_name']) ?></td><td><?= e($a['class_name']) ?></td><td><?= e($a['section_name']) ?></td><td><?= e($a['subject_name']) ?></td>
          <td class="text-end"><?php if ($manage): ?><?= Ui::postForm(url('employees', 'view', ['id' => $emp['id']]), ['op' => 'remove_assign', 'assign_id' => $a['id']], 'Remove', 'btn btn-sm btn-outline-danger', 'Remove this assignment?') ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
      <?php if ($manage && $emp['is_active']): ?><div class="card-footer bg-white"><form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="op" value="add_assign">
        <div class="col-md-3"><label class="form-label small mb-0" for="ay">Year</label><select class="form-select form-select-sm" id="ay" name="year_id"><?= Ui::options($years, 'id', 'name', $year['id'] ?? null) ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0" for="as">Section</label><select class="form-select form-select-sm" id="as" name="section_id" required><?= Ui::sectionOptions($sections, null, 'Select') ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-0" for="asub">Subject</label><select class="form-select form-select-sm" id="asub" name="subject_id" required><?= Ui::options($subjects, 'id', 'name', null, 'Select') ?></select></div>
        <div class="col-md-3 d-grid"><button class="btn btn-sm btn-primary">Add</button></div></form></div><?php endif; ?>
    </div>
    <div class="card"><div class="card-header">Section coordinator</div>
      <?php if (!$coord): ?><div class="card-body text-muted">Not a coordinator of any section.</div><?php else: ?>
      <ul class="list-group list-group-flush"><?php foreach ($coord as $c): ?><li class="list-group-item"><?= e($c['year_name']) ?> · Class <?= e($c['class_name']) ?> - <?= e($c['section_name']) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
  </div>
</div>
