<div class="page-head"><h1>Reset password - <?= e($user['username']) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('users', 'list')) ?>">Cancel</a></div>
<form method="post" autocomplete="off" class="card" style="max-width:520px"><div class="card-body"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label required" for="password">New password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label required" for="password2">Repeat password</label><input class="form-control" type="password" id="password2" name="password2" required autocomplete="new-password"></div>
  <div class="form-text">At least 8 characters with letters and numbers. Tell the user to change it after signing in.</div></div>
<div class="card-footer bg-white"><button class="btn btn-primary">Reset password</button></div></form>
