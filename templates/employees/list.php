<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Employees</h1><div class="sub">An employee record does not give login access. Create a user account separately.</div></div>
  <?php if (can('employees.manage')): ?><a class="btn btn-primary" href="<?= e(url('employees', 'form')) ?>"><i class="fa-solid fa-user-plus me-1"></i>Add employee</a><?php endif; ?></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="employees">
  <div class="row g-2 align-items-end"><div class="col-md-6"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, employee ID, phone, designation"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-grid"><button class="btn btn-primary">Filter</button></div></div></form>
<div class="card"><?php if (!$rows): ?><?= Ui::empty('id-badge', 'No employees found') ?><?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Name</th><th>Designation</th><th>Phone</th><th>Since</th><th>Login</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= e($r['employee_code']) ?></td><td><a class="fw-semibold" href="<?= e(url('employees', 'view', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a></td><td><?= e($r['designation']) ?></td><td><?= e($r['phone']) ?></td>
    <td><?= e(dmy($r['start_date'])) ?></td><td><?= $r['has_login'] ? Ui::badge('Has login', 'info') : '<span class="text-muted small">No login</span>' ?></td><td><?= Ui::status((bool) $r['is_active']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><span class="small text-muted"><?= number_format($pager->total) ?> employees</span><?= Ui::pagination($pager) ?></div><?php endif; ?></div>
