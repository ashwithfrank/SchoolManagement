<?php use App\Core\Ui; ?>
<div class="page-head">
  <div><h1>Students</h1><div class="sub"><?= $year ? 'Class and section shown for ' . e($year['name']) : 'Create an academic year first' ?><?= $pager ? ' · ' . number_format($pager->total) . ' found' : '' ?></div></div>
  <?php if (can('students.manage')): ?><a class="btn btn-primary" href="<?= e(url('students', 'form')) ?>"><i class="fa-solid fa-user-plus me-1"></i>Admit student</a><?php endif; ?>
</div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>">
  <input type="hidden" name="m" value="students">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Name, admission no, SATS, phone"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="class">Class</label>
      <select class="form-select" id="class" name="class" data-sections-target="#section"><?= Ui::options($classes, 'id', 'name', $f['class_id'], 'All') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="section">Section</label>
      <select class="form-select" id="section" name="section"><?= Ui::sectionOptions($sections, $f['section_id'], 'All') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="enrolled">Enrollment</label>
      <select class="form-select" id="enrolled" name="enrolled"><?php foreach (['all' => 'Everyone', 'yes' => 'Enrolled this year', 'no' => 'Not enrolled'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['enrolled']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-md-1 d-grid"><button class="btn btn-primary">Filter</button></div>
  </div>
</form>
<div class="card">
<?php if (!$rows): ?>
  <?= Ui::empty('user-graduate', 'No students found', can('students.manage') ? 'Try different filters, or admit a new student.' : 'Try different filters. Teachers only see students in sections they are assigned to.') ?>
<?php else: ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th></th><th><?= Ui::sortLink('Name', 'name', $f['sort'], $f['dir']) ?></th><th><?= Ui::sortLink('Adm. no', 'admission', $f['sort'], $f['dir']) ?></th><th><?= Ui::sortLink('Class', 'class', $f['sort'], $f['dir']) ?></th><th>Roll</th><th>Guardian</th><th>Phone</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): $g = $s['father_name'] ?: ($s['mother_name'] ?: $s['guardian_name']); ?>
      <tr>
        <td style="width:50px"><?php if ($s['photo_file']): ?><img class="avatar" alt="" src="<?= e(base_path() . '/photo.php?id=' . $s['id']) ?>"><?php else: ?><span class="avatar"><i class="fa-solid fa-user"></i></span><?php endif; ?></td>
        <td><a class="fw-semibold" href="<?= e(url('students', 'view', ['id' => $s['id']])) ?>"><?= e($s['full_name']) ?></a><div class="small text-muted"><?= e($s['student_code']) ?></div></td>
        <td><?= e($s['admission_no']) ?></td>
        <td><?= $s['class_name'] ? e($s['class_name'] . ' - ' . $s['section_name']) : '<span class="text-muted">Not enrolled</span>' ?></td>
        <td><?= e($s['roll_no']) ?></td><td><?= e($g) ?></td><td><?= e($s['phone_primary']) ?></td><td><?= Ui::status((bool) $s['is_active']) ?></td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="small text-muted">Page <?= (int) $pager->page ?> of <?= (int) $pager->pages() ?></span><?= Ui::pagination($pager) ?></div>
<?php endif; ?>
</div>
