<?php use App\Core\Money; ?>
<div class="page-head"><div><h1>Fee structure</h1><div class="sub">Annual academic fee per class for <b><?= e($year['name']) ?></b>. Use the year selector (top right) to edit another year.</div></div></div>
<form method="post" class="card" style="max-width:620px"><?= csrf_field() ?>
  <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Class</th><th style="width:220px">Academic fee (₹)</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td>Class <?= e($r['class_name']) ?></td><td><input class="form-control form-control-sm text-end" name="fee[<?= (int) $r['class_id'] ?>]" inputmode="decimal" aria-label="Fee for class <?= e($r['class_name']) ?>" value="<?= $r['academic_fee'] !== null ? e(Money::toDb(Money::fromDb($r['academic_fee']))) : '' ?>" placeholder="not set"></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white">
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="update_existing" name="update_existing" value="1"><label class="form-check-label" for="update_existing">Also update students whose fee came from the structure and who have no payment or discount yet</label></div>
    <button class="btn btn-primary">Save fee structure</button>
    <div class="form-text mt-2">Saving creates the academic fee assessment for every enrolled student who does not have one. Individual fees can be adjusted on a student's fee statement with a reason.</div>
  </div></form>
