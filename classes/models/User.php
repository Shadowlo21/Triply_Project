<?php

abstract class User
{
    protected int    $id;
    protected string $email;
    protected string $role;


    protected string $name            = '';
    protected string $phone           = '';
    protected string $nationality     = '';
    protected string $emergencyContact = '';
    protected int    $points          = 0;

    public function __construct(int $id, string $email, string $role)
    {
        $this->id    = $id;
        $this->email = $email;
        $this->role  = $role;
    }




    public function decryptData(string $blob): void
    {
        $data = Encryption::decryptJson($blob, $this->id);

        $this->name             = $data['name']             ?? '';
        $this->phone            = $data['phone']            ?? '';
        $this->nationality      = $data['nationality']      ?? '';
        $this->emergencyContact = $data['emergency_contact'] ?? '';
        $this->points           = (int)($data['points']    ?? 0);
    }




    public function encryptData(): string
    {
        return Encryption::encryptJson([
            'name'              => $this->name,
            'phone'             => $this->phone,
            'nationality'       => $this->nationality,
            'emergency_contact' => $this->emergencyContact,
            'points'            => $this->points,
        ], $this->id);
    }




    public static function findById(int $id): ?static
    {
        $db   = Database::getInstance('accounts');
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();

        if (!$row) return null;

        $user = match ($row['role']) {
            'admin'  => new Admin($row['id'], $row['email'], $row['role']),
            'leader' => new TripLeader($row['id'], $row['email'], $row['role']),
            default  => new Member($row['id'], $row['email'], $row['role']),
        };

        $user->decryptData($row['data']);
        return $user;
    }




    public static function findByEmail(string $email): ?array
    {
        $db   = Database::getInstance('accounts');
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function updateProfile(array $fields): void
    {
        foreach ($fields as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $db   = Database::getInstance('accounts');
        $stmt = $db->prepare('UPDATE users SET data = ? WHERE id = ?');
        $stmt->execute([$this->encryptData(), $this->id]);
    }

    public function viewTrip(int $tripId): ?array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            "SELECT t.* FROM trips t
             JOIN trip_members tm ON tm.trip_id = t.id
             WHERE t.id = ? AND tm.user_id = ? AND tm.status = 'accepted'"
        );
        $stmt->execute([$tripId, $this->id]);
        return $stmt->fetch() ?: null;
    }




    public function getId(): int
    {
        return $this->id;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getRole(): string
    {
        return $this->role;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getPoints(): int
    {
        return $this->points;
    }
    public function getNationality(): string
    {
        return $this->nationality;
    }
    public function getPhone(): string
    {
        return $this->phone;
    }
    public function getEmergencyContact(): string
    {
        return $this->emergencyContact;
    }
}
