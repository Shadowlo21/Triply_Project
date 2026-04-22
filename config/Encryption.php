<?php

class Encryption
{
    private const ALGO   = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;
    private const CONTEXT = 'triply_user_data';

    
    
    
    public static function deriveKey(int $userId): string
    {
        $masterKey = base64_decode($_ENV['TRIPLY_MASTER_KEY'] ?? '');

        if (strlen($masterKey) !== 32) {
            throw new RuntimeException('TRIPLY_MASTER_KEY must be 32 bytes (base64-encoded).');
        }

        return hash_hkdf('sha256', $masterKey, 32, self::CONTEXT, (string)$userId);
    }

    
    
    
    
    public static function encryptJson(array $data, int $userId): string
    {
        $key  = self::deriveKey($userId);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $iv   = random_bytes(self::IV_LEN);
        $tag  = '';

        $cipher = openssl_encrypt(
            $json,
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    
    
    
    public static function decryptJson(string $blob, int $userId): array
    {
        $key = self::deriveKey($userId);
        $raw = base64_decode($blob);

        if (strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) {
            throw new RuntimeException('Invalid encrypted blob.');
        }

        $iv     = substr($raw, 0, self::IV_LEN);
        $tag    = substr($raw, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($raw, self::IV_LEN + self::TAG_LEN);

        $json = openssl_decrypt(
            $cipher,
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($json === false) {
            throw new RuntimeException('Decryption failed — data may be tampered.');
        }

        return json_decode($json, true);
    }

    
    
    
    
    
    
    
    
    private const MAGIC_LEN = 16;

    public static function encryptFile(string $bytes, int $userId): string
    {
        $key   = self::deriveKey($userId);
        $magic = substr($bytes, 0, self::MAGIC_LEN);
        $rest  = substr($bytes, self::MAGIC_LEN);

        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $enc = openssl_encrypt($magic, self::ALGO, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        if ($enc === false) {
            throw new RuntimeException('File encryption failed.');
        }

        return $iv . $tag . $enc . $rest;
    }

    
    
    
    public static function decryptFile(string $raw, int $userId): string
    {
        $key    = self::deriveKey($userId);
        $iv     = substr($raw, 0, self::IV_LEN);
        $tag    = substr($raw, self::IV_LEN, self::TAG_LEN);
        $enc    = substr($raw, self::IV_LEN + self::TAG_LEN, self::MAGIC_LEN);
        $rest   = substr($raw, self::IV_LEN + self::TAG_LEN + self::MAGIC_LEN);

        $magic = openssl_decrypt($enc, self::ALGO, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($magic === false) {
            throw new RuntimeException('File decryption failed — may be tampered.');
        }

        return $magic . $rest;
    }

    
    
    
    public static function generateMasterKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
