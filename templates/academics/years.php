<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Academic years</h1><div class="sub">One year is active at a time. Closed years and all their records stay available.</div></div>
  <?php if (can('promotion.manage')): ?><a class="btn btn-primary" href="<?= e(url('academics', 'promotion')) ?>"><i class="fa-solid fa-turn-up me-1"></i>Promote students</a><?php endif; ?></div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><?php if (!$years): ?><?= Ui::empty('calendar-days', 'No academic years yet') ?><?php else: ?>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Year</th><th>Starts</th><th>Ends</th><th>Enrollments</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($years as $y): ?><tr><td class="fw-semibold"><?= e($y['name']) ?></td><td><?= e(dmy($y['start_date'])) ?></td><td><?= e(dmy($y['end_date'])) ?></td><td><?= (int) $y['students'] ?></td>
        <td><?= $y['active_flag'] ? Ui::badge('Active', 'success') : Ui::badge('Not active', 'secondary') ?></td>
        <td class="text-end"><?php if (can('academics.manage') && !$y['active_flag']): ?><?= Ui::postForm(url('academics', 'years'), ['op' => 'activate', 'id' => $y['id']], 'Make active', 'btn btn-sm btn-outline-primary', 'Make ' . $y['name'] . ' the active academic year?') ?><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
  <?php if (can('academics.manage')): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header">New academic year</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="create">
      <div class="mb-3"><label class="form-label required" for="name">Name</label><input class="form-control" id="name" name="name" placeholder="2027-28" required maxlength="20" value="<?= e(old('name')) ?>"></div>
      <div class="row g-3 mb-3"><div class="col-6"><label class="form-label required" for="start_date">Starts</label><input class="form-control" type="date" id="start_date" name="start_date" required value="<?= e(old('start_date')) ?>"></div>
        <div class="col-6"><label class="form-label required" for="end_date">Ends</label><input class="form-control" type="date" id="end_date" name="end_date" required value="<?= e(old('end_date')) ?>"></div></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="make_active" name="make_active" value="1"><label class="form-check-label" for="make_active">Make it the active year</label></div>
      <button class="btn btn-primary">Create year</button>
    </form></div></div></div>
  <?php endif; ?>
</div>
