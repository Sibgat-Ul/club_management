<?php
require_once __DIR__ . '/config/config.php'; // Correct path to config file
session_start(); // Enable sessions

// Redirect if user is not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch student basic details from the users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit();
}

// Fetch detailed student info (e.g., phone number) from the students table using the user_id
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// Merge the data so that we have both basic and detailed info
$student = array_merge($user, $studentDetails ? $studentDetails : []);

// Fetch clubs the student is a member of using the student's id from the students table if available,
// otherwise, fall back to the user's id.
$studentIdForMembership = $studentDetails ? $studentDetails['id'] : $user['id'];
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.type, cm.position 
    FROM club_members cm
    JOIN clubs c ON cm.club_id = c.id
    WHERE cm.student_id = ?
");
$stmt->execute([$studentIdForMembership]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch upcoming events for the clubs the student is part of
$club_ids = array_column($clubs, 'id');
if (!empty($club_ids)) {
    $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT e.id, e.name, e.date, e.time, e.location, c.name AS club_name 
        FROM events e
        JOIN clubs c ON e.club_id = c.id
        WHERE e.club_id IN ($placeholders) AND e.date >= CURDATE()
        ORDER BY e.date ASC
        LIMIT 5
    ");
    $stmt->execute($club_ids);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $events = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Club Management</title>
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
        <h2 class="mb-4">Welcome, <?= htmlspecialchars($student['name']) ?>!</h2>

        <!-- Personal Information Section -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Your Profile</h3>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= htmlspecialchars($student['name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                <?php if (isset($student['phone']) && $student['phone']): ?>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($student['phone']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Clubs Section -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Your Clubs</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($clubs)): ?>
                    <div class="list-group">
                        <?php foreach ($clubs as $club): ?>
                            <a href="club_details.php?id=<?= $club['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($club['name']) ?></span>
                                <span class="badge"><?= ucfirst($club['type']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">You are not a member of any clubs yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Events Section -->
        <div class="card">
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
                                    <small class="event-date"><?= date('M j', strtotime($event['date'])) ?></small>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($event['club_name']) ?></small>
                                <?php if ($event['location']): ?>
                                    <small class="text-muted"> | <?= htmlspecialchars($event['location']) ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming events for your clubs.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
