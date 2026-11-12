<?php $v = fn ($k) => e($d[$k] ?? ''); ?>
<div class="page-head"><h1><?= $emp ? 'Edit employee' : 'Add employee' ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e($emp ? url('employees', 'view', ['id' => $emp['id']]) : url('employees', 'list')) ?>">Cancel</a></div>
<form method="post" class="card"><div class="card-body"><?= csrf_field() ?><div class="row g-3">
  <div class="col-md-6"><label class="form-label required" for="full_name">Name</label><input class="form-control" id="full_name" name="full_name" required maxlength="120" value="<?= $v('full_name') ?>"></div>
  <div class="col-md-6"><label class="form-label" for="designation">Designation</label><input class="form-control" id="designation" name="designation" maxlength="80" value="<?= $v('designation') ?>" placeholder="Teacher, Clerk, Driver..."></div>
  <div class="col-md-4"><label class="form-label" for="phone">Contact number</label><input class="form-control" id="phone" name="phone" maxlength="20" value="<?= $v('phone') ?>"></div>
  <div class="col-md-4"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= $v('email') ?>"></div>
  <div class="col-md-4"><label class="form-label" for="start_date">Employment start date</label><input class="form-control" type="date" id="start_date" name="start_date" value="<?= e(substr((string) ($d['start_date'] ?? ''), 0, 10)) ?>"></div>
  <div class="col-12"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" rows="2" maxlength="300"><?= $v('address') ?></textarea></div>
</div></div><div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button></div></form>
