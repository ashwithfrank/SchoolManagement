<?php
/**
 * Dependency-free test runner:  php tests/run.php
 * Covers pure logic and static consistency checks. It does NOT touch MySQL (see tests/README.md for the manual DB checklist).
 */
define('APP_VERSION', 'test');
define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', APP_ROOT . '/app');
define('STORAGE_DIR', APP_ROOT . '/storage');
define('TEMPLATE_DIR', APP_ROOT . '/templates');
define('MODULE_DIR', APP_ROOT . '/modules');
define('DB_DIR', APP_ROOT . '/database');
spl_autoload_register(function ($c) {
    if (strpos($c, 'App\\') === 0 && is_file($p = APP_DIR . '/' . str_replace('\\', '/', substr($c, 4)) . '.php')) {
        require $p;
    }
});
mb_internal_encoding('UTF-8');

use App\Core\{BackupCrypto, Csrf, Dates, Http, Marks, Migrator, Money, Permissions, Router};
use App\Services\FeeService;

$pass = 0;
$fail = 0;
$failures = [];
function t(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        $failures[] = $name . ($detail !== '' ? " -> $detail" : '');
    }
}
function same($a, $b): bool { return $a === $b; }

// ---------- Money ----------
t('money parse 1250.5', same(Money::parse('1,250.5'), 125050));
t('money parse integer', same(Money::parse('2500'), 250000));
t('money rejects negative', Money::parse('-5') === null);
t('money rejects 3 decimals', Money::parse('1.234') === null);
t('money rejects text', Money::parse('abc') === null);
t('money rejects empty', Money::parse('') === null);
t('money fromDb', same(Money::fromDb('1234.50'), 123450));
t('money fromDb negative', same(Money::fromDb('-0.05'), -5));
t('money toDb', same(Money::toDb(123450), '1234.50'));
t('money toDb small', same(Money::toDb(5), '0.05'));
t('money format Indian grouping', strpos(Money::format(12345678), '1,23,456.78') !== false, Money::format(12345678));
t('money parseDecimal', same(Money::parseDecimal('87.5'), '87.5') || same(Money::parseDecimal('87.5'), '87.50'));
t('money parseDecimal invalid', Money::parseDecimal('8x') === null);

// ---------- Marks: percentage = scored/max*100; pass if scored >= min ----------
t('marks hundredths', same(Marks::hundredths('87.5'), 8750));
t('marks display trims', same(Marks::display('87.50'), '87.5'));
$e = Marks::evaluate('40', false, '100', '35');
t('marks pass', $e['result'] === 'Pass' && $e['percentage'] == 40.0);
$e = Marks::evaluate('35', false, '100', '35');
t('marks pass at exactly min', $e['result'] === 'Pass');
$e = Marks::evaluate('34.99', false, '100', '35');
t('marks fail just below min', $e['result'] === 'Fail');
$e = Marks::evaluate('45', false, '60', '21');
t('marks percentage 75', $e['percentage'] == 75.0);
t('marks absent', Marks::evaluate(null, true, '100', '35')['result'] === 'Absent');
t('marks missing is not zero', Marks::evaluate(null, false, '100', '35')['result'] === 'Not entered');
t('marks zero is entered', Marks::evaluate('0', false, '100', '35')['result'] === 'Fail');
$o = Marks::overall([['scored' => '50', 'absent' => false, 'max' => '100', 'min' => '35'], ['scored' => '30', 'absent' => false, 'max' => '100', 'min' => '35']]);
t('overall fails when one subject fails', $o['result'] === 'Fail' && $o['percentage'] == 40.0 && $o['total'] == 80.0);
$o = Marks::overall([['scored' => '50', 'absent' => false, 'max' => '100', 'min' => '35'], ['scored' => null, 'absent' => false, 'max' => '100', 'min' => '35']]);
t('overall incomplete when a mark is missing', $o['result'] === 'Incomplete');
$o = Marks::overall([['scored' => '70', 'absent' => false, 'max' => '100', 'min' => '35'], ['scored' => '60', 'absent' => false, 'max' => '100', 'min' => '35']]);
t('overall pass', $o['result'] === 'Pass' && $o['percentage'] == 65.0);
t('overall no papers', Marks::overall([])['result'] === 'No papers');

// ---------- Fees: total = academic + bus - discounts; balance = total - valid payments ----------
$c = FeeService::compute(['academic' => 2500000, 'transport' => 600000, 'discount_academic' => 100000, 'discount_transport' => 0, 'paid_academic' => 1000000, 'paid_transport' => 0]);
t('fee net total', $c['total']['net'] === 3000000);
t('fee balance', $c['total']['balance'] === 2000000);
t('fee partial status', $c['status'] === 'partial');
t('fee transport balance', $c['transport']['balance'] === 600000);
$c = FeeService::compute(['academic' => 100000, 'paid_academic' => 100000]);
t('fee paid status', $c['status'] === 'paid' && $c['total']['balance'] === 0);
$c = FeeService::compute(['academic' => 100000]);
t('fee pending status', $c['status'] === 'pending');
$c = FeeService::compute([]);
t('fee none status', $c['status'] === 'none');
$c = FeeService::compute(['academic' => 100000, 'discount_academic' => 100000, 'paid_academic' => 0]);
t('fee fully discounted', $c['total']['balance'] === 0 && $c['status'] === 'none');

