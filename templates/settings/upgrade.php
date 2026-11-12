<div class="page-head"><h1>Database upgrade</h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('settings', 'index')) ?>">Back</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
  <p>Database schema version <b><?= (int) $cur ?></b>; this application expects <b><?= (int) $latest ?></b>.</p>
  <?php if ($cur >= $latest): ?><div class="alert alert-success mb-0">The database is up to date.</div><?php else: ?>
  <div class="alert alert-warning"><b>Take a backup first</b> (Backup &amp; restore). MySQL cannot roll back structure changes, so a failed upgrade is recovered from the backup.</div>
  <form method="post" data-confirm="Have you made a backup? Run the upgrade now?"><?= csrf_field() ?><button class="btn btn-warning">Run upgrade</button></form><?php endif; ?>
</div></div>
