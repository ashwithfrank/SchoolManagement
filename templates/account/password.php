<div class="page-head"><h1>Change password</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
  <form method="post" autocomplete="off"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label required" for="current">Current password</label><input class="form-control" type="password" id="current" name="current" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label required" for="new">New password</label><input class="form-control" type="password" id="new" name="new" required minlength="8" autocomplete="new-password"><div class="form-text">At least 8 characters, with a letter and a number.</div></div>
    <div class="mb-3"><label class="form-label required" for="new2">Repeat new password</label><input class="form-control" type="password" id="new2" name="new2" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-primary">Change password</button>
  </form>
</div></div>
