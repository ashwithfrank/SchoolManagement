<div class="empty-state mt-5"><i class="fa-solid fa-lock"></i>
  <h4>Access denied</h4>
  <p class="text-muted"><?= e($message ?? 'You do not have permission to open this page.') ?></p>
  <a class="btn btn-primary" href="<?= e(url()) ?>">Back to dashboard</a>
</div>
