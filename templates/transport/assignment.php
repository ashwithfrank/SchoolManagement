<?php use App\Core\{Marks, Money, Ui}; $active = $a['status'] === 'active'; ?>
<div class="page-head"><div><h1>Transport: <?= e($a['student_name']) ?></h1><div class="sub">Bus <?= e($a['bus_code']) ?> (<?= e($a['vehicle_no']) ?>) · <?= Ui::badge(ucfirst($a['status']), ['active' => 'success', 'ended' => 'secondary', 'cancelled' => 'danger'][$a['status']]) ?></div></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('transport', 'assignments')) ?>">Back</a></div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header">Fee and pickup</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="update">
      <div class="mb-3"><label class="form-label required" for="fee">Fee for this student (₹)</label><input class="form-control" id="fee" name="fee" required inputmode="decimal" value="<?= e(Money::toDb(Money::fromDb($a['fee']))) ?>">
        <div class="form-text">Cannot be lowered below what has already been paid plus discounts.</div></div>
      <div class="mb-3"><label class="form-label" for="pickup_location">Pickup / drop location</label><input class="form-control" id="pickup_location" name="pickup_location" maxlength="150" value="<?= e($a['pickup_location']) ?>"></div>
      <div class="mb-3"><label class="form-label" for="distance_km">Distance (km)</label><input class="form-control" id="distance_km" name="distance_km" inputmode="decimal" value="<?= e(Marks::display($a['distance_km'])) ?>"></div>
      <button class="btn btn-primary">Save changes</button></form></div></div></div>
  <?php if ($active): ?>
  <div class="col-lg-6">
    <div class="card mb-3"><div class="card-header">End assignment (change bus / stop transport)</div><div class="card-body">
      <form method="post" data-confirm="End this assignment? It stays in the history and the student can then be assigned to another bus."><?= csrf_field() ?><input type="hidden" name="op" value="end">
        <div class="row g-3"><div class="col-6"><label class="form-label required" for="end_date">End date</label><input class="form-control" type="date" id="end_date" name="end_date" value="<?= e($today) ?>" required></div>
          <div class="col-6"><label class="form-label" for="final_fee">Fee actually due (₹)</label><input class="form-control" id="final_fee" name="final_fee" inputmode="decimal" placeholder="leave blank = unchanged"></div>
          <div class="col-12"><label class="form-label required" for="reason">Reason</label><input class="form-control" id="reason" name="reason" required maxlength="200" placeholder="Changed bus / left transport"></div></div>
        <button class="btn btn-warning mt-3">End assignment</button>
        <div class="form-text mt-2">To move the student to another bus: end this one (set the fee due so far), then assign the new bus with its own fee for the rest of the year.</div></form></div></div>
    <div class="card border-danger-subtle"><div class="card-header">Entered by mistake?</div><div class="card-body">
      <form method="post" data-confirm="Cancel this assignment? It stops counting toward fees but stays visible in the history."><?= csrf_field() ?><input type="hidden" name="op" value="cancel">
        <div class="mb-3"><label class="form-label required" for="creason">Reason</label><input class="form-control" id="creason" name="reason" required maxlength="200"></div>
        <button class="btn btn-outline-danger">Cancel assignment</button></form></div></div>
  </div>
  <?php endif; ?>
</div>
