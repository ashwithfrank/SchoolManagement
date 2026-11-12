<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup · AlphaOmega School Management</title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/solid.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head><body>
<div class="login-wrap"><div class="card login-card" style="max-width:640px"><div class="card-body p-4">
  <div class="text-center mb-3">
    <div class="brand-mark"><i class="fa-solid fa-graduation-cap"></i></div>
    <h1 class="h4 mb-0">First-time setup</h1>
    <div class="text-muted small">Step <?= $stepTwo ? '2 of 2: school and administrator' : '1 of 2: database' ?></div>
  </div>
  <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$stepTwo): ?>
  <div class="mb-3">
    <div class="fw-semibold mb-1">Server check</div>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($checks as $label => $ok): ?>
        <li><i class="fa-solid <?= $ok ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?> me-1"></i><?= e($label) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-8"><label class="form-label required" for="host">MySQL host</label><input class="form-control" id="host" name="host" value="<?= e($in['host'] ?? '127.0.0.1') ?>" required></div>
      <div class="col-4"><label class="form-label" for="port">Port</label><input class="form-control" id="port" name="port" value="<?= e($in['port'] ?? '3306') ?>"></div>
      <div class="col-12"><label class="form-label required" for="name">Database name</label><input class="form-control" id="name" name="name" value="<?= e($in['name'] ?? 'alphaomega_school') ?>" required pattern="[A-Za-z0-9_]+">
        <div class="form-text">Must be empty or not exist yet. A brand-new database is created for the school; the college database is never touched.</div></div>
      <div class="col-md-6"><label class="form-label required" for="user">MySQL user</label><input class="form-control" id="user" name="user" value="<?= e($in['user'] ?? 'root') ?>" required autocomplete="off"></div>
      <div class="col-md-6"><label class="form-label" for="pass">MySQL password</label><input class="form-control" id="pass" name="pass" type="password" autocomplete="new-password"></div>
      <div class="col-12">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="create_db" name="create_db" value="1" checked><label class="form-check-label" for="create_db">Create the database if it does not exist</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="accept_root" name="accept_root" value="1"><label class="form-check-label" for="accept_root">I am using root without a password on a single local PC (XAMPP default)</label></div>
      </div>
    </div>
    <button class="btn btn-primary w-100 mt-4"<?= $ready ? '' : ' disabled' ?>>Create tables and continue</button>
  </form>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-12"><label class="form-label required" for="school_name">School name</label><input class="form-control" id="school_name" name="school_name" value="<?= e($in['school_name'] ?? '') ?>" required maxlength="120"></div>
      <div class="col-md-4"><label class="form-label required" for="year_name">First academic year</label><input class="form-control" id="year_name" name="year_name" value="<?= e($in['year_name'] ?? $defaultYear['name']) ?>" required maxlength="20"></div>
      <div class="col-md-4"><label class="form-label required" for="year_start">Starts</label><input class="form-control" type="date" id="year_start" name="year_start" value="<?= e($in['year_start'] ?? $defaultYear['start']) ?>" required></div>
      <div class="col-md-4"><label class="form-label required" for="year_end">Ends</label><input class="form-control" type="date" id="year_end" name="year_end" value="<?= e($in['year_end'] ?? $defaultYear['end']) ?>" required></div>
      <div class="col-12"><hr class="my-1"><div class="fw-semibold">Administrator account</div></div>
      <div class="col-md-6"><label class="form-label required" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" value="<?= e($in['full_name'] ?? '') ?>" required maxlength="120"></div>
      <div class="col-md-6"><label class="form-label required" for="username">Username</label><input class="form-control" id="username" name="username" value="<?= e($in['username'] ?? '') ?>" required pattern="[A-Za-z0-9._\-]{3,40}" autocomplete="off"></div>
      <div class="col-md-6"><label class="form-label required" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password"></div>
      <div class="col-md-6"><label class="form-label required" for="password2">Repeat password</label><input class="form-control" type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password"></div>
      <div class="col-12 form-text">At least 8 characters with a letter and a number. There is no default or recovery account, so write it down and keep it safe.</div>
    </div>
    <button class="btn btn-primary w-100 mt-4">Finish setup</button>
  </form>
  <?php endif; ?>
</div></div></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
