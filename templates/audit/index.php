<?php use App\Core\Ui; ?>
<div class="page-head"><div><h1>Audit log</h1><div class="sub">Append-only record of sign-ins and changes. It cannot be edited or deleted from the application.</div></div></div>
<form class="filter-bar" method="get" action="<?= e(base_path() . '/index.php') ?>"><input type="hidden" name="m" value="audit"><div class="row g-2 align-items-end">
  <div class="col-md-2"><label class="form-label" for="user">User</label><input class="form-control" id="user" name="user" value="<?= e($f['user']) ?>"></div>
  <div class="col-md-2"><label class="form-label" for="action">Action starts with</label><input class="form-control" id="action" name="action" value="<?= e($f['action']) ?>" placeholder="payment."></div>
  <div class="col-md-2"><label class="form-label" for="q">Contains</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="col-md-2 d-grid"><button class="btn btn-primary">Filter</button></div></div></form>
<div class="card"><?php if (!$rows): ?><?= Ui::empty('clipboard-list', 'No entries') ?><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="text-nowrap small"><?= e(date('d M Y H:i:s', strtotime($r['created_at']))) ?></td><td><?= e($r['username'] ?? '-') ?></td><td><code><?= e($r['action']) ?></code></td><td class="small"><?= e(trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?></td><td class="small text-break"><?= e($r['details'] ?? '') ?></td><td class="small"><?= e($r['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card-footer bg-white d-flex justify-content-between"><span class="small text-muted"><?= number_format($pager->total) ?> entries</span><?= Ui::pagination($pager) ?></div><?php endif; ?></div>
