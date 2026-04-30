<?php

/**
 * Design Pattern: Factory
 * Creates appropriate user objects (Member or TripLeader) based on role during login.
 * Centralizes user instantiation logic in login() and register() methods.
 */
class Auth
{
    private const COOKIE_NAME   = 'triply_token';
    private const TOKEN_TTL     = 604800;
    private const COOKIE_SECURE = false;




    public static function register(
        string $email,
        string $password,
        string $name,
        string $phone,
        string $nationality,
        string $role = 'member'
    ): int {
        $db = Database::getInstance('accounts');

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Email already registered.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare(
            'INSERT INTO users (email, password_hash, role, data) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$email, $hash, $role, '']);
        $userId = (int)$db->lastInsertId();

        $encryptedData = Encryption::encryptJson([
            'name'              => $name,
            'phone'             => $phone,
            'nationality'       => $nationality,
            'emergency_contact' => '',
            'points'            => 0,
        ], $userId);

        $db->prepare('UPDATE users SET data = ? WHERE id = ?')
            ->execute([$encryptedData, $userId]);

        return $userId;
    }




    public static function login(string $email, string $password): User
    {
        $row = User::findByEmail($email);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            throw new RuntimeException('Invalid credentials.');
        }

        $user = match ($row['role']) {
            'leader', 'admin' => new TripLeader($row['id'], $row['email'], $row['role']),
            default            => new Member($row['id'], $row['email'], $row['role']),
        };
        $user->decryptData($row['data']);

        self::issueToken((int)$row['id']);

        return $user;
    }




    public static function logout(): void
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;

        if ($raw) {
            $hash = self::hashToken($raw);
            Database::getInstance('accounts')
                ->prepare('DELETE FROM sessions WHERE token_hash = ?')
                ->execute([$hash]);
        }

        self::clearCookie();
    }




    public static function current(): ?User
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$raw) return null;

        $db   = Database::getInstance('accounts');
        $hash = self::hashToken($raw);

        $stmt = $db->prepare(
            "SELECT s.user_id FROM sessions s
             WHERE s.token_hash = ?
               AND datetime(s.expires_at) > datetime('now')"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            self::clearCookie();
            return null;
        }

        return User::findById((int)$row['user_id']);
    }




    public static function require(): User
    {
        $user = self::current();
        if (!$user) {
            $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            } else {
                header('Location: /?page=login');
            }
            exit;
        }
        return $user;
    }




    public static function requireRole(string $role): User
    {
        $user = self::require();
        if ($user->getRole() !== $role && $user->getRole() !== 'admin') {
            http_response_code(403);
            exit('Forbidden');
        }
        return $user;
    }




    public static function revokeAll(int $userId): void
    {
        Database::getInstance('accounts')
            ->prepare('DELETE FROM sessions WHERE user_id = ?')
            ->execute([$userId]);
    }




    public static function purgeExpired(): void
    {
        Database::getInstance('accounts')
            ->exec("DELETE FROM sessions WHERE datetime(expires_at) <= datetime('now')");
    }




    private static function issueToken(int $userId): void
    {

        $raw  = base64_encode(random_bytes(32));
        $hash = self::hashToken($raw);
        $exp  = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);

        Database::getInstance('accounts')
            ->prepare('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
            ->execute([$hash, $userId, $exp]);

        self::setCookie($raw, time() + self::TOKEN_TTL);
    }

    private static function hashToken(string $raw): string
    {

        return hash('sha256', $raw);
    }

    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => self::COOKIE_SECURE,
        ]);
    }

    private static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => self::COOKIE_SECURE,
        ]);
    }
}
