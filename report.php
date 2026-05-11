<?php

require_once __DIR__ . '/config/bootstrap.php';

$user = Auth::current();
if (!$user) {
    http_response_code(403);
    exit('Unauthorized');
}

$tripId = (int)($_GET['trip_id'] ?? 0);
if (!$tripId) {
    http_response_code(400);
    exit('trip_id required');
}

// Verify access
if (!$user->viewTrip($tripId)) {
    http_response_code(403);
    exit('Access denied');
}

// Get trip data
$trip = Trip::findById($tripId);
if (!$trip) {
    http_response_code(404);
    exit('Trip not found');
}

// Get members
$members = $trip->getMembers();

// Get activities
$tripsDb = Database::getInstance('trips');
$stmt = $tripsDb->prepare('SELECT * FROM activities WHERE trip_id = ? AND status != "cancelled" ORDER BY datetime ASC');
$stmt->execute([$tripId]);
$activities = $stmt->fetchAll();

// Get expenses
$financialDb = Database::getInstance('financial');
$stmt = $financialDb->prepare('SELECT * FROM expenses WHERE trip_id = ? ORDER BY created_at DESC');
$stmt->execute([$tripId]);
$expenses = $stmt->fetchAll();

// Calculate totals
$totalSpent = 0;
foreach ($expenses as $exp) {
    $totalSpent += (float)$exp['converted_amount'];
}

// Generate HTML report (fallback if FPDF not available)
// This can be converted to PDF using browser's print-to-PDF or with FPDF if installed

ob_start();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Trip Report - <?= htmlspecialchars($trip->getTitle()) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 5px 0;
            color: #007bff;
        }

        .section {
            margin-top: 25px;
        }

        .section h2 {
            background: #f5f5f5;
            padding: 10px;
            border-left: 4px solid #007bff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .stat-box {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
            margin-top: 5px;
        }

        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 12px;
            color: #666;
        }

        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1><?= htmlspecialchars($trip->getTitle()) ?></h1>
        <p><strong>Destination:</strong> <?= htmlspecialchars($trip->getDestination()) ?></p>
        <p><strong>Dates:</strong> <?= htmlspecialchars($trip->getStartDate()) ?> to <?= htmlspecialchars($trip->getEndDate()) ?></p>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-label">Total Members</div>
            <div class="stat-value"><?= count($members) ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Activities</div>
            <div class="stat-value"><?= count($activities) ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value"><?= number_format($totalSpent, 2) ?> <?= htmlspecialchars($trip->getBaseCurrency()) ?></div>
        </div>
    </div>

    <div class="section">
        <h2>Trip Members (<?= count($members) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Trip Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['role']) ?></td>
                        <td><?= htmlspecialchars($m['trip_role']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Itinerary (<?= count($activities) ?> Activities)</h2>
        <?php if (empty($activities)): ?>
            <p><em>No activities planned.</em></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Location</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Transport</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['title']) ?></td>
                            <td><?= htmlspecialchars($a['location'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['datetime']) ?></td>
                            <td><?= (int)$a['duration_min'] ?> min</td>
                            <td><?= htmlspecialchars($a['transport_mode']) ?></td>
                            <td><?= htmlspecialchars($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Financial Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Total Spent</strong></td>
                    <td><strong><?= number_format($totalSpent, 2) ?> <?= htmlspecialchars($trip->getBaseCurrency()) ?></strong></td>
                </tr>
                <tr>
                    <td>Budget Limit</td>
                    <td><?= $trip->getBudgetLimit() ? number_format($trip->getBudgetLimit(), 2) . ' ' . htmlspecialchars($trip->getBaseCurrency()) : '—' ?></td>
                </tr>
                <tr>
                    <td>Budget Used</td>
                    <td><?= $trip->getBudgetLimit() ? number_format(($totalSpent / $trip->getBudgetLimit()) * 100, 1) . '%' : '—' ?></td>
                </tr>
                <tr>
                    <td>Number of Expenses</td>
                    <td><?= count($expenses) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Expenses (<?= count($expenses) ?>)</h2>
        <?php if (empty($expenses)): ?>
            <p><em>No expenses recorded.</em></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['title']) ?></td>
                            <td><?= number_format((float)$e['converted_amount'], 2) ?> <?= htmlspecialchars($trip->getBaseCurrency()) ?></td>
                            <td><?= htmlspecialchars($e['type']) ?></td>
                            <td><?= htmlspecialchars($e['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Report generated on <?= date('Y-m-d H:i:s') ?> for <?= htmlspecialchars($user->getEmail()) ?></p>
        <p>Triply Trip Management System</p>
        <button class="no-print" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <script>
        // Auto-print if requested
        if (window.location.search.includes('print=1')) {
            setTimeout(() => window.print(), 500);
        }
    </script>
</body>

</html>
<?php

$html = ob_get_clean();

// Output based on format requested
$format = $_GET['format'] ?? 'html';

if ($format === 'pdf') {
    // If FPDF is available, use it
    if (class_exists('FPDF')) {
        // Convert HTML to PDF using FPDF
        // Note: This requires additional HTML parsing library
        // For now, output as HTML that can be printed to PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="trip_' . $tripId . '_report.pdf"');
        // This would need mPDF or similar for full HTML support
        echo $html; // Fallback to HTML
    } else {
        // No FPDF available, output HTML for browser print-to-PDF
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
} else {
    // HTML output
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
?>