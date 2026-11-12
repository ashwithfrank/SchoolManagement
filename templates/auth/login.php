<?php use App\Core\Settings; ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(Settings::get('school_name', 'School')) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/solid.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="card login-card"><div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="brand-mark"><i class="fa-solid fa-graduation-cap"></i></div>
      <h1 class="h4 mb-0"><?= e(Settings::get('school_name', 'School')) ?></h1>
      <div class="text-muted small">AlphaOmega School Management</div>
    </div>
    <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="mb-3"><label class="form-label" for="u">Username</label>
        <input class="form-control" id="u" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username" maxlength="60"></div>
      <div class="mb-4"><label class="form-label" for="p">Password</label>
        <input class="form-control" id="p" name="password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>
    <p class="text-muted small text-center mt-4 mb-0">Forgot your password? Ask the school administrator to reset it.</p>
  </div></div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
