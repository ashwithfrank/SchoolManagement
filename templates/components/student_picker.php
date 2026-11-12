<?php
/** Search-as-you-type student chooser. Vars: $name, $label, $selected (array|null), $required (bool) */
$required = $required ?? true;
?>
<div data-student-picker data-url="<?= e(url('students', 'lookup')) ?>" class="position-relative">
  <label class="form-label<?= $required ? ' required' : '' ?>" for="<?= e($name) ?>_q"><?= e($label) ?></label>
  <input type="hidden" class="picker-id" name="<?= e($name) ?>" value="<?= e($selected['id'] ?? '') ?>"<?= $required ? ' required' : '' ?>>
  <input type="search" class="form-control picker-q" id="<?= e($name) ?>_q" placeholder="Type at least 2 letters of the name or admission number" autocomplete="off">
  <ul class="list-group position-absolute w-100 shadow-sm picker-results" style="z-index:20;max-height:260px;overflow:auto" hidden></ul>
  <div class="form-text picker-chosen"><?= !empty($selected) ? e($selected['full_name'] . ' (Adm ' . $selected['admission_no'] . ')') : 'No student chosen yet' ?></div>
</div>
