<?php
require_once __DIR__ . '/config/config.php'; 
session_start(); 

$join_message = '';
if (isset($_SESSION['join_success'])) {
    $join_message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['join_success']) . '</div>';
    unset($_SESSION['join_success']);
} elseif (isset($_SESSION['join_error'])) {
    $join_message = '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['join_error']) . '</div>';
    unset($_SESSION['join_error']);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clubs.php");
    exit();
}
$club_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    header("Location: clubs.php");
    exit();
}

$members = $pdo->prepare("
    SELECT u.name, u.email, cm.position, s.id AS student_id
    FROM club_members cm
    JOIN students s ON cm.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE cm.club_id = ?
    ORDER BY 
        CASE cm.position
            WHEN 'president' THEN 1
            WHEN 'vice_president' THEN 2
            WHEN 'general_secretary' THEN 3
            WHEN 'treasurer' THEN 4
            ELSE 5
        END
");

$members->execute([$club_id]);
$members = $members->fetchAll(PDO::FETCH_ASSOC);

$advisor = $pdo->prepare("
    SELECT u.name, u.email, t.department
    FROM club_advisors ca
    JOIN teachers t ON ca.advisor_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ca.club_id = ?
");

$advisor->execute([$club_id]);
$advisor = $advisor->fetch(PDO::FETCH_ASSOC);

$totalMembers = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ?");
$totalMembers->execute([$club_id]);
$totalMembers = $totalMembers->fetchColumn();

$events = $pdo->prepare("
    SELECT id, name, date, location 
    FROM events 
    WHERE club_id = ? AND date >= CURDATE()
    ORDER BY date ASC
    LIMIT 3
");

$events->execute([$club_id]);
$events = $events->fetchAll(PDO::FETCH_ASSOC);

$is_member = false;
if (isset($_SESSION['user_id'])) {
    $student_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $student_stmt->execute([$_SESSION['user_id']]);
    $student_id = $student_stmt->fetchColumn();

    if ($student_id) {
        $membership_stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND student_id = ?");
        $membership_stmt->execute([$club_id, $student_id]);
        $is_member = $membership_stmt->fetchColumn();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($club['name']) ?> - Club Details</title>
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
        .member-email {
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
        <?= $join_message ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Club Description -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h1 class="card-title"><?= htmlspecialchars($club['name']) ?></h1>
                        <p class="card-text"><?= nl2br(htmlspecialchars($club['description'])) ?></p>
                        <p class="text-muted">
                            <strong>Type:</strong> <?= ucfirst($club['type']) ?> | 
                            <strong>Contact:</strong> <?= htmlspecialchars($club['contact']) ?>
                        </p>
                    </div>
                </div>

                <!-- Leadership Team -->
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Leadership Team</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($members as $member): ?>
                                <?php if (in_array($member['position'], ['president', 'vice_president', 'general_secretary', 'treasurer'])): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-grow-1">
                                                <h5 class="mb-0"><?= htmlspecialchars($member['name']) ?></h5>
                                                <span class="badge bg-secondary position-badge">
                                                    <?= str_replace('_', ' ', $member['position']) ?>
                                                </span>
                                                <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <?php if (!empty($events)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Upcoming Events</h3>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($events as $event): ?>
                                <a href="event.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?= htmlspecialchars($event['name']) ?></h5>
                                        <small><?= date('M j', strtotime($event['date'])) ?></small>
                                    </div>
                                    <?php if ($event['location']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($event['location']) ?></small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Club Advisor -->
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Club Advisor</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($advisor): ?>
                            <h5><?= htmlspecialchars($advisor['name']) ?></h5>
                            <p class="mb-1"><?= htmlspecialchars($advisor['email']) ?></p>
                            <p class="text-muted"><?= htmlspecialchars($advisor['department']) ?></p>
                        <?php else: ?>
                            <p class="text-muted">No advisor assigned</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Membership Section -->
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Membership</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Total Members:</span>
                            <strong><?= $totalMembers ?></strong>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($is_member): ?>
                                <div class="alert alert-success mb-3">
                                    You are a member of this club.
                                </div>
                                <h5>Club Members</h5>
                                <ul class="list-group">
                                    <?php foreach ($members as $member): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($member['name']) ?>
                                            <span class="badge bg-secondary position-badge">
                                                <?= str_replace('_', ' ', $member['position'] ?: 'Member') ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <form action="join_club.php" method="POST">
                                    <input type="hidden" name="club_id" value="<?= $club_id ?>">
                                    <button type="submit" class="btn btn-primary w-100">
                                        Join Club
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Please login to join this club.
                            </div>
                            <a href="login.php?redirect=club_details.php?id=<?= $club_id ?>" class="btn btn-primary w-100">
                                Login to Join
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="events.php?club=<?= $club_id ?>" class="btn btn-outline-secondary w-100 mb-2">
                            View All Events
                        </a>
                        <?php if ($is_member): ?>
                            <a href="messages.php?club=<?= $club_id ?>" class="btn btn-outline-secondary w-100 mb-2">
                                Club Messages
                            </a>
                            <a href="forum.php?club=<?= $club_id ?>" class="btn btn-outline-secondary w-100">
                                Club Forum
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>