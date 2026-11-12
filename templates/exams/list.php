<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Exams</h1><div class="sub">Exams for <?= e($year['name']) ?>. Each exam has papers (subject + class with maximum and passing marks).</div></div>
  <?php if (can('marks.enter')): ?><a class="btn btn-primary" href="<?= e(url('exams', 'marks')) ?>"><i class="fa-solid fa-pen me-1"></i>Enter marks</a><?php endif; ?></div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><?php if (!$exams): ?><?= Ui::empty('pen-to-square', 'No exams yet', can('exams.manage') ? 'Create the first exam on the right.' : '') ?><?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Exam</th><th>Date</th><th>Papers</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($exams as $x): ?><tr><td class="fw-semibold"><?= e($x['name']) ?></td><td><?= e($x['exam_date'] ? dmy($x['exam_date']) : '-') ?></td><td><?= (int) $x['papers'] ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('exams', 'papers', ['exam' => $x['id']])) ?>">Papers</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('exams', 'results', ['exam' => $x['id']])) ?>">Results</a>
        <?php if (can('exams.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('exams', 'list', ['edit' => $x['id']])) ?>">Rename</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php if (can('exams.manage')): ?><div class="col-lg-4"><div class="card"><div class="card-header"><?= $editRow ? 'Edit exam' : 'New exam' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
      <div class="mb-3"><label class="form-label required" for="name">Name</label><input class="form-control" id="name" name="name" maxlength="80" required placeholder="Term 1" value="<?= e($editRow['name'] ?? old('name')) ?>"></div>
      <div class="mb-3"><label class="form-label" for="exam_date">Date (optional)</label><input class="form-control" type="date" id="exam_date" name="exam_date" value="<?= e($editRow['exam_date'] ?? old('exam_date')) ?>"></div>
      <button class="btn btn-primary"><?= $editRow ? 'Save' : 'Create exam' ?></button><?php if ($editRow): ?> <a class="btn btn-link" href="<?= e(url('exams', 'list')) ?>">Cancel</a><?php endif; ?>
    </form></div></div></div><?php endif; ?>
</div>
