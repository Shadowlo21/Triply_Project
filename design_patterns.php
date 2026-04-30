<?php

/**
 * Design Patterns Documentation
 * Visual guide to all design patterns implemented in Triply
 */

session_start();
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/boundary/Auth.php';

$isAuthed = (bool)Auth::current();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Design Patterns — Triply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="/public/css/style.css">
    <style>
        .pattern-card {
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .pattern-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.1);
        }

        .pattern-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .pattern-badge.singleton {
            background: #e7f3ff;
            color: #0066cc;
        }

        .pattern-badge.observer {
            background: #fff3e0;
            color: #e65100;
        }

        .pattern-badge.strategy {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .pattern-badge.factory {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .code-block {
            background: #f5f5f5;
            border-left: 4px solid #007bff;
            padding: 12px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            border-radius: 4px;
        }

        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 8px;
        }

        .hero h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.1em;
            opacity: 0.95;
        }

        .pattern-item {
            margin-bottom: 30px;
        }

        .pattern-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .pattern-icon {
            font-size: 2em;
        }

        .footer-text {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>

<body style="background: #fafafa;">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="/" style="font-weight: 700; font-size: 1.3em;">
                <i class="fas fa-plane-departure"></i> Triply
            </a>
            <div class="ms-auto">
                <?php if ($isAuthed): ?>
                    <a href="/?page=dashboard" class="btn btn-light btn-sm">Dashboard</a>
                <?php else: ?>
                    <a href="/?page=login" class="btn btn-light btn-sm">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="container">
        <div class="hero mt-5">
            <h1>🏗️ Design Patterns in Triply</h1>
            <p>A comprehensive guide to the design patterns used throughout the Triply trip planning application</p>
        </div>
    </div>

    <!-- Content -->
    <div class="container" style="max-width: 900px;">
        <!-- 1. Singleton Pattern -->
        <div class="pattern-card">
            <div class="pattern-header">
                <div class="pattern-icon">🔒</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.5em;">Singleton Pattern</h2>
                    <span class="pattern-badge singleton">Structural</span>
                </div>
            </div>
            <hr>
            <p><strong>Purpose:</strong> Ensures only one database connection instance exists per database file throughout the application lifecycle.</p>
            <p><strong>Implementation:</strong></p>
            <ul>
                <li><strong>File:</strong> <code>config/Database.php</code></li>
                <li><strong>Class:</strong> <code>Database</code></li>
                <li><strong>Key Method:</strong> <code>getInstance(string $db): PDO</code></li>
            </ul>
            <p><strong>Why Used in Triply:</strong></p>
            <ul>
                <li>Manages 5 separate SQLite databases (accounts, trips, financial, social, documents)</li>
                <li>Prevents resource exhaustion from multiple DB connections</li>
                <li>Ensures consistent DB state across the application</li>
            </ul>
            <div class="code-block">
                Database::getInstance('trips')->prepare(...)->execute(...);<br>
                Database::for('expenses')->query(...);<br>
                // Each call returns the same connection instance
            </div>
        </div>

        <!-- 2. Observer Pattern -->
        <div class="pattern-card">
            <div class="pattern-header">
                <div class="pattern-icon">👁️</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.5em;">Observer Pattern</h2>
                    <span class="pattern-badge observer">Behavioral</span>
                </div>
            </div>
            <hr>
            <p><strong>Purpose:</strong> Notifies interested parties when important state changes occur in the system.</p>
            <p><strong>Implementation:</strong></p>
            <ul>
                <li><strong>File:</strong> <code>classes/control/Notification.php</code></li>
                <li><strong>Class:</strong> <code>Notification</code></li>
                <li><strong>Events Observed:</strong> Trip creation, poll closure, budget threshold, daily briefings</li>
            </ul>
            <p><strong>Observable Events:</strong></p>
            <ul>
                <li><code>tripInvite()</code> — User invited to trip</li>
                <li><code>pollClosed()</code> — Poll closed with winner announcement</li>
                <li><code>checkBudgetThreshold()</code> — Budget alert at 80%+</li>
                <li><code>sendDailyBriefings()</code> — Tomorrow's schedule notifications</li>
            </ul>
            <div class="code-block">
                Notification::tripInvite($userId, $tripTitle, $inviterName);<br>
                Notification::pollClosed($pollId, $tripId, $winnerOptionId);<br>
                Notification::checkBudgetThreshold($tripId);
            </div>
        </div>

        <!-- 3. Strategy Pattern -->
        <div class="pattern-card">
            <div class="pattern-header">
                <div class="pattern-icon">🔄</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.5em;">Strategy Pattern</h2>
                    <span class="pattern-badge strategy">Behavioral</span>
                </div>
            </div>
            <hr>
            <p><strong>Purpose:</strong> Encapsulates interchangeable algorithms for splitting expenses among trip members.</p>
            <p><strong>Implementation:</strong></p>
            <ul>
                <li><strong>File:</strong> <code>classes/entities/Expense.php</code></li>
                <li><strong>Class:</strong> <code>Expense</code></li>
                <li><strong>Strategy Methods:</strong></li>
                <li style="margin-left: 20px;">
                    <code>splitEqual(array $userIds): bool</code> — Divide equally among all members
                </li>
                <li style="margin-left: 20px;">
                    <code>splitCustom(array $splits): bool</code> — Custom per-person amounts
                </li>
                <li style="margin-left: 20px;">
                    <code>splitByPercentage(array $percentages): bool</code> — Percentage-based division
                </li>
            </ul>
            <p><strong>Why Used in Triply:</strong></p>
            <ul>
                <li>Provides flexibility for different expense-splitting scenarios</li>
                <li>Reduces code duplication</li>
                <li>Easy to add new splitting algorithms in the future</li>
            </ul>
            <div class="code-block">
                $splitType = $_POST['split_type'] ?? 'equal';<br>
                match($splitType) {<br>
                &nbsp;&nbsp;'equal' => $expense->splitEqual($memberIds),<br>
                &nbsp;&nbsp;'percentage' => $expense->splitByPercentage($percentages),<br>
                &nbsp;&nbsp;'custom' => $expense->splitCustom($amounts),<br>
                };
            </div>
        </div>

        <!-- 4. Factory Pattern -->
        <div class="pattern-card">
            <div class="pattern-header">
                <div class="pattern-icon">🏭</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.5em;">Factory Pattern</h2>
                    <span class="pattern-badge factory">Creational</span>
                </div>
            </div>
            <hr>
            <p><strong>Purpose:</strong> Creates appropriate user objects (Member or TripLeader) based on their role without exposing instantiation logic.</p>
            <p><strong>Implementation:</strong></p>
            <ul>
                <li><strong>File:</strong> <code>classes/boundary/Auth.php</code></li>
                <li><strong>Class:</strong> <code>Auth</code></li>
                <li><strong>Factory Methods:</strong> <code>login()</code>, <code>register()</code></li>
            </ul>
            <p><strong>Product Classes:</strong></p>
            <ul>
                <li><code>Member</code> — Base user (can join trips, set attendance, propose activities)</li>
                <li><code>TripLeader</code> — Extends Member (can create trips, confirm activities, manage permissions)</li>
            </ul>
            <p><strong>Why Used in Triply:</strong></p>
            <ul>
                <li>Encapsulates role-based user creation logic</li>
                <li>Ensures correct user type is instantiated based on database role</li>
                <li>Simplifies client code — no need to know about Member/TripLeader</li>
            </ul>
            <div class="code-block">
                $user = match($row['role']) {<br>
                &nbsp;&nbsp;'leader', 'admin' => new TripLeader(...),<br>
                &nbsp;&nbsp;default => new Member(...),<br>
                };
            </div>
        </div>

        <!-- Summary -->
        <div style="background: #f0f7ff; border: 2px solid #007bff; border-radius: 8px; padding: 20px; margin-top: 30px;">
            <h3 style="color: #007bff; margin-bottom: 15px;">📊 Pattern Summary</h3>
            <table class="table table-sm" style="margin-bottom: 0;">
                <thead>
                    <tr style="background: #e7f3ff;">
                        <th>Pattern</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Singleton</strong></td>
                        <td>Structural</td>
                        <td><code>config/Database.php</code></td>
                        <td>One DB connection per database</td>
                    </tr>
                    <tr>
                        <td><strong>Observer</strong></td>
                        <td>Behavioral</td>
                        <td><code>classes/control/Notification.php</code></td>
                        <td>Send notifications on state changes</td>
                    </tr>
                    <tr>
                        <td><strong>Strategy</strong></td>
                        <td>Behavioral</td>
                        <td><code>classes/entities/Expense.php</code></td>
                        <td>Multiple expense-splitting algorithms</td>
                    </tr>
                    <tr>
                        <td><strong>Factory</strong></td>
                        <td>Creational</td>
                        <td><code>classes/boundary/Auth.php</code></td>
                        <td>Create correct user type by role</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Additional Architectural Patterns -->
        <div style="background: #f5f5f5; border-radius: 8px; padding: 20px; margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">🏛️ Additional Architectural Patterns</h3>
            <p><strong>MVC Architecture:</strong></p>
            <ul>
                <li><strong>Models:</strong> <code>classes/entities/</code> (User, Trip, Activity, Expense, Poll, Document, etc.)</li>
                <li><strong>Views:</strong> <code>views/</code> (dashboard, trips, itinerary, financial, social, documents)</li>
                <li><strong>Controllers:</strong> <code>api/</code> (auth, trips, itinerary, financial, social, documents)</li>
            </ul>
            <p><strong>Boundary Pattern:</strong></p>
            <ul>
                <li><code>classes/boundary/</code> contains <code>Auth</code> and <code>ApiResponse</code></li>
                <li>Protects core business logic from external interactions</li>
            </ul>
            <p><strong>Data Encryption (Strategy):</strong></p>
            <ul>
                <li><code>config/Encryption.php</code> — AES-256-GCM encryption for sensitive user data</li>
                <li>User sensitive fields stored encrypted at rest</li>
            </ul>
        </div>

        <div class="footer-text">
            <p>Triply Design Patterns Documentation | Built with PHP, SQLite, and Bootstrap</p>
            <p><a href="/" style="color: #007bff;">← Back to Triply</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>