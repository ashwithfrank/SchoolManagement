<div class="page-head"><h1>Restore backup</h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('backups', 'index')) ?>">Cancel</a></div>
<div class="alert alert-danger"><b>This replaces ALL current data</b> (students, fees, marks, users, settings and photos) with the contents of <code><?= e($b['filename']) ?></code>. The file is validated first and a safety backup of the current data is made automatically. You will be signed out afterwards.</div>
<form method="post" autocomplete="off" data-confirm="Replace all current data with this backup?" class="card" style="max-width:520px"><div class="card-body"><?= csrf_field() ?>
  <?php if ($b['is_encrypted']): ?><div class="mb-3"><label class="form-label required" for="password">Backup password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="off"></div><?php endif; ?>
  <div class="mb-3"><label class="form-label required" for="confirm">Type RESTORE to confirm</label><input class="form-control" id="confirm" name="confirm" required autocomplete="off"></div></div>
  <div class="card-footer bg-white"><button class="btn btn-danger">Restore now</button></div></form>
