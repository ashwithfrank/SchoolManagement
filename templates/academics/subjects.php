<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Subjects</h1><div class="sub">Subjects are used for exam papers and teacher assignments. Disable a subject instead of deleting it.</div></div></div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><?php if (!$subjects): ?><?= Ui::empty('book', 'No subjects yet', 'Add subjects such as English, Mathematics, Kannada, Science.') ?><?php else: ?>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Subject</th><th>Code</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($subjects as $s): ?><tr class="<?= $s['is_active'] ? '' : 'text-muted' ?>"><td><?= e($s['name']) ?></td><td><?= e($s['code']) ?></td><td><?= Ui::status((bool) $s['is_active']) ?></td>
      <td class="text-end text-nowrap"><?php if (can('academics.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('academics', 'subjects', ['edit' => $s['id']])) ?>">Edit</a>
        <?= Ui::postForm(url('academics', 'subjects'), ['op' => 'toggle', 'id' => $s['id'], 'active' => $s['is_active'] ? 0 : 1], $s['is_active'] ? 'Disable' : 'Enable') ?><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php if (can('academics.manage')): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header"><?= $edit ? 'Edit subject' : 'Add subject' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['id']) ?>"><?php endif; ?>
      <div class="mb-3"><label class="form-label required" for="name">Subject name</label><input class="form-control" id="name" name="name" required maxlength="80" value="<?= e($edit['name'] ?? old('name')) ?>"></div>
      <div class="mb-3"><label class="form-label" for="code">Code (optional)</label><input class="form-control" id="code" name="code" maxlength="20" value="<?= e($edit['code'] ?? old('code')) ?>"></div>
      <button class="btn btn-primary"><?= $edit ? 'Save subject' : 'Add subject' ?></button> <?php if ($edit): ?><a class="btn btn-link" href="<?= e(url('academics', 'subjects')) ?>">Cancel</a><?php endif; ?>
    </form></div></div></div>
  <?php endif; ?>
</div>
