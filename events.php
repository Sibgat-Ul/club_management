<?php
require_once __DIR__ . '/config/config.php'; // Correct path to config file
session_start();

// Fetch events based on club ID or globally
$club_id = isset($_GET['club']) && is_numeric($_GET['club']) ? $_GET['club'] : null;

if ($club_id) {
    // Fetch club name for the header
    $stmt = $pdo->prepare("SELECT name FROM clubs WHERE id = ?");
    $stmt->execute([$club_id]);
    $club_name = $stmt->fetchColumn();

    // Fetch events for the specific club
    $stmt = $pdo->prepare("SELECT id, name, date, location FROM events WHERE club_id = ? ORDER BY date ASC");
    $stmt->execute([$club_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Fetch all events across clubs
    $stmt = $pdo->query("SELECT e.id, e.name, e.date, e.location, c.name AS club_name FROM events e JOIN clubs c ON e.club_id = c.id ORDER BY e.date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $club_id ? htmlspecialchars($club_name) . ' Events' : 'All Events' ?></title>
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
        <h2 class="mb-4"><?= $club_id ? htmlspecialchars($club_name) . ' Events' : 'All Events' ?></h2>

        <?php if (!empty($events)): ?>
            <div class="list-group">
                <?php foreach ($events as $event): ?>
                    <a href="event.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?= htmlspecialchars($event['name']) ?></h5>
                            <small><?= date('M j', strtotime($event['date'])) ?></small>
                        </div>
                        <?php if (isset($event['club_name'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($event['club_name']) ?></small>
                        <?php endif; ?>
                        <?php if ($event['location']): ?>
                            <small class="text-muted"><?= htmlspecialchars($event['location']) ?></small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No events found.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>