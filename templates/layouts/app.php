<?php
use App\Core\{Auth, Router, Settings};
use App\Services\AcademicService;
$user = Auth::user();
$school = Settings::get('school_name', 'School');
$years = AcademicService::years();
$curYear = AcademicService::currentYear();
$menu = Router::menu();
$curMod = $_GET['m'] ?? 'dashboard';
$curAct = $_GET['a'] ?? 'index';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? $pageTitle . ' · ' : '') ?><?= e($school) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/solid.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell" id="appShell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-mark"><i class="fa-solid fa-graduation-cap"></i></span>
      <span class="brand-text"><b>AlphaOmega</b><small>School Management</small></span>
    </a>
    <nav class="side-nav" aria-label="Main">
      <?php foreach ($menu as $group => $items): ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($items as [$m, $a, $label, $icon]): ?>
          <?php $active = Router::isActive($m, $a, $curMod, $curAct); ?>
          <a class="nav-link-item<?= $active ? ' active' : '' ?>" href="<?= e(url($m, $a)) ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">v<?= e(APP_VERSION) ?> · offline</div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main-col">
    <header class="topbar no-print">
      <button class="btn btn-light btn-sm" id="sidebarToggle" type="button" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
      <div class="topbar-title d-none d-md-block"><?= e($school) ?></div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <?php if ($years): ?>
        <form method="post" action="<?= e(url('account', 'year')) ?>" class="year-form" title="Academic year being viewed">
          <?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
          <label class="visually-hidden" for="yearSel">Academic year</label>
          <i class="fa-solid fa-calendar-days text-muted me-1"></i>
          <select class="form-select form-select-sm" id="yearSel" name="year_id" data-autosubmit>
            <?php foreach ($years as $y): ?>
              <option value="<?= e($y['id']) ?>"<?= selected($y['id'], $curYear['id'] ?? 0) ?>><?= e($y['name']) ?><?= $y['active_flag'] ? ' (active)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <div class="dropdown">
          <button class="btn btn-light btn-sm dropdown-toggle user-btn" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-circle-user me-1"></i><?= e($user['full_name'] ?? '') ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($user['role_name'] ?? '') ?> · <?= e($user['username'] ?? '') ?></span></li>
            <li><a class="dropdown-item" href="<?= e(url('account', 'password')) ?>"><i class="fa-solid fa-key me-2"></i>Change password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="post" action="<?= e(base_path() . '/logout.php') ?>"><?= csrf_field() ?>
                <button class="dropdown-item"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign out</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>
    <main class="content" id="main">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show no-print" role="alert"><?= e($f['msg']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
      <?php endforeach; ?>
      <?php if (!$curYear && Auth::can('academics.manage')): ?>
        <div class="alert alert-warning">No academic year exists yet. <a href="<?= e(url('academics', 'years')) ?>">Create one</a> to start admitting students.</div>
      <?php endif; ?>
      <?= $content ?>
    </main>
    <footer class="app-foot no-print">AlphaOmega School Management System · AlphaOmega Studio</footer>
  </div>
</div>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
