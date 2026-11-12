<?php
namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/** Thin PDO wrapper. All queries use prepared statements with bound parameters. */
class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(Config::get('db', []));
        }
        return self::$pdo;
    }

    public static function connect(array $c, bool $withDatabase = true): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;%scharset=%s',
            $c['host'] ?? '127.0.0.1', (int) ($c['port'] ?? 3306),
            $withDatabase ? 'dbname=' . ($c['name'] ?? '') . ';' : '',
            $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    }

    public static function stmt(string $sql, array $params = []): \PDOStatement
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($params);
        return $s;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::stmt($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::stmt($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = [])
    {
        $r = self::stmt($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::stmt($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::stmt($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Placeholders for an IN() list: [ '?,?,?', [..ids] ] */
    public static function in(array $values): array
    {
        $values = array_values($values);
        if (!$values) {
            return ['NULL', []];
        }
        return [implode(',', array_fill(0, count($values), '?')), $values];
    }

    /** Run $fn in a transaction; rolls back and rethrows on any failure. */
    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function isDuplicate(Throwable $e): bool
    {
        return $e instanceof PDOException && (($e->errorInfo[1] ?? 0) === 1062);
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
