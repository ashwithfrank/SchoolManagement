<?php use App\Core\{Auth, Ui}; ?>
<div class="page-head"><div><h1>Users</h1><div class="sub">Logins are created here only. Adding an employee never creates a login. Accounts are deactivated, not deleted.</div></div>
  <a class="btn btn-primary" href="<?= e(url('users', 'form')) ?>"><i class="fa-solid fa-plus me-1"></i>Add user</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Linked employee</th><th>Last sign-in</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td class="fw-semibold"><?= e($u['username']) ?></td><td><?= e($u['full_name']) ?></td><td><?= e($u['role_name']) ?></td>
  <td><?= $u['employee_name'] ? e($u['employee_code'] . ' ' . $u['employee_name']) : '-' ?></td><td><?= $u['last_login_at'] ? e(date('d M Y H:i', strtotime($u['last_login_at']))) : 'Never' ?></td><td><?= Ui::status((bool) $u['is_active']) ?></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('users', 'form', ['id' => $u['id']])) ?>">Edit</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('users', 'password', ['id' => $u['id']])) ?>">Reset password</a>
    <?php if ((int) $u['id'] !== Auth::id()): ?><?= Ui::postForm(url('users', 'toggle'), ['id' => $u['id'], 'active' => $u['is_active'] ? 0 : 1], $u['is_active'] ? 'Deactivate' : 'Activate', 'btn btn-sm btn-outline-' . ($u['is_active'] ? 'danger' : 'success'), ($u['is_active'] ? 'Deactivate ' : 'Activate ') . $u['username'] . '?') ?><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
