<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
                $paidByIds    = array_unique(array_column($expenses, 'paid_by'));
                $accountsDb   = Database::getInstance('accounts');
                $placeholders = implode(',', array_fill(0, count($paidByIds), '?'));
                $userStmt     = $accountsDb->prepare(
                    "SELECT id, email FROM users WHERE id IN ({$placeholders})"
                );
                $userStmt->execute($paidByIds);
                $userEmails = [];
                foreach ($userStmt->fetchAll() as $u) {
                    $userEmails[$u['id']] = $u['email'];
                }
                foreach ($expenses as &$exp) {
                    $exp['paid_by_email'] = $userEmails[$exp['paid_by']] ?? '';
                }
            }


            $splitStmt = $financialDb->prepare(
                'SELECT * FROM expense_splits WHERE expense_id = ?'
            );
            foreach ($expenses as &$exp) {
                $splitStmt->execute([$exp['id']]);
                $splits = $splitStmt->fetchAll();


                if (!empty($splits)) {
                    $splitUserIds  = array_unique(array_column($splits, 'user_id'));
                    $accountsDb    = Database::getInstance('accounts');
                    $placeholders2 = implode(',', array_fill(0, count($splitUserIds), '?'));
                    $splitUserStmt = $accountsDb->prepare(
                        "SELECT id, email FROM users WHERE id IN ({$placeholders2})"
                    );
                    $splitUserStmt->execute($splitUserIds);
                    $splitEmails = [];
                    foreach ($splitUserStmt->fetchAll() as $u) {
                        $splitEmails[$u['id']] = $u['email'];
                    }
                    foreach ($splits as &$s) {
                        $s['email'] = $splitEmails[$s['user_id']] ?? '';
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


            $expense   = Expense::findById($expenseId);
            $splitType = $_POST['split_type'] ?? 'equal';
            $memberIds = array_map('intval', $_POST['member_ids'] ?? []);

            if (empty($memberIds)) {

                $tripsDb = Database::getInstance('trips');
                $stmt    = $tripsDb->prepare('SELECT user_id FROM trip_members WHERE trip_id = ?');
                $stmt->execute([$tripId]);
                $memberIds = array_column($stmt->fetchAll(), 'user_id');
            }

            match ($splitType) {
                'equal'      => $expense->splitEqual($memberIds),
                'percentage' => $expense->splitByPercentage(
                    array_combine($memberIds, array_map('floatval', $_POST['percentages'] ?? []))
                ),
                'custom'     => $expense->splitCustom(
                    array_combine($memberIds, array_map('floatval', $_POST['amounts'] ?? []))
                ),
                default      => $expense->splitEqual($memberIds),
            };


            Notification::checkBudgetThreshold($tripId);

            ApiResponse::success(['expense_id' => $expenseId], 'Expense logged.');


        case 'settlement':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            $transactions = Itinerary::calculateSettlement($tripId);


            if (!empty($transactions)) {
                $allIds = array_unique(array_merge(
                    array_column($transactions, 'from'),
                    array_column($transactions, 'to')
                ));
                $accountsDb   = Database::getInstance('accounts');
                $placeholders = implode(',', array_fill(0, count($allIds), '?'));
                $stmt         = $accountsDb->prepare(
                    "SELECT id, email FROM users WHERE id IN ({$placeholders})"
                );
                $stmt->execute($allIds);
                $users = array_column($stmt->fetchAll(), 'email', 'id');

                foreach ($transactions as &$t) {
                    $t['from_email'] = $users[$t['from']] ?? '';
                    $t['to_email']   = $users[$t['to']]   ?? '';
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

            ApiResponse::success(null, $ok ? 'Settlement approved.' : 'Failed.');


        case 'rates':
            $db   = Database::getInstance('financial');
            $stmt = $db->query('SELECT from_currency, to_currency, rate FROM currency_rates');
            ApiResponse::success($stmt->fetchAll());

        /**
         * Delete an expense
         */
        case 'delete_expense':
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            if (!$expenseId) ApiResponse::error('expense_id required.');

            // Get expense details
            $db = Database::getInstance('financial');
            $stmt = $db->prepare('SELECT * FROM expenses WHERE id = ?');
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if (!$expense) ApiResponse::error('Expense not found.', 404);

            // Verify user has access to the trip
            if (!$user->viewTrip($expense['trip_id'])) {
                ApiResponse::error('Access denied.', 403);
            }

            // Only expense creator, trip leader, or admin can delete
            if ($expense['paid_by'] !== $user->getId() && !($user instanceof TripLeader) && $user->getRole() !== 'admin') {
                ApiResponse::error('Only expense creator or trip leader can delete.', 403);
            }

            // Delete expense splits first
            $db->prepare('DELETE FROM expense_splits WHERE expense_id = ?')->execute([$expenseId]);

            // Delete expense
            $db->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expenseId]);

            ApiResponse::success(null, 'Expense deleted.');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
