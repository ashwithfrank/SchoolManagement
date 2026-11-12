<?php use App\Core\{Marks, Ui}; ?>
<div class="page-head"><div><h1>Enter marks</h1><div class="sub">Year <?= e($year['name']) ?>. Leave a mark blank to keep it "not entered"; tick Absent for absence. Nothing is saved if any value is invalid.</div></div></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="exams"><input type="hidden" name="a" value="marks">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label" for="exam">Exam</label><select class="form-select" id="exam" name="exam" required><?= Ui::options($exams, 'id', 'name', $sel['exam'], 'Choose') ?></select></div>
    <div class="col-md-2"><label class="form-label" for="class">Class</label><select class="form-select" id="class" name="class" data-sections-target="#section" required><?= Ui::options($classes, 'id', 'name', $sel['class'], 'Choose') ?></select></div>
    <div class="col-md-2"><label class="form-label" for="section">Section</label><select class="form-select" id="section" name="section" required><?= Ui::sectionOptions($sections, $sel['section'], 'Choose') ?></select></div>
    <div class="col-md-3"><label class="form-label" for="subject">Subject</label><select class="form-select" id="subject" name="subject" required><?= Ui::options($subjects, 'id', 'name', $sel['subject'], 'Choose') ?></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Load sheet</button></div></div></form>
<?php if ($notice): ?><div class="alert alert-warning"><?= e($notice) ?></div><?php endif; ?>
<?php if ($paper): ?>
<div class="card"><div class="card-header d-flex justify-content-between"><span><?= e($paper['subject_name']) ?> - Class <?= e($paper['class_name']) ?></span><span class="text-muted small">Maximum <?= e(Marks::display($paper['max_marks'])) ?> | Passing <?= e(Marks::display($paper['min_marks'])) ?></span></div>
<?php if (!$sheet): ?><?= Ui::empty('users', 'No students in this section') ?><?php else: ?>
<form method="post" action="<?= e(url('exams', 'marks', $sel)) ?>"><?= csrf_field() ?>
  <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Roll</th><th>Student</th><th>Adm. no</th><th style="width:140px">Marks</th><th>Absent</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($sheet as $r): $ev = Marks::evaluate($r['scored_marks'], (bool) $r['is_absent'], $paper['max_marks'], $paper['min_marks']); ?>
    <tr><td><?= e($r['roll_no']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['admission_no']) ?></td>
      <td><input class="form-control form-control-sm" name="score[<?= (int) $r['enrollment_id'] ?>]" inputmode="decimal" value="<?= e(Marks::display($r['scored_marks'])) ?>" aria-label="Marks for <?= e($r['full_name']) ?>"></td>
      <td><input class="form-check-input" type="checkbox" name="absent[<?= (int) $r['enrollment_id'] ?>]" value="1"<?= $r['is_absent'] ? ' checked' : '' ?> aria-label="Absent"></td>
      <td><?= $ev['state'] === 'entered' ? Ui::badge($ev['result'] . ' ' . $ev['percentage'] . '%', $ev['result'] === 'Pass' ? 'success' : 'danger') : Ui::badge($ev['result'], 'secondary') ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save marks</button></div></form><?php endif; ?></div>
<?php endif; ?>
