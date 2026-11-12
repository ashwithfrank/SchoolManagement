<?php
namespace App\Core;

/** Password-based AES-256-GCM encryption for backup files. Format: MAGIC | salt16 | iv12 | tag16 | ciphertext */
class BackupCrypto
{
    public const MAGIC = "AOSBAK1\n";
    private const ITER = 200000;

    private static function key(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, $salt, self::ITER, 32, true);
    }

    public static function isEncrypted(string $head): bool
    {
        return strncmp($head, self::MAGIC, strlen(self::MAGIC)) === 0;
    }

    public static function encrypt(string $plain, string $password): string
    {
        if ($password === '') {
            throw new UserError('Enter a password for the encrypted backup.');
        }
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key($password, $salt), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::MAGIC . $salt . $iv . $tag . $ct;
    }

    public static function decrypt(string $blob, string $password): string
    {
        $m = strlen(self::MAGIC);
        if (strlen($blob) < $m + 44 || !self::isEncrypted($blob)) {
            throw new UserError('This is not an encrypted AlphaOmega backup.');
        }
        $salt = substr($blob, $m, 16);
        $iv = substr($blob, $m + 16, 12);
        $tag = substr($blob, $m + 28, 16);
        $ct = substr($blob, $m + 44);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', self::key($password, $salt), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new UserError('Wrong password, or the backup file is damaged.');
        }
        return $plain;
    }
}
