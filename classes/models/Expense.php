<?php
class Expense
{
    private int    $id;
    private int    $tripId;
    private string $title;
    private float  $amount;
    private string $originalCurrency;
    private float  $convertedAmount;
    private string $type;
    private int    $paidBy;

    public function __construct(array $row)
    {
        $this->id               = (int)$row['id'];
        $this->tripId           = (int)$row['trip_id'];
        $this->title            = $row['title'];
        $this->amount           = (float)$row['amount'];
        $this->originalCurrency = $row['original_currency'];
        $this->convertedAmount  = (float)$row['converted_amount'];
        $this->type             = $row['type'];
        $this->paidBy           = (int)$row['paid_by'];
    }

    public static function findById(int $id): ?self
    {
        $db   = Database::getInstance('financial');
        $stmt = $db->prepare('SELECT * FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }




    public static function convertCurrency(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;

        $db   = Database::getInstance('financial');
        $stmt = $db->prepare(
            'SELECT rate FROM currency_rates WHERE from_currency = ? AND to_currency = ?'
        );
        $stmt->execute([$from, $to]);
        $row  = $stmt->fetch();

        if (!$row) throw new RuntimeException("No rate found for {$from} → {$to}");

        return round($amount * (float)$row['rate'], 2);
    }




    public function splitEqual(array $userIds): bool
    {
        if (empty($userIds)) return false;

        $share = round($this->convertedAmount / count($userIds), 2);
        return $this->saveSplits(array_fill_keys($userIds, $share));
    }




    public function splitCustom(array $splits): bool
    {
        return $this->saveSplits($splits);
    }




    public function splitByPercentage(array $percentages): bool
    {
        $splits = [];
        foreach ($percentages as $userId => $pct) {
            $splits[$userId] = round($this->convertedAmount * $pct / 100, 2);
        }
        return $this->saveSplits($splits, $percentages);
    }

    private function saveSplits(array $amounts, array $percentages = []): bool
    {
        $db   = Database::getInstance('financial');


        $db->prepare('DELETE FROM expense_splits WHERE expense_id = ?')->execute([$this->id]);

        $stmt = $db->prepare(
            'INSERT INTO expense_splits (expense_id, user_id, amount, percentage) VALUES (?, ?, ?, ?)'
        );

        foreach ($amounts as $userId => $amount) {
            $pct = $percentages[$userId] ?? null;
            $stmt->execute([$this->id, $userId, $amount, $pct]);
        }

        return true;
    }

    public function getIndividualShare(int $userId): float
    {
        $db   = Database::getInstance('financial');
        $stmt = $db->prepare(
            'SELECT amount FROM expense_splits WHERE expense_id = ? AND user_id = ?'
        );
        $stmt->execute([$this->id, $userId]);
        return (float)($stmt->fetchColumn() ?? 0);
    }

    public function getSplits(): array
    {
        $db   = Database::getInstance('financial');
        $stmt = $db->prepare('SELECT * FROM expense_splits WHERE expense_id = ?');
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }


    public function getId(): int
    {
        return $this->id;
    }
    public function getTripId(): int
    {
        return $this->tripId;
    }
    public function getConvertedAmount(): float
    {
        return $this->convertedAmount;
    }
    public function getPaidBy(): int
    {
        return $this->paidBy;
    }
    public function getOriginalCurrency(): string
    {
        return $this->originalCurrency;
    }
}
