<?php use App\Core\{Marks, Ui}; ?>
<div class="page-head"><div><h1><?= e($exam['name']) ?> - papers</h1><div class="sub">Year <?= e($exam['year_name']) ?>. Pass if marks scored are at least the passing marks.</div></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('exams', 'list')) ?>">Back to exams</a></div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><?php if (!$papers): ?><?= Ui::empty('file-lines', 'No papers yet') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Class</th><th>Subject</th><th class="text-end">Maximum</th><th class="text-end">Passing</th><th class="text-end">Marks entered</th></tr></thead><tbody>
    <?php foreach ($papers as $p): ?><tr><td><?= e($p['class_name']) ?></td><td><?= e($p['subject_name']) ?></td><td class="text-end"><?= e(Marks::display($p['max_marks'])) ?></td><td class="text-end"><?= e(Marks::display($p['min_marks'])) ?></td><td class="text-end"><?= (int) $p['marks_entered'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div></div>
  <?php if (can('exams.papers')): ?><div class="col-lg-4"><div class="card"><div class="card-header">Add or change a paper</div><div class="card-body">
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label required" for="class_id">Class</label><select class="form-select" id="class_id" name="class_id" required><?= Ui::options($classes, 'id', 'name', old('class_id'), 'Choose') ?></select></div>
      <div class="mb-3"><label class="form-label required" for="subject_id">Subject</label><select class="form-select" id="subject_id" name="subject_id" required><?= Ui::options($subjects, 'id', 'name', old('subject_id'), 'Choose') ?></select></div>
      <div class="row g-2 mb-3"><div class="col-6"><label class="form-label required" for="max_marks">Maximum</label><input class="form-control" id="max_marks" name="max_marks" inputmode="decimal" required value="<?= e(old('max_marks')) ?>"></div>
        <div class="col-6"><label class="form-label required" for="min_marks">Passing</label><input class="form-control" id="min_marks" name="min_marks" inputmode="decimal" required value="<?= e(old('min_marks')) ?>"></div></div>
      <button class="btn btn-primary">Save paper</button>
      <div class="form-text mt-2">Saving an existing class + subject changes its marks limits (blocked if existing marks would exceed the new maximum).</div>
    </form></div></div></div><?php endif; ?>
</div>
