<?php
require_once __DIR__ . '/config/config.php'; // Correct path to config file
session_start(); // Enable sessions

$clubs = $pdo->query("
    SELECT c.id, c.name, c.description, c.type, COUNT(cm.student_id) AS total_members
    FROM clubs c
    LEFT JOIN club_members cm ON c.id = cm.club_id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$events = $pdo->query("
    SELECT e.id, e.name, e.date, e.location, c.name AS club_name
    FROM events e
    JOIN clubs c ON e.club_id = c.id
    WHERE e.date >= CURDATE()
    ORDER BY e.date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Management - Home</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9f9f9;
            color: #333;
        }
        .navbar {
            background-color: #000;
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: #fff !important;
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .badge {
            background-color: #000;
            color: #fff;
        }
        .event-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Club Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="forum.php">Forum</a>
                <a class="nav-link" href="clubs.php">Clubs</a>
                <a class="nav-link" href="events.php">Events</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : ($_SESSION['role'] === 'club_manager' ? 'club_manager_dashboard.php' : 'student_dashboard.php') ?>">Dashboard</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                    <a class="nav-link" href="signup.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="mb-4">Welcome to Club Management</h2>

        <!-- Upcoming Events Section -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Upcoming Events</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($events)): ?>
                    <div class="list-group">
                        <?php foreach ($events as $event): ?>
                            <a href="event.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?= htmlspecialchars($event['name']) ?></h5>
                                    <small><?= date('M j', strtotime($event['date'])) ?></small>
                                </div>
                                <small class="text-muted">
                                    <?= htmlspecialchars($event['club_name']) ?>
                                    <?php if ($event['location']): ?>
                                        | <?= htmlspecialchars($event['location']) ?>
                                    <?php endif; ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming events at this time.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Clubs Section -->
        <div class="card">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Clubs</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($clubs as $club): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($club['name']) ?></h5>
                                    <p class="card-text text-muted">
                                        <strong>Type:</strong> <?= ucfirst($club['type']) ?><br>
                                        <strong>Members:</strong> <?= $club['total_members'] ?>
                                    </p>
                                    <p class="card-text"><?= nl2br(htmlspecialchars(substr($club['description'], 0, 100))) ?>...</p>
                                    <a href="club_details.php?id=<?= $club['id'] ?>" class="btn btn-primary w-100">
                                        View Club Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>