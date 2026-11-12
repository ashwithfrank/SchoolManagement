<?php
use App\Core\Ui;
/** @var array $d @var bool $limited */
$dis = fn (bool $contact = false) => ($limited && !$contact) ? ' disabled' : '';
$in = function (string $name, string $label, array $o = []) use ($d, $limited) {
    $type = $o['type'] ?? 'text';
    $contact = $o['contact'] ?? false;
    $val = $d[$name] ?? '';
    if ($type === 'date' && $val) { $val = substr((string) $val, 0, 10); }
    $col = $o['col'] ?? 'col-md-4';
    return '<div class="' . $col . '"><label class="form-label' . (!empty($o['req']) ? ' required' : '') . '" for="f_' . $name . '">' . e($label) . '</label>'
        . '<input class="form-control" id="f_' . $name . '" name="' . $name . '" type="' . $type . '" value="' . e($val) . '" maxlength="' . ($o['max'] ?? 120) . '"'
        . (!empty($o['req']) ? ' required' : '') . (($limited && !$contact) ? ' disabled' : '') . (isset($o['pattern']) ? ' pattern="' . $o['pattern'] . '"' : '') . '></div>';
};
?>
<div class="page-head"><div><h1><?= $student ? 'Edit student' : 'Admit student' ?></h1>
  <?php if ($student): ?><div class="sub"><?= e($student['full_name']) ?> · <?= e($student['student_code']) ?></div><?php endif; ?></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e($student ? url('students', 'view', ['id' => $student['id']]) : url('students', 'list')) ?>">Cancel</a></div>
<?php if ($limited): ?><div class="alert alert-info">As teaching staff you can update contact, guardian and address details only.</div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="card mb-3"><div class="card-header">Identity</div><div class="card-body"><div class="row g-3">
    <?= $in('admission_no', 'Admission number', ['req' => 1, 'max' => 30]) ?>
    <?= $in('sats_no', 'SATS number (if applicable)', ['max' => 30]) ?>
    <?= $in('full_name', 'Student name', ['req' => 1]) ?>
    <div class="col-md-4"><label class="form-label required" for="f_gender">Gender</label>
      <select class="form-select" id="f_gender" name="gender" required<?= $dis() ?>><option value="">Select</option>
        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $d['gender'] ?? '') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <?= $in('date_of_birth', 'Date of birth', ['req' => 1, 'type' => 'date']) ?>
    <?= $in('admission_date', 'Date of admission', ['req' => 1, 'type' => 'date']) ?>
  </div></div></div>

  <div class="card mb-3"><div class="card-header">Parents / guardian and contact</div><div class="card-body"><div class="row g-3">
    <?= $in('father_name', 'Father name', ['contact' => 1]) ?>
    <?= $in('mother_name', 'Mother name', ['contact' => 1]) ?>
    <?= $in('guardian_name', 'Guardian name', ['contact' => 1]) ?>
    <?= $in('phone_primary', 'Primary contact number', ['req' => 1, 'contact' => 1, 'max' => 20]) ?>
    <?= $in('phone_secondary', 'Secondary contact number', ['contact' => 1, 'max' => 20]) ?>
    <?= $in('email', 'Email (optional)', ['contact' => 1, 'type' => 'email', 'max' => 150]) ?>
    <div class="col-12 form-text mt-0">Enter at least one of father, mother or guardian.</div>
  </div></div></div>

  <div class="card mb-3"><div class="card-header">Address</div><div class="card-body"><div class="row g-3">
    <?= $in('addr_house', 'House / building', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_street', 'Street', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_locality', 'Locality', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_village', 'Village / town', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_post_office', 'Post office', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_taluk', 'Taluk', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_district', 'District', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_state', 'State', ['contact' => 1, 'max' => 100]) ?>
    <?= $in('addr_pin', 'PIN code', ['contact' => 1, 'max' => 6, 'pattern' => '\d{6}']) ?>
  </div></div></div>

  <?php if (!$student): ?>
  <div class="card mb-3"><div class="card-header">Class for <?= e($year['name'] ?? 'the current year') ?> <span class="text-muted fw-normal">(optional now; can be set later)</span></div><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="class_id">Class</label><select class="form-select" id="class_id" name="class_id" data-sections-target="#section_id"><?= Ui::options($classes, 'id', 'name', $enrol['class_id'], 'Not yet assigned') ?></select></div>
    <div class="col-md-4"><label class="form-label" for="section_id">Section</label><select class="form-select" id="section_id" name="section_id"><?= Ui::sectionOptions($sections, $enrol['section_id'], 'Select section') ?></select></div>
    <div class="col-md-4"><label class="form-label" for="roll_no">Roll number (optional)</label><input class="form-control" id="roll_no" name="roll_no" value="<?= e($enrol['roll_no']) ?>" inputmode="numeric" maxlength="5"></div>
  </div></div></div>
  <?php endif; ?>

  <?php if (!$limited): ?>
  <div class="card mb-3"><div class="card-header">Notes (optional)</div><div class="card-body"><textarea class="form-control" name="notes" rows="2" maxlength="2000"><?= e($d['notes'] ?? '') ?></textarea></div></div>
  <?php endif; ?>
  <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= $student ? 'Save changes' : 'Admit student' ?></button>
</form>
