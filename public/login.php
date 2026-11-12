<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\{Auth, Config, Csrf, Db, Flash, UserError, View};

if (!Config::exists()) {
    redirect(base_path() . '/setup.php');
}
try {
    if ((int) Db::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        redirect(base_path() . '/setup.php');
    }
} catch (Throwable $e) {
    redirect(base_path() . '/index.php');   // index.php shows the database error page
}

/** Only allow redirects back into this application. */
function safe_next(?string $next): string
{
    $next = (string) $next;
    $base = base_path() . '/';
    if ($next !== '' && strncmp($next, $base, strlen($base)) === 0 && !preg_match('/[\r\n\\\\]|\/\//', substr($next, 1)) && strpos($next, 'login.php') === false && strpos($next, 'logout.php') === false) {
        return $next;
    }
    return url();
}

if (Auth::check()) {
    redirect(safe_next($_GET['next'] ?? null));
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::enforce();
    $username = trim((string) ($_POST['username'] ?? ''));
    try {
        if (Auth::attempt($username, (string) ($_POST['password'] ?? ''))) {
            redirect(safe_next($_POST['next'] ?? null));
        }
        $error = 'Invalid username or password.';
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}
echo View::partial('auth/login', ['error' => $error, 'username' => $username, 'next' => $_GET['next'] ?? ($_POST['next'] ?? ''), 'flashes' => Flash::pull()]);
