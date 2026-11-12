<?php use App\Core\Settings; ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(Settings::get('school_name', 'School')) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/solid.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
  <button class="btn btn-primary btn-sm" data-print type="button"><i class="fa-solid fa-print me-1"></i>Print / Save as PDF</button>
  <button class="btn btn-outline-secondary btn-sm" data-back type="button">Back</button>
</div>
<div class="print-sheet"><?= $content ?></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
