<?php

class Auth
{
    private const COOKIE_NAME    = 'triply_token';
    private const TOKEN_TTL      = 604800;
    private const COOKIE_SECURE  = false;

    private const LOGIN_WINDOW_SECONDS = 900;
    private const LOGIN_MAX_ATTEMPTS   = 5;

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
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $emailKey = 'email:' . strtolower($email);
        $ipKey    = 'ip:' . $ip;

        $remaining = self::throttleCheck([$emailKey, $ipKey]);
        if ($remaining !== null) {
            throw new RuntimeException("Too many failed attempts. Try again in {$remaining} seconds.");
        }

        $row = User::findByEmail($email);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            self::recordFailedAttempt([$emailKey, $ipKey]);
            throw new RuntimeException('Invalid credentials.');
        }

        self::clearAttempts([$emailKey, $ipKey]);

        $user = match ($row['role']) {
            'admin'  => new Admin($row['id'], $row['email'], $row['role']),
            'leader' => new TripLeader($row['id'], $row['email'], $row['role']),
            default  => new Member($row['id'], $row['email'], $row['role']),
        };
        $user->decryptData($row['data']);

        self::issueToken((int)$row['id']);

        return $user;
    }

    private static function throttleCheck(array $identifiers): ?int
    {
        $db = Database::getInstance('accounts');
        $db->exec(
            "DELETE FROM login_attempts WHERE datetime(attempt_at) < datetime('now', '-" . self::LOGIN_WINDOW_SECONDS . " seconds')"
        );

        $maxBlockSeconds = 0;
        foreach ($identifiers as $id) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS cnt, MIN(attempt_at) AS oldest
                 FROM login_attempts
                 WHERE identifier = ?
                   AND datetime(attempt_at) >= datetime('now', '-" . self::LOGIN_WINDOW_SECONDS . " seconds')"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['cnt'] < self::LOGIN_MAX_ATTEMPTS) continue;

            $unlockTs = strtotime($row['oldest']) + self::LOGIN_WINDOW_SECONDS;
            $remaining = $unlockTs - time();
            if ($remaining > $maxBlockSeconds) $maxBlockSeconds = $remaining;
        }
        return $maxBlockSeconds > 0 ? $maxBlockSeconds : null;
    }

    private static function recordFailedAttempt(array $identifiers): void
    {
        $db = Database::getInstance('accounts');
        $stmt = $db->prepare('INSERT INTO login_attempts (identifier) VALUES (?)');
        foreach ($identifiers as $id) {
            $stmt->execute([$id]);
        }
    }

    private static function clearAttempts(array $identifiers): void
    {
        $db = Database::getInstance('accounts');
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE identifier = ?');
        foreach ($identifiers as $id) {
            $stmt->execute([$id]);
        }
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
        self::verifyCsrf();
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
        $csrf = bin2hex(random_bytes(32));

        Database::getInstance('accounts')
            ->prepare('INSERT INTO sessions (token_hash, user_id, expires_at, csrf_token) VALUES (?, ?, ?, ?)')
            ->execute([$hash, $userId, $exp, $csrf]);

        self::setCookie($raw, time() + self::TOKEN_TTL);
    }

    public static function csrfToken(): ?string
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$raw) return null;
        $hash = self::hashToken($raw);
        $db   = Database::getInstance('accounts');
        $stmt = $db->prepare(
            "SELECT csrf_token FROM sessions WHERE token_hash = ? AND datetime(expires_at) > datetime('now')"
        );
        $stmt->execute([$hash]);
        $token = $stmt->fetchColumn();
        if ($token) return $token;
        if ($token === false) return null;

        $newToken = bin2hex(random_bytes(32));
        $db->prepare('UPDATE sessions SET csrf_token = ? WHERE token_hash = ?')
           ->execute([$newToken, $hash]);
        return $newToken;
    }

    public static function verifyCsrf(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

        $expected = self::csrfToken();
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';

        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token. Please refresh the page.']);
            exit;
        }
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
