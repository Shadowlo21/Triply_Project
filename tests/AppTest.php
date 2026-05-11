<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/models/Trip.php';
require_once __DIR__ . '/../classes/services/Itinerary.php';
require_once __DIR__ . '/../classes/models/Activity.php';

class AppTest extends TestCase
{
    private PDO $accountsDb;
    private PDO $tripsDb;
    private PDO $financialDb;

    protected function setUp(): void
    {
        $this->accountsDb   = $this->createMemoryDatabase();
        $this->tripsDb      = $this->createMemoryDatabase();
        $this->financialDb  = $this->createMemoryDatabase();

        $this->setDatabaseInstances();
        $this->createSchema();
    }

    private function createMemoryDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    private function setDatabaseInstances(): void
    {
        $reflection = new ReflectionClass(Database::class);
        $property   = $reflection->getProperty('instances');
        $property->setValue(null, [
            'accounts' => $this->accountsDb,
            'trips'    => $this->tripsDb,
            'financial' => $this->financialDb,
        ]);
    }

    private function createSchema(): void
    {
        $this->accountsDb->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                email TEXT NOT NULL,
                role TEXT NOT NULL,
                data TEXT
            )'
        );

        $this->tripsDb->exec(
            'CREATE TABLE trips (
                id INTEGER PRIMARY KEY,
                title TEXT,
                destination TEXT,
                start_date TEXT,
                end_date TEXT,
                base_currency TEXT,
                budget_limit REAL,
                max_slots INTEGER,
                created_by INTEGER,
                status TEXT,
                required_docs TEXT
            )'
        );
        $this->tripsDb->exec(
            'CREATE TABLE trip_members (
                trip_id INTEGER,
                user_id INTEGER,
                role TEXT,
                status TEXT,
                can_edit INTEGER DEFAULT 0
            )'
        );
        $this->tripsDb->exec(
            'CREATE TABLE activities (
                id INTEGER PRIMARY KEY,
                trip_id INTEGER,
                title TEXT,
                location TEXT,
                lat REAL,
                lng REAL,
                datetime TEXT,
                duration_min INTEGER,
                status TEXT,
                transport_mode TEXT,
                created_by INTEGER
            )'
        );

        $this->financialDb->exec(
            'CREATE TABLE expenses (
                id INTEGER PRIMARY KEY,
                trip_id INTEGER,
                title TEXT,
                amount REAL,
                original_currency TEXT,
                converted_amount REAL,
                type TEXT,
                paid_by INTEGER
            )'
        );
        $this->financialDb->exec(
            'CREATE TABLE expense_splits (
                expense_id INTEGER,
                user_id INTEGER,
                amount REAL,
                percentage REAL
            )'
        );
    }

    private function insertTrip(): int
    {
        $stmt = $this->tripsDb->prepare(
            'INSERT INTO trips (title, destination, start_date, end_date, base_currency, created_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['Test Trip', 'Nowhere', '2026-01-01', '2026-01-05', 'USD', 1, 'planning']);
        return (int)$this->tripsDb->lastInsertId();
    }

    private function insertUser(int $id, string $email, string $role = 'member'): void
    {
        $stmt = $this->accountsDb->prepare(
            'INSERT INTO users (id, email, role, data) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, $email, $role, '']);
    }

    public function testCalculateSettlement_noExpenses_returnsEmpty(): void
    {
        $tripId = $this->insertTrip();

        $result = Itinerary::calculateSettlement($tripId);

        $this->assertSame([], $result);
    }

    public function testCalculateSettlement_creditorAndDebtor_returnsTransaction(): void
    {
        $tripId = $this->insertTrip();

        $this->financialDb->prepare(
            'INSERT INTO expenses (trip_id, title, amount, original_currency, converted_amount, type, paid_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tripId, 'Hotel', 100.0, 'USD', 100.0, 'lodging', 1]);

        $this->financialDb->prepare(
            'INSERT INTO expenses (trip_id, title, amount, original_currency, converted_amount, type, paid_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tripId, 'Dinner', 50.0, 'USD', 50.0, 'food', 2]);

        $this->financialDb->prepare(
            'INSERT INTO expense_splits (expense_id, user_id, amount, percentage) VALUES (?, ?, ?, ?)'
        )
            ->execute([1, 1, 50.0, 50.0]);
        $this->financialDb->prepare(
            'INSERT INTO expense_splits (expense_id, user_id, amount, percentage) VALUES (?, ?, ?, ?)'
        )
            ->execute([1, 2, 50.0, 50.0]);
        $this->financialDb->prepare(
            'INSERT INTO expense_splits (expense_id, user_id, amount, percentage) VALUES (?, ?, ?, ?)'
        )
            ->execute([2, 1, 25.0, 50.0]);
        $this->financialDb->prepare(
            'INSERT INTO expense_splits (expense_id, user_id, amount, percentage) VALUES (?, ?, ?, ?)'
        )
            ->execute([2, 2, 25.0, 50.0]);

        $result = Itinerary::calculateSettlement($tripId);

        $this->assertCount(1, $result);
        $this->assertSame([
            ['from' => 2, 'to' => 1, 'amount' => 25.0],
        ], $result);
    }

    public function testGetMembers_noAcceptedMembers_returnsEmpty(): void
    {
        $tripId = $this->insertTrip();
        $trip = new Trip(['id' => $tripId, 'title' => 'Test Trip', 'destination' => 'Nowhere', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05', 'base_currency' => 'USD', 'budget_limit' => null, 'max_slots' => null, 'created_by' => 1, 'status' => 'planning', 'required_docs' => null]);

        $this->assertSame([], $trip->getMembers());
    }

    public function testGetMembers_withAcceptedMembers_returnsMemberDetails(): void
    {
        $tripId = $this->insertTrip();
        $this->insertUser(1, 'leader@example.com', 'leader');
        $this->insertUser(2, 'member@example.com', 'member');

        $this->tripsDb->prepare(
            'INSERT INTO trip_members (trip_id, user_id, role, status, can_edit) VALUES (?, ?, ?, ?, ?)'
        )->execute([$tripId, 2, 'member', 'accepted', 0]);

        $trip = new Trip(['id' => $tripId, 'title' => 'Test Trip', 'destination' => 'Nowhere', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05', 'base_currency' => 'USD', 'budget_limit' => null, 'max_slots' => null, 'created_by' => 1, 'status' => 'planning', 'required_docs' => null]);

        $members = $trip->getMembers();

        $this->assertCount(1, $members);
        $this->assertSame(2, $members[0]['id']);
        $this->assertSame('member@example.com', $members[0]['email']);
        $this->assertSame('member', $members[0]['role']);
        $this->assertSame('member', $members[0]['trip_role']);
    }

    public function testDetectConflicts_noActivities_returnsEmpty(): void
    {
        $tripId = $this->insertTrip();

        $itinerary = new Itinerary($tripId);
        $this->assertSame([], $itinerary->detectConflicts());
    }

    public function testDetectConflicts_overlappingActivities_returnsConflictPair(): void
    {
        $tripId = $this->insertTrip();

        $insert = $this->tripsDb->prepare(
            'INSERT INTO activities (trip_id, title, datetime, duration_min, status, transport_mode, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $insert->execute([$tripId, 'A', '2026-01-01 10:00:00', 60, 'confirmed', 'car', 1]);
        $insert->execute([$tripId, 'B', '2026-01-01 10:30:00', 60, 'confirmed', 'car', 1]);

        $itinerary = new Itinerary($tripId);
        $conflicts = $itinerary->detectConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertSame(1, $conflicts[0]['a']->getId());
        $this->assertSame(2, $conflicts[0]['b']->getId());
    }

    public function testDetectConflicts_nonOverlappingActivities_returnsEmpty(): void
    {
        $tripId = $this->insertTrip();

        $insert = $this->tripsDb->prepare(
            'INSERT INTO activities (trip_id, title, datetime, duration_min, status, transport_mode, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $insert->execute([$tripId, 'A', '2026-01-01 10:00:00', 60, 'confirmed', 'car', 1]);
        $insert->execute([$tripId, 'B', '2026-01-01 11:10:00', 60, 'confirmed', 'car', 1]);

        $itinerary = new Itinerary($tripId);
        $this->assertSame([], $itinerary->detectConflicts());
    }
}
