<?php use App\Core\{Permissions, Ui}; $v = fn($k) => e($d[$k] ?? ''); ?>
<div class="page-head"><h1><?= $user ? 'Edit user' : 'Add user' ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('users', 'list')) ?>">Cancel</a></div>
<form method="post" autocomplete="off" class="card"><div class="card-body"><?= csrf_field() ?><div class="row g-3">
  <div class="col-md-6"><label class="form-label required" for="username">Username</label><input class="form-control" id="username" name="username" required maxlength="40" value="<?= $v('username') ?>"<?= $user ? ' readonly' : '' ?>></div>
  <div class="col-md-6"><label class="form-label required" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" required maxlength="120" value="<?= $v('full_name') ?>"></div>
  <div class="col-md-6"><label class="form-label required" for="role">Role</label><select class="form-select" id="role" name="role"><?php foreach (Permissions::ROLES as $code => $label): ?><option value="<?= e($code) ?>"<?= selected($code, $d['role'] ?? '') ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label" for="employee_id">Linked employee</label><select class="form-select" id="employee_id" name="employee_id"><option value="">None</option><?php foreach ($employees as $e): ?><option value="<?= (int) $e['id'] ?>"<?= selected($e['id'], $d['employee_id'] ?? '') ?>><?= e($e['employee_code'] . ' - ' . $e['full_name']) ?></option><?php endforeach; ?></select>
    <div class="form-text">Teaching Staff only see classes and subjects assigned to the linked employee.</div></div>
  <?php if (!$user): ?><div class="col-md-6"><label class="form-label required" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="new-password"></div>
  <div class="col-md-6"><label class="form-label required" for="password2">Repeat password</label><input class="form-control" type="password" id="password2" name="password2" required autocomplete="new-password"></div>
  <div class="col-12 form-text">At least 8 characters with letters and numbers; it must not contain the username.</div><?php endif; ?>
</div></div><div class="card-footer bg-white"><button class="btn btn-primary">Save</button></div></form>
