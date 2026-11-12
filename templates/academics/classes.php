<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Classes &amp; sections</h1><div class="sub">LKG to Class 10. Student counts are for <?= e($year['name'] ?? 'the selected year') ?>.</div></div>
  <a class="btn btn-outline-primary btn-sm" href="<?= e(url('academics', 'coordinators')) ?>"><i class="fa-solid fa-user-tie me-1"></i>Class coordinators</a></div>
<div class="row g-3">
<?php foreach ($classes as $c): $secs = $by[(int) $c['id']] ?? []; ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header d-flex justify-content-between">Class <?= e($c['name']) ?>
      <?php if (can('academics.manage')): ?>
      <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= e($c['id']) ?>">
        <input class="form-control form-control-sm" style="width:90px" name="name" placeholder="New sec." maxlength="20" aria-label="New section name for class <?= e($c['name']) ?>" required><button class="btn btn-sm btn-outline-primary" title="Add section"><i class="fa-solid fa-plus"></i></button></form>
      <?php endif; ?></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($secs as $s): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center <?= $s['is_active'] ? '' : 'text-muted' ?>">
          <span>Section <b><?= e($s['name']) ?></b> <?= $s['is_active'] ? '' : Ui::badge('Disabled', 'secondary') ?>
            <?php if (isset($coords[(int) $s['id']])): ?><div class="small text-muted"><i class="fa-solid fa-user-tie me-1"></i><?= e($coords[(int) $s['id']]['employee_name']) ?></div><?php endif; ?></span>
          <span class="d-flex align-items-center gap-2"><span class="badge text-bg-light border"><?= (int) ($counts[(int) $s['id']] ?? 0) ?> students</span>
            <?php if (can('academics.manage')): ?><?= Ui::postForm(url('academics', 'classes'), ['op' => 'toggle', 'id' => $s['id'], 'active' => $s['is_active'] ? 0 : 1], $s['is_active'] ? 'Disable' : 'Enable', 'btn btn-sm btn-link text-decoration-none p-0') ?><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ul></div></div>
<?php endforeach; ?>
</div>
