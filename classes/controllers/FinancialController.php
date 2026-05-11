<?php

class FinancialController
{
    public function handle(User $user, string $action): void
    {
        try {
            switch ($action) {

                case 'list':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $financialDb = Database::getInstance('financial');
                    $stmt        = $financialDb->prepare(
                        'SELECT * FROM expenses WHERE trip_id = ? ORDER BY created_at DESC'
                    );
                    $stmt->execute([$tripId]);
                    $expenses = $stmt->fetchAll();

                    if (!empty($expenses)) {
                        $paidByIds    = array_values(array_unique(array_column($expenses, 'paid_by')));
                        $accountsDb   = Database::getInstance('accounts');
                        $placeholders = implode(',', array_fill(0, count($paidByIds), '?'));
                        $userStmt     = $accountsDb->prepare(
                            "SELECT id, data FROM users WHERE id IN ({$placeholders})"
                        );
                        $userStmt->execute($paidByIds);
                        $userNames = [];
                        foreach ($userStmt->fetchAll() as $u) {
                            try {
                                $d = Encryption::decryptJson($u['data'], (int)$u['id']);
                                $userNames[$u['id']] = $d['name'] ?? '';
                            } catch (\Throwable $ignored) {}
                        }
                        foreach ($expenses as &$exp) {
                            $exp['paid_by_name'] = $userNames[$exp['paid_by']] ?? '';
                        }
                    }

                    $splitStmt = $financialDb->prepare(
                        'SELECT * FROM expense_splits WHERE expense_id = ?'
                    );
                    foreach ($expenses as &$exp) {
                        $splitStmt->execute([$exp['id']]);
                        $splits = $splitStmt->fetchAll();

                        if (!empty($splits)) {
                            $splitUserIds  = array_values(array_unique(array_column($splits, 'user_id')));
                            $accountsDb    = Database::getInstance('accounts');
                            $placeholders2 = implode(',', array_fill(0, count($splitUserIds), '?'));
                            $splitUserStmt = $accountsDb->prepare(
                                "SELECT id, data FROM users WHERE id IN ({$placeholders2})"
                            );
                            $splitUserStmt->execute($splitUserIds);
                            $splitNames = [];
                            foreach ($splitUserStmt->fetchAll() as $u) {
                                try {
                                    $d = Encryption::decryptJson($u['data'], (int)$u['id']);
                                    $splitNames[$u['id']] = $d['name'] ?? '';
                                } catch (\Throwable $ignored) {}
                            }
                            foreach ($splits as &$s) {
                                $s['name'] = $splitNames[$s['user_id']] ?? '';
                            }
                        }

                        $exp['splits'] = $splits;
                    }

                    $trip = Trip::findById($tripId);
                    ApiResponse::success([
                        'expenses'     => $expenses,
                        'total_spent'  => $trip->getBudgetUsed(),
                        'budget_limit' => $trip->getBudgetLimit(),
                        'currency'     => $trip->getBaseCurrency(),
                    ]);

                case 'add':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
                    if (empty($_POST['title']) || empty($_POST['amount'])) {
                        ApiResponse::error('title and amount required.');
                    }
                    if ((float)$_POST['amount'] <= 0) ApiResponse::error('Amount must be positive.');

                    $splitType = $_POST['split_type'] ?? 'equal';
                    $memberIds = array_map('intval', $_POST['member_ids'] ?? []);

                    if (empty($memberIds)) {
                        $tripsDb = Database::getInstance('trips');
                        $stmt    = $tripsDb->prepare('SELECT user_id FROM trip_members WHERE trip_id = ?');
                        $stmt->execute([$tripId]);
                        $memberIds = array_column($stmt->fetchAll(), 'user_id');
                    }

                    if (empty($memberIds)) ApiResponse::error('Trip has no members to split with.');

                    $splitMap = null;
                    if ($splitType === 'percentage' || $splitType === 'custom') {
                        $values = $splitType === 'percentage'
                            ? array_map('floatval', $_POST['percentages'] ?? [])
                            : array_map('floatval', $_POST['amounts'] ?? []);

                        if (count($memberIds) !== count($values)) {
                            ApiResponse::error('member_ids and ' . ($splitType === 'percentage' ? 'percentages' : 'amounts') . ' must have the same length.');
                        }

                        if ($splitType === 'custom') {
                            $sum = array_sum($values);
                            $amt = (float)$_POST['amount'];
                            if (abs($sum - $amt) > 0.01) {
                                ApiResponse::error("Custom amounts must sum to {$amt}, got {$sum}.");
                            }
                        } else {
                            $sum = array_sum($values);
                            if (abs($sum - 100) > 0.01) {
                                ApiResponse::error("Percentages must sum to 100, got {$sum}.");
                            }
                        }

                        $splitMap = array_combine($memberIds, $values);
                    }

                    $trip             = Trip::findById($tripId);
                    $originalCurrency = $_POST['currency'] ?? $trip->getBaseCurrency();
                    $amount           = (float)$_POST['amount'];

                    $converted = Expense::convertCurrency(
                        $amount,
                        $originalCurrency,
                        $trip->getBaseCurrency()
                    );

                    $expenseId = $user->logExpense($tripId, [
                        'title'             => trim($_POST['title']),
                        'amount'            => $amount,
                        'original_currency' => $originalCurrency,
                        'converted_amount'  => $converted,
                        'type'              => $_POST['type'] ?? 'general',
                    ]);

                    $expense = Expense::findById($expenseId);

                    if ($splitType === 'percentage')   $expense->splitByPercentage($splitMap);
                    elseif ($splitType === 'custom')   $expense->splitCustom($splitMap);
                    else                               $expense->splitEqual($memberIds);

                    Notification::checkBudgetThreshold($tripId);

                    ApiResponse::success(['expense_id' => $expenseId], 'Expense logged.');

                case 'settlement':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $transactions = Itinerary::calculateSettlement($tripId);

                    if (!empty($transactions)) {
                        $allIds = array_values(array_unique(array_merge(
                            array_column($transactions, 'from'),
                            array_column($transactions, 'to')
                        )));
                        $accountsDb   = Database::getInstance('accounts');
                        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
                        $stmt         = $accountsDb->prepare(
                            "SELECT id, data FROM users WHERE id IN ({$placeholders})"
                        );
                        $stmt->execute($allIds);
                        $names = [];
                        foreach ($stmt->fetchAll() as $u) {
                            try {
                                $d = Encryption::decryptJson($u['data'], (int)$u['id']);
                                $names[$u['id']] = $d['name'] ?? '';
                            } catch (\Throwable $ignored) {}
                        }
                        foreach ($transactions as &$t) {
                            $t['from_name'] = $names[$t['from']] ?? '';
                            $t['to_name']   = $names[$t['to']]   ?? '';
                        }
                    }

                    $trip = Trip::findById($tripId);
                    ApiResponse::success([
                        'transactions' => $transactions,
                        'currency'     => $trip->getBaseCurrency(),
                    ]);

                case 'approve_settlement':
                    $settlementId = (int)($_POST['settlement_id'] ?? 0);
                    if (!$settlementId) ApiResponse::error('settlement_id required.');

                    if (!($user instanceof Member)) ApiResponse::error('Not allowed.');
                    $ok = $user->approveSettlement($settlementId);
                    if (!$ok) ApiResponse::error('Settlement not found.', 404);

                    ApiResponse::success(null, 'Settlement approved.');

                case 'rates':
                    $db   = Database::getInstance('financial');
                    $stmt = $db->query('SELECT from_currency, to_currency, rate FROM currency_rates');
                    ApiResponse::success($stmt->fetchAll());

                case 'delete_expense':
                    $expenseId = (int)($_POST['expense_id'] ?? 0);
                    if (!$expenseId) ApiResponse::error('expense_id required.');

                    $db = Database::getInstance('financial');
                    $stmt = $db->prepare('SELECT * FROM expenses WHERE id = ?');
                    $stmt->execute([$expenseId]);
                    $expense = $stmt->fetch();

                    if (!$expense) ApiResponse::error('Expense not found.', 404);

                    if (!$user->viewTrip($expense['trip_id'])) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    if ($expense['paid_by'] !== $user->getId() && !($user instanceof TripLeader) && $user->getRole() !== 'admin') {
                        ApiResponse::error('Only expense creator or trip leader can delete.', 403);
                    }

                    $db->prepare('DELETE FROM expense_splits WHERE expense_id = ?')->execute([$expenseId]);
                    $db->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expenseId]);

                    ApiResponse::success(null, 'Expense deleted.');

                default:
                    ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }
}