// ---------- Backup encryption ----------
if (function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
    $plain = random_bytes(5000);
    $blob = BackupCrypto::encrypt($plain, 'correct horse battery');
    t('crypto marks encrypted', BackupCrypto::isEncrypted(substr($blob, 0, 8)));
    t('crypto round trip', BackupCrypto::decrypt($blob, 'correct horse battery') === $plain);
    $bad = false;
    try { BackupCrypto::decrypt($blob, 'wrong password'); } catch (\Throwable $e) { $bad = true; }
    t('crypto wrong password rejected', $bad);
    $tam = $blob;
    $tam[strlen($tam) - 20] = chr(ord($tam[strlen($tam) - 20]) ^ 1);
    $bad = false;
    try { BackupCrypto::decrypt($tam, 'correct horse battery'); } catch (\Throwable $e) { $bad = true; }
    t('crypto tamper detected', $bad);
    t('crypto not plaintext zip', strpos($blob, 'PK') !== 0);
} else {
    t('openssl AES-256-GCM available', false, 'skipped: not available in this PHP');
}

// ---------- Migrator statement splitter ----------
$schema = Migrator::statements(file_get_contents(DB_DIR . '/schema.sql'));
$tables = [];
foreach ($schema as $st) {
    if (preg_match('/^CREATE TABLE (\w+)/i', trim($st), $m)) {
        $tables[] = $m[1];
    }
}
t('schema has 29 tables', count($tables) === 29, (string) count($tables));
t('splitter ignores comments', Migrator::statements("-- hi; there\nCREATE TABLE a (x INT);\n-- c\nINSERT INTO a VALUES (1);\n") === ['CREATE TABLE a (x INT)', 'INSERT INTO a VALUES (1)']);
$refs = [];
preg_match_all('/REFERENCES\s+(\w+)/i', file_get_contents(DB_DIR . '/schema.sql'), $refs);
foreach (array_unique($refs[1]) as $r) {
    t("FK target exists: $r", in_array($r, $tables, true));
}
// every table the PHP code queries exists in the schema
$known = array_flip($tables);
$bad = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php' || strpos($p, '/tests/') !== false || strpos($p, '/vendor/') !== false) {
        continue;
    }
    $src = file_get_contents($p);
    if (preg_match_all('/(?<!KEY )\b(?:FROM|JOIN|INTO|UPDATE)\s+([a-z_]+)\b/', $src, $mm)) {
        foreach ($mm[1] as $tbl) {
            if (!isset($known[$tbl]) && !in_array($tbl, ['information_schema', 'set', 'select', 'where', 'the', 'a', 'an', 'this', 'your', 'each', 'one', 'it', 'is', 'to', 'now', 'dual', 'student', 'from'], true) && preg_match('/^[a-z]+_[a-z_]+$|^(users|roles|buses|exams|marks|students|enrollments|sections|classes|subjects|employees|payments|counters)$/', $tbl)) {
                $bad[$tbl . ' in ' . substr($p, strlen(APP_ROOT) + 1)] = 1;
            }
        }
    }
}
t('all SQL tables exist in schema', !$bad, implode('; ', array_slice(array_keys($bad), 0, 8)));

// ---------- Permissions ----------
$d = Permissions::defaults();
t('admin has every permission', count($d['admin']) === count(Permissions::ALL));
t('teaching cannot record payments', !in_array('payments.record', $d['teaching'], true));
t('teaching cannot manage users/backups', !in_array('users.manage', $d['teaching'], true) && !in_array('backups.manage', $d['teaching'], true));
t('teaching can enter marks', in_array('marks.enter', $d['teaching'], true));
t('office cannot enter marks', !in_array('marks.enter', $d['office'], true));
t('office cannot manage users/backups/settings', !array_intersect(['users.manage', 'backups.manage', 'settings.manage', 'audit.view'], $d['office']));
t('office cannot reverse payments', !in_array('payments.reverse', $d['office'], true));
foreach ($d as $role => $perms) {
    foreach ($perms as $p) {
        t("default perm exists: $role/$p", isset(Permissions::ALL[$p]));
    }
}

// ---------- CSRF ----------
$_SESSION = [];
$tok = Csrf::token();
t('csrf accepts own token', Csrf::valid($tok));
t('csrf rejects wrong token', !Csrf::valid('x' . $tok));
t('csrf rejects null', !Csrf::valid(null));
$_SESSION = [];
t('csrf rejects when no session token', !Csrf::valid(''));

// ---------- CSV formula injection, dates ----------
t('csv guards =', Http::csvSafe('=1+1')[0] === "'");
t('csv guards @', Http::csvSafe('@x')[0] === "'");
t('csv keeps negative numbers', Http::csvSafe('-5') === '-5');
t('csv keeps text', Http::csvSafe('Asha') === 'Asha');
t('date valid', Dates::isValid('2026-02-28'));
t('date rejects 30 Feb', !Dates::isValid('2026-02-30'));
t('date rejects text', !Dates::isValid('hello'));

