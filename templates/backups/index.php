<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Backup &amp; restore</h1><div class="sub">A backup holds the database, student photos and a manifest. Keep copies off this computer.</div></div></div>
<?php if (!$req['zip']): ?><div class="alert alert-danger">PHP "zip" extension is off. In XAMPP open php.ini, enable <code>extension=zip</code>, restart Apache.</div><?php endif; ?>
<?php if (!$req['openssl']): ?><div class="alert alert-warning">Encrypted backups need the PHP openssl extension (AES-256-GCM).</div><?php endif; ?>
<?php if (!$req['writable']): ?><div class="alert alert-danger">The backup folder is missing or not writable: <code><?= e($req['dir'] ?? '(unknown)') ?></code></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-5"><div class="card mb-3"><div class="card-header">Create a backup</div><div class="card-body">
    <form method="post" action="<?= e(url('backups', 'create')) ?>" autocomplete="off"><?= csrf_field() ?>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="photos" name="photos" value="1" checked><label class="form-check-label" for="photos">Include student photos</label></div>
      <div class="mb-2"><label class="form-label" for="password">Encryption password (recommended)</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password"></div>
      <div class="mb-2"><label class="form-label" for="password2">Repeat password</label><input class="form-control" type="password" id="password2" name="password2" autocomplete="new-password"><div class="form-text">There is no password recovery. If it is lost, the backup cannot be opened.</div></div>
      <div class="mb-2"><label class="form-label" for="note">Note (optional)</label><input class="form-control" id="note" name="note" maxlength="200"></div>
      <?php if ($cloudDir !== ''): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="cloud" name="cloud" value="1"><label class="form-check-label" for="cloud">Also copy to synced folder (encrypted only)<div class="small text-muted"><?= e($cloudDir) ?></div></label></div>
      <?php else: ?><div class="form-text mb-3">Optional: set a synced folder (OneDrive / Google Drive / network share) in Settings to copy encrypted backups there. This only copies a file; it is not a cloud API.</div><?php endif; ?>
      <button class="btn btn-primary"><i class="fa-solid fa-database me-1"></i>Create backup</button></form></div></div>
    <div class="card"><div class="card-header">Add a backup file</div><div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= e(url('backups', 'import')) ?>"><?= csrf_field() ?>
        <input class="form-control mb-2" type="file" name="backup" accept=".zip,.aosbak" required><button class="btn btn-outline-primary btn-sm">Upload</button></form>
      <form method="post" action="<?= e(url('backups', 'rescan')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">Rescan backup folder</button></form>
      <div class="form-text mt-2">Folder: <code><?= e($req['dir'] ?? '') ?></code></div></div></div></div>
  <div class="col-lg-7"><div class="card"><div class="card-header">Backups</div><?php if (!$backups): ?><?= Ui::empty('database', 'No backups yet') ?><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>File</th><th>Created</th><th>Type</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($backups as $b): ?><tr class="<?= $b['exists'] ? '' : 'table-warning' ?>"><td class="small"><?= e($b['filename']) ?><div class="text-muted"><?= number_format($b['size_bytes'] / 1024, 1) ?> KB<?= $b['is_encrypted'] ? ' · encrypted' : '' ?><?= $b['includes_photos'] ? '' : ' · no photos' ?><?= $b['note'] ? ' · ' . e($b['note']) : '' ?></div><?php if (!$b['exists']): ?><div class="text-danger">File missing</div><?php endif; ?></td>
      <td class="small"><?= e(date('d M Y H:i', strtotime($b['created_at']))) ?></td><td><?= Ui::badge($b['kind'], $b['kind'] === 'safety' ? 'warning' : 'secondary') ?></td>
      <td class="text-end text-nowrap"><?php if ($b['exists']): ?>
        <form method="post" action="<?= e(url('backups', 'verify')) ?>" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><?php if ($b['is_encrypted']): ?><input class="form-control form-control-sm" type="password" name="password" placeholder="Password" style="width:110px" autocomplete="off"><?php endif; ?><button class="btn btn-sm btn-outline-primary">Verify</button></form>
        <a class="btn btn-sm btn-outline-secondary" data-allow-resubmit href="<?= e(url('backups', 'download', ['id' => $b['id']])) ?>">Download</a>
        <a class="btn btn-sm btn-outline-danger" href="<?= e(url('backups', 'restore', ['id' => $b['id']])) ?>">Restore</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
</div>
