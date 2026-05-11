<?php
class Database
{

    private const DBS = [
        'accounts'  => 'database/accounts.db',
        'trips'     => 'database/trips.db',
        'financial' => 'database/financial.db',
        'social'    => 'database/social.db',
        'documents' => 'database/documents.db',
    ];


    private const TABLE_MAP = [
        'users'               => 'accounts',
        'sessions'            => 'accounts',
        'trips'               => 'trips',
        'trip_members'        => 'trips',
        'activities'          => 'trips',
        'activity_attendance' => 'trips',
        'itinerary_versions'  => 'trips',
        'comments'            => 'trips',
        'shared_items'        => 'trips',
        'notifications'       => 'trips',
        'expenses'            => 'financial',
        'expense_splits'      => 'financial',
        'settlements'         => 'financial',
        'currency_rates'      => 'financial',
        'polls'               => 'social',
        'poll_options'        => 'social',
        'votes'               => 'social',
        'documents'           => 'documents',
    ];


    private static array $instances = [];

    private function __construct() {}
    private function __clone() {}






    public static function getInstance(string $db = 'accounts'): PDO
    {
        if (!isset(self::$instances[$db])) {
            $path    = self::resolvePath(self::DBS[$db] ?? self::DBS['accounts']);
            $pdo     = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode  = WAL');
            self::$instances[$db] = $pdo;
        }

        return self::$instances[$db];
    }





    public static function for(string $table): PDO
    {
        $db = self::TABLE_MAP[$table] ?? 'accounts';
        return self::getInstance($db);
    }


    private static function resolvePath(string $relative): string
    {

        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative)) {
            return $relative;
        }
        $dir = realpath(__DIR__ . '/../' . dirname($relative));
        return $dir . DIRECTORY_SEPARATOR . basename($relative);
    }
}