// ---------- Router registry vs files ----------
$mods = Router::modules();
foreach ($mods as $m => $def) {
    foreach (array_keys($def['actions']) as $a) {
        t("controller exists: $m/$a", is_file(MODULE_DIR . "/$m/$a.php"));
        $perm = $def['actions'][$a] ?? $def['perm'] ?? null;
        if (is_string($perm)) {
            t("registry permission is real: $m/$a", isset(Permissions::ALL[$perm]), $perm);
        }
    }
    if (!empty($def['perm'])) {
        t("module permission is real: $m", isset(Permissions::ALL[$def['perm']]), (string) $def['perm']);
    }
}
// every controller file is registered (no orphan files, no unregistered = unreachable)
foreach (glob(MODULE_DIR . '/*/*.php') as $f) {
    $m = basename(dirname($f));
    $a = basename($f, '.php');
    t("controller registered: $m/$a", isset($mods[$m]['actions']) && array_key_exists($a, $mods[$m]['actions']));
}
// url('module','action') literals in code and templates point at registered actions; templates exist
$scan = array_merge(glob(MODULE_DIR . '/*/*.php'), glob(TEMPLATE_DIR . '/*/*.php'), glob(APP_DIR . '/*/*.php'), glob(APP_DIR . '/*/*/*.php'));
$badUrl = [];
$badTpl = [];
foreach ($scan as $f) {
    $src = file_get_contents($f);
    if (preg_match_all("/\burl\('([a-z]+)'(?:,\s*'([a-z_]+)')?/", $src, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $x) {
            $m = $x[1];
            $a = $x[2] ?? 'index';
            if (!isset($mods[$m]) || !array_key_exists($a, $mods[$m]['actions'])) {
                $badUrl[] = basename(dirname($f)) . '/' . basename($f) . ": url('$m','$a')";
            }
        }
    }
    if (preg_match_all("/View::(?:page|print|partial)\('([a-z_\/]+)'/", $src, $mm)) {
        foreach ($mm[1] as $tpl) {
            if (!is_file(TEMPLATE_DIR . "/$tpl.php")) {
                $badTpl[] = $tpl . ' (from ' . basename($f) . ')';
            }
        }
    }
}
t('all url() targets are registered actions', !$badUrl, implode('; ', array_slice($badUrl, 0, 6)));
t('all View templates exist', !$badTpl, implode('; ', array_slice($badTpl, 0, 6)));

// ---------- Static security heuristics ----------
$sqlConcat = [];
$rawEcho = [];
$noCsrf = [];
foreach ($scan as $f) {
    foreach (file($f) as $i => $line) {
        if (preg_match('/Db::(all|row|val|exec|insert)\(.*\$_(GET|POST|REQUEST|COOKIE)/', $line)) {
            $sqlConcat[] = basename($f) . ':' . ($i + 1);
        }
        if (preg_match('/<\?=\s*\$_(GET|POST|REQUEST|SERVER)/', $line)) {
            $rawEcho[] = basename($f) . ':' . ($i + 1);
        }
    }
}
t('no request data passed straight into Db::*', !$sqlConcat, implode(', ', $sqlConcat));
t('no raw request data echoed in templates', !$rawEcho, implode(', ', $rawEcho));
foreach (glob(TEMPLATE_DIR . '/*/*.php') as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/<form\b[^>]*method="post"[^>]*>(.{0,400})/is', $src, $mm)) {
        foreach ($mm[0] as $chunk) {
            if (strpos($chunk, 'csrf_field') === false && strpos($chunk, 'Ui::postForm') === false) {
                $noCsrf[] = basename(dirname($f)) . '/' . basename($f);
            }
        }
    }
}
t('every POST form has a CSRF field', !$noCsrf, implode(', ', array_unique($noCsrf)));
t('CSRF enforced in dispatcher', strpos(file_get_contents(APP_DIR . '/Core/Router.php'), 'Csrf::enforce') !== false);
t('no secrets shipped: config.php absent', !is_file(APP_DIR . '/config/config.php'));
t('no inline <script> in templates (CSP)', !array_filter(glob(TEMPLATE_DIR . '/*/*.php'), fn($f) => preg_match('/<script(?![^>]*\bsrc=)/i', file_get_contents($f))));
t('no onclick/onsubmit handlers (CSP)', !array_filter(glob(TEMPLATE_DIR . '/*/*.php'), fn($f) => preg_match('/\son(click|submit|change|load)=/i', file_get_contents($f))));
t('no eval/exec/shell in app code', !array_filter($scan, fn($f) => preg_match('/\b(eval|shell_exec|passthru|system|proc_open|popen)\s*\(/', file_get_contents($f))));
t('no md5/sha1 password hashing', !array_filter($scan, fn($f) => preg_match('/\b(md5|sha1)\s*\(/', file_get_contents($f))));

echo "\nPassed: $pass   Failed: $fail\n";
foreach ($failures as $f) {
    echo "  FAIL: $f\n";
}
exit($fail ? 1 : 0);
