<?php use App\Core\Ui; use App\Services\TransportService;
$badge = function (?string $date) {
    [$st, $days] = TransportService::expiryState($date);
    if ($st === 'none') { return '<span class="text-muted">-</span>'; }
    $cls = ['expired' => 'danger', 'soon' => 'warning', 'ok' => 'success'][$st];
    $txt = $st === 'expired' ? 'Expired' : ($st === 'soon' ? 'Due in ' . $days . ' d' : 'Valid');
    return e(dmy($date)) . ' ' . Ui::badge($txt, $cls);
};
?>
<div class="page-head"><div><h1>Buses</h1><div class="sub">Insurance and fitness certificate dates are checked against today.</div></div>
  <?php if (can('buses.manage')): ?><a class="btn btn-primary" href="<?= e(url('transport', 'bus_form')) ?>"><i class="fa-solid fa-plus me-1"></i>Add bus</a><?php endif; ?></div>
<div class="card"><?php if (!$buses): ?><?= Ui::empty('bus', 'No buses added yet') ?><?php else: ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Bus ID</th><th>Vehicle</th><th>Driver</th><th>Insurance expiry</th><th>FC expiry</th><th>Seats</th><th>Riders</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($buses as $b): ?><tr class="<?= $b['is_active'] ? '' : 'text-muted' ?>"><td class="fw-semibold"><?= e($b['bus_code']) ?></td><td><?= e($b['vehicle_no']) ?></td>
    <td><?= e($b['driver_name']) ?><div class="small text-muted"><?= e($b['driver_phone']) ?></div></td><td><?= $badge($b['insurance_expiry']) ?></td><td><?= $badge($b['fc_expiry']) ?></td>
    <td><?= e($b['capacity']) ?></td><td><a href="<?= e(url('transport', 'assignments', ['bus' => $b['id']])) ?>"><?= (int) $b['riders'] ?></a></td><td><?= Ui::status((bool) $b['is_active']) ?></td>
    <td class="text-end"><?php if (can('buses.manage')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('transport', 'bus_form', ['id' => $b['id']])) ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
