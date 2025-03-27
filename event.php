<?php
require_once __DIR__ . '/config/config.php';
session_start();

// Redirect if event ID is invalid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$event_id = $_GET['id'];

// Fetch event details
$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.description, e.date, e.time, e.location, c.id AS club_id, c.name AS club_name 
    FROM events e
    JOIN clubs c ON e.club_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: index.php");
    exit();
}

// Fetch attendees (students who have registered for the event)
$stmt = $pdo->prepare("
    SELECT u.name, u.email 
    FROM event_attendees ea
    JOIN club_members cm ON ea.member_id = cm.id
    JOIN students s ON cm.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ea.event_id = ?
");
$stmt->execute([$event_id]);
$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_registered = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    // Get the student ID for this user
    $student_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $student_stmt->execute([$_SESSION['user_id']]);
    $student_id = $student_stmt->fetchColumn();

    if ($student_id) {
        // Get the club member ID for this student in the event's club
        $member_stmt = $pdo->prepare("SELECT id FROM club_members WHERE student_id = ? AND club_id = ?");
        $member_stmt->execute([$student_id, $event['club_id']]);
        $member_id = $member_stmt->fetchColumn();

        if ($member_id) {
            // Check if the student is already registered for the event
            $registration_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_attendees WHERE event_id = ? AND member_id = ?");
            $registration_stmt->execute([$event_id, $member_id]);
            $is_registered = $registration_stmt->fetchColumn();
        }
    }
}

// Handle RSVP form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rsvp'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        $_SESSION['rsvp_error'] = "You must be logged in as a student to RSVP.";
        header("Location: event.php?id=$event_id");
        exit();
    }

    try {
        // Ensure the user is a member of the club
        if (!$member_id) {
            throw new Exception("You must be a member of the club to RSVP.");
        }

        // Prevent duplicate registrations
        if ($is_registered) {
            throw new Exception("You are already registered for this event.");
        }

        // Insert the RSVP record (club_id is redundant here as events already link to clubs)
        $stmt = $pdo->prepare("INSERT INTO event_attendees (event_id, member_id) VALUES (?, ?)");
        $stmt->execute([$event_id, $member_id]);

        $_SESSION['rsvp_success'] = "You have successfully registered for the event!";
    } catch (Exception $e) {
        $_SESSION['rsvp_error'] = $e->getMessage();
    }

    header("Location: event.php?id=$event_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['name']) ?> - Event Details</title>
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
        .attendee-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .overview-card {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Club Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="forum.php">Forum</a>
                <a class="nav-link" href="message.php">Message</a>
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
        <?php if (isset($_SESSION['rsvp_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['rsvp_success']) ?></div>
            <?php unset($_SESSION['rsvp_success']); ?>
        <?php elseif (isset($_SESSION['rsvp_error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['rsvp_error']) ?></div>
            <?php unset($_SESSION['rsvp_error']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h1 class="card-title"><?= htmlspecialchars($event['name']) ?></h1>
                        <p class="card-text"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                        <p class="text-muted">
                            <strong>Date:</strong> <?= date('M j, Y', strtotime($event['date'])) ?><br>
                            <strong>Time:</strong> <?= htmlspecialchars($event['time']) ?><br>
                            <strong>Location:</strong> <?= htmlspecialchars($event['location']) ?><br>
                            <strong>Club:</strong> <a href="club_details.php?id=<?= $event['club_id'] ?>"><?= htmlspecialchars($event['club_name']) ?></a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">RSVP</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['role'] !== 'student'): ?>
                                <div class="alert alert-warning">
                                    Only students can RSVP for events.
                                </div>
                            <?php elseif ($is_registered): ?>
                                <div class="alert alert-success mb-3">
                                    You are registered for this event.
                                </div>
                            <?php else: ?>
                                <form action="event.php?id=<?= $event_id ?>" method="POST">
                                    <button type="submit" name="rsvp" class="btn btn-primary w-100">
                                        Register for Event
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Please login to register for this event.
                            </div>
                            <a href="login.php?redirect=event.php?id=<?= $event_id ?>" class="btn btn-primary w-100">
                                Login to Register
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-black text-white">
                        <h3 class="mb-0">Attendees</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendees)): ?>
                            <div class="list-group">
                                <?php foreach ($attendees as $attendee): ?>
                                    <div class="list-group-item">
                                        <h5 class="mb-0"><?= htmlspecialchars($attendee['name']) ?></h5>
                                        <small class="attendee-email"><?= htmlspecialchars($attendee['email']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No attendees yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>