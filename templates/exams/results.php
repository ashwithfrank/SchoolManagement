<?php use App\Core\{Marks, Ui}; ?>
<div class="page-head"><div><h1><?= e($exam['name']) ?> - results</h1><div class="sub">Year <?= e($exam['year_name']) ?>. Overall is "Incomplete" while any subject is absent or not entered.</div></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('exams', 'list')) ?>">Back to exams</a></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="exams"><input type="hidden" name="a" value="results"><input type="hidden" name="exam" value="<?= (int) $exam['id'] ?>">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label" for="class">Class</label><select class="form-select" id="class" name="class" data-sections-target="#section" required><?= Ui::options($classes, 'id', 'name', $classId, 'Choose') ?></select></div>
    <div class="col-md-3"><label class="form-label" for="section">Section</label><select class="form-select" id="section" name="section"><?= Ui::sectionOptions($sections, $sectionId, 'All sections') ?></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Show</button></div>
    <?php if ($data): ?><div class="col-md-2 d-grid"><a class="btn btn-outline-secondary" data-allow-resubmit href="<?= e(url('exams', 'results', ['exam' => $exam['id'], 'class' => $classId, 'section' => $sectionId, 'export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a></div><?php endif; ?></div></form>
<?php if ($data): ?><div class="card"><?php if (!$data['papers']): ?><?= Ui::empty('file-lines', 'No papers for this class in this exam') ?><?php elseif (!$data['students']): ?><?= Ui::empty('users', 'No students found') ?><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Sec</th><th>Roll</th><th>Student</th>
    <?php foreach ($data['papers'] as $p): ?><th class="text-end"><?= e($p['subject_name']) ?><div class="small text-muted fw-normal">/<?= e(Marks::display($p['max_marks'])) ?> (pass <?= e(Marks::display($p['min_marks'])) ?>)</div></th><?php endforeach; ?>
    <th class="text-end">Total</th><th class="text-end">%</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($data['students'] as $s): ?><tr><td><?= e($s['section_name']) ?></td><td><?= e($s['roll_no']) ?></td><td><?= e($s['full_name']) ?></td>
    <?php foreach ($data['papers'] as $p): $c = $s['cells'][$p['id']]; ?><td class="text-end<?= $c['eval']['result'] === 'Fail' ? ' text-danger fw-semibold' : '' ?>"><?= $c['eval']['state'] === 'absent' ? 'AB' : ($c['eval']['state'] === 'missing' ? '-' : e(Marks::display($c['scored']))) ?></td><?php endforeach; ?>
    <td class="text-end"><?= e(Marks::display($s['overall']['total'])) ?>/<?= e(Marks::display($s['overall']['max'])) ?></td><td class="text-end"><?= $s['overall']['percentage'] === null ? '-' : e($s['overall']['percentage']) ?></td>
    <td><?= Ui::badge($s['overall']['result'], $s['overall']['result'] === 'Pass' ? 'success' : ($s['overall']['result'] === 'Fail' ? 'danger' : 'secondary')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></div><?php endif; ?>
